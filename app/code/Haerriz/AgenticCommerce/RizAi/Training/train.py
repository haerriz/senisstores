#!/usr/bin/env python3
"""Train and export the lightweight RizAI neural intent model.

Runtime inference is implemented in PHP and does not require Python/Torch in Magento. This script is
for offline, reviewed model releases only.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import math
import random
import re
import unicodedata
import zlib
from collections import Counter
from pathlib import Path

import numpy as np
import torch
from torch import nn
from torch.utils.data import DataLoader, TensorDataset

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_DATA = Path(__file__).with_name("commerce_intents.jsonl")
DEFAULT_OUT = ROOT / "Model" / "rizai-commerce-intent-v1.json"

SEED = 4305
INPUT_DIM = 1024
HIDDEN_DIM = 96


def normalize(text: str) -> str:
    text = unicodedata.normalize("NFKC", text).lower().strip()
    text = re.sub(r"[^\w\s]+", " ", text, flags=re.UNICODE)
    text = text.replace("_", " ")
    return " ".join(text.split())


def crc_index(feature: str, dim: int) -> int:
    return zlib.crc32(feature.encode("utf-8")) % dim


def features(text: str, dim: int = INPUT_DIM) -> np.ndarray:
    text = normalize(text)
    vec = np.zeros(dim, dtype=np.float32)
    if not text:
        return vec
    tokens = text.split()
    feats: list[str] = [f"w:{t}" for t in tokens]
    feats += [f"b:{tokens[i]} {tokens[i+1]}" for i in range(len(tokens) - 1)]
    compact = f" {text} "
    for n in (3, 4):
        if len(compact) >= n:
            feats += [f"c{n}:{compact[i:i+n]}" for i in range(len(compact) - n + 1)]
    for feat in feats:
        vec[crc_index(feat, dim)] += 1.0
    norm = float(np.linalg.norm(vec))
    if norm > 0:
        vec /= norm
    return vec


class IntentMLP(nn.Module):
    def __init__(self, input_dim: int, hidden_dim: int, classes: int):
        super().__init__()
        self.fc1 = nn.Linear(input_dim, hidden_dim)
        self.act = nn.ReLU()
        self.fc2 = nn.Linear(hidden_dim, classes)

    def forward(self, x: torch.Tensor) -> torch.Tensor:
        return self.fc2(self.act(self.fc1(x)))


def load_rows(path: Path):
    rows = []
    with path.open("r", encoding="utf-8") as f:
        for line in f:
            if line.strip():
                rows.append(json.loads(line))
    return rows


def accuracy(model, x, y):
    model.eval()
    with torch.no_grad():
        logits = model(x)
        pred = logits.argmax(dim=1)
        acc = (pred == y).float().mean().item()
        probs = torch.softmax(logits, dim=1)
        top = probs.max(dim=1).values.mean().item()
    return acc, top


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--data", type=Path, default=DEFAULT_DATA)
    ap.add_argument("--out", type=Path, default=DEFAULT_OUT)
    ap.add_argument("--epochs", type=int, default=120)
    args = ap.parse_args()

    random.seed(SEED)
    np.random.seed(SEED)
    torch.manual_seed(SEED)
    torch.set_num_threads(max(1, min(4, torch.get_num_threads())))

    rows = load_rows(args.data)
    labels = sorted({r["label"] for r in rows})
    label_to_idx = {label: i for i, label in enumerate(labels)}

    train_rows = [r for r in rows if r.get("split") == "train"]
    val_rows = [r for r in rows if r.get("split") == "validation"]

    def tensorize(subset):
        x = np.stack([features(r["text"]) for r in subset], axis=0)
        y = np.array([label_to_idx[r["label"]] for r in subset], dtype=np.int64)
        return torch.from_numpy(x), torch.from_numpy(y)

    x_train, y_train = tensorize(train_rows)
    x_val, y_val = tensorize(val_rows)

    model = IntentMLP(INPUT_DIM, HIDDEN_DIM, len(labels))
    counts = Counter(r["label"] for r in train_rows)
    class_weights = torch.tensor(
        [math.sqrt(len(train_rows) / (len(labels) * counts[label])) for label in labels],
        dtype=torch.float32,
    )
    criterion = nn.CrossEntropyLoss(weight=class_weights)
    optimizer = torch.optim.AdamW(model.parameters(), lr=0.01, weight_decay=0.0008)
    scheduler = torch.optim.lr_scheduler.CosineAnnealingLR(optimizer, T_max=args.epochs, eta_min=0.0003)
    loader = DataLoader(TensorDataset(x_train, y_train), batch_size=128, shuffle=True)

    best = None
    best_acc = -1.0
    patience = 0
    for epoch in range(1, args.epochs + 1):
        model.train()
        for xb, yb in loader:
            optimizer.zero_grad(set_to_none=True)
            loss = criterion(model(xb), yb)
            loss.backward()
            optimizer.step()
        scheduler.step()
        val_acc, val_conf = accuracy(model, x_val, y_val)
        if val_acc > best_acc + 1e-6:
            best_acc = val_acc
            best = {k: v.detach().cpu().clone() for k, v in model.state_dict().items()}
            patience = 0
        else:
            patience += 1
        if epoch % 10 == 0 or epoch == 1:
            train_acc, _ = accuracy(model, x_train, y_train)
            print(f"epoch={epoch:03d} train_acc={train_acc:.4f} val_acc={val_acc:.4f} val_conf={val_conf:.4f}")
        if patience >= 40 and epoch >= 60:
            break

    if best is not None:
        model.load_state_dict(best)
    train_acc, train_conf = accuracy(model, x_train, y_train)
    val_acc, val_conf = accuracy(model, x_val, y_val)

    # Per-class validation metrics.
    model.eval()
    with torch.no_grad():
        preds = model(x_val).argmax(dim=1).cpu().numpy()
    per_class = {}
    for label, idx in label_to_idx.items():
        mask = y_val.numpy() == idx
        total = int(mask.sum())
        correct = int((preds[mask] == idx).sum()) if total else 0
        per_class[label] = {"samples": total, "accuracy": round(correct / total, 4) if total else None}

    state = model.state_dict()
    # Export orientation chosen for efficient PHP sparse inference: w1[hidden][input], w2[class][hidden].
    payload = {
        "schema_version": 1,
        "model_id": "rizai-commerce-intent-v1",
        "model_family": "RizAI Commerce Neural Intent",
        "model_type": "feed_forward_neural_network",
        "architecture": {
            "input": "hashed_word_bigram_char_ngram",
            "input_dim": INPUT_DIM,
            "hidden_dim": HIDDEN_DIM,
            "activation": "relu",
            "output": "softmax_intent_classes",
            "class_count": len(labels),
        },
        "labels": labels,
        "training": {
            "seed": SEED,
            "dataset": args.data.name,
            "train_examples": len(train_rows),
            "validation_examples": len(val_rows),
            "train_accuracy": round(train_acc, 4),
            "validation_accuracy": round(val_acc, 4),
            "mean_validation_confidence": round(val_conf, 4),
            "split_strategy": "grouped_by_base_utterance",
            "note": "Synthetic/curated commerce-intent holdout metrics are not a production benchmark.",
            "per_class_validation": per_class,
        },
        "feature_hash": {"algorithm": "crc32", "word_unigrams": True, "word_bigrams": True, "char_ngrams": [3, 4], "l2_normalize": True},
        "weights": {
            "w1": np.round(state["fc1.weight"].numpy(), 6).tolist(),
            "b1": np.round(state["fc1.bias"].numpy(), 6).tolist(),
            "w2": np.round(state["fc2.weight"].numpy(), 6).tolist(),
            "b2": np.round(state["fc2.bias"].numpy(), 6).tolist(),
        },
    }
    args.out.parent.mkdir(parents=True, exist_ok=True)
    with args.out.open("w", encoding="utf-8") as f:
        json.dump(payload, f, ensure_ascii=False, separators=(",", ":"))
    digest = hashlib.sha256(args.out.read_bytes()).hexdigest()
    checksum_path = args.out.with_suffix(".sha256")
    checksum_path.write_text(digest + "  " + args.out.name + "\n", encoding="utf-8")
    print(f"exported {args.out}")
    print(f"sha256={digest}")
    print(f"train_accuracy={train_acc:.4f} validation_accuracy={val_acc:.4f} validation_confidence={val_conf:.4f}")


if __name__ == "__main__":
    main()
