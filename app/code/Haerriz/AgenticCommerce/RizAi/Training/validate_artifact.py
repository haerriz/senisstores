#!/usr/bin/env python3
"""Validate the bundled RizAI neural model artifact without Magento.

Checks artifact integrity, matrix shapes, training-metadata consistency, grouped split isolation and
recomputed holdout accuracy using the *exported rounded weights* that PHP will execute.
"""
from __future__ import annotations

import hashlib
import json
import math
from collections import Counter, defaultdict
from pathlib import Path

import numpy as np

from train import DEFAULT_DATA, DEFAULT_OUT, features


def load_jsonl(path: Path) -> list[dict]:
    rows: list[dict] = []
    for line_no, line in enumerate(path.read_text(encoding="utf-8").splitlines(), 1):
        if not line.strip():
            continue
        row = json.loads(line)
        if not isinstance(row, dict):
            raise ValueError(f"{path}:{line_no}: row is not an object")
        rows.append(row)
    return rows


def softmax(logits: np.ndarray) -> np.ndarray:
    logits = logits - logits.max(axis=1, keepdims=True)
    exp = np.exp(np.clip(logits, -60.0, 60.0))
    return exp / exp.sum(axis=1, keepdims=True)


def main() -> None:
    model_path = DEFAULT_OUT
    data_path = DEFAULT_DATA
    checksum_path = model_path.with_suffix(".sha256")

    raw = model_path.read_bytes()
    actual_checksum = hashlib.sha256(raw).hexdigest()
    checksum_tokens = checksum_path.read_text(encoding="utf-8").split()
    expected_checksum = checksum_tokens[0].lower() if checksum_tokens else ""
    if actual_checksum != expected_checksum:
        raise SystemExit(f"checksum mismatch: expected {expected_checksum}, got {actual_checksum}")

    model = json.loads(raw)
    if model.get("schema_version") != 1:
        raise SystemExit("unsupported schema_version")
    arch = model["architecture"]
    input_dim = int(arch["input_dim"])
    hidden_dim = int(arch["hidden_dim"])
    labels = list(model["labels"])
    class_count = int(arch["class_count"])
    if class_count != len(labels):
        raise SystemExit("class_count does not match labels")

    w1 = np.asarray(model["weights"]["w1"], dtype=np.float32)
    b1 = np.asarray(model["weights"]["b1"], dtype=np.float32)
    w2 = np.asarray(model["weights"]["w2"], dtype=np.float32)
    b2 = np.asarray(model["weights"]["b2"], dtype=np.float32)
    expected_shapes = {
        "w1": (hidden_dim, input_dim),
        "b1": (hidden_dim,),
        "w2": (class_count, hidden_dim),
        "b2": (class_count,),
    }
    actual_shapes = {"w1": w1.shape, "b1": b1.shape, "w2": w2.shape, "b2": b2.shape}
    if actual_shapes != expected_shapes:
        raise SystemExit(f"weight shape mismatch: {actual_shapes} != {expected_shapes}")
    if not all(np.isfinite(x).all() for x in (w1, b1, w2, b2)):
        raise SystemExit("weights contain non-finite values")

    rows = load_jsonl(data_path)
    train_rows = [r for r in rows if r.get("split") == "train"]
    val_rows = [r for r in rows if r.get("split") == "validation"]
    metadata = model["training"]
    if len(train_rows) != int(metadata["train_examples"]) or len(val_rows) != int(metadata["validation_examples"]):
        raise SystemExit("dataset counts do not match model metadata")

    # The dataset builder records a group id shared by all polite variants of the same base phrase.
    train_groups = {str(r.get("group_id", "")) for r in train_rows if r.get("group_id")}
    val_groups = {str(r.get("group_id", "")) for r in val_rows if r.get("group_id")}
    overlap = train_groups & val_groups
    if overlap:
        raise SystemExit(f"group leakage detected across train/validation: {sorted(overlap)[:5]}")

    exact_split_map: dict[tuple[str, str], set[str]] = defaultdict(set)
    for r in rows:
        exact_split_map[(str(r["text"]).casefold(), str(r["label"]))].add(str(r["split"]))
    leaked_exact = [key for key, splits in exact_split_map.items() if len(splits) > 1]
    if leaked_exact:
        raise SystemExit(f"exact phrase leakage detected: {leaked_exact[:5]}")

    label_to_idx = {label: i for i, label in enumerate(labels)}
    x = np.stack([features(str(r["text"]), input_dim) for r in val_rows])
    y = np.asarray([label_to_idx[str(r["label"])] for r in val_rows], dtype=np.int64)
    hidden = np.maximum(0.0, x @ w1.T + b1)
    probs = softmax(hidden @ w2.T + b2)
    pred = probs.argmax(axis=1)
    acc = float((pred == y).mean())
    mean_conf = float(probs.max(axis=1).mean())

    reported_acc = float(metadata["validation_accuracy"])
    reported_conf = float(metadata["mean_validation_confidence"])
    # Exported weights are rounded to six decimals; allow only tiny metadata drift.
    if abs(acc - reported_acc) > 0.002:
        raise SystemExit(f"artifact accuracy drift: recomputed={acc:.4f}, metadata={reported_acc:.4f}")
    if abs(mean_conf - reported_conf) > 0.005:
        raise SystemExit(f"artifact confidence drift: recomputed={mean_conf:.4f}, metadata={reported_conf:.4f}")

    per_class = {}
    for label, idx in label_to_idx.items():
        mask = y == idx
        n = int(mask.sum())
        per_class[label] = {
            "samples": n,
            "accuracy": round(float((pred[mask] == idx).mean()), 4) if n else None,
        }

    report = {
        "status": "PASS",
        "model_id": model.get("model_id"),
        "sha256": actual_checksum,
        "architecture": arch,
        "dataset": {
            "rows": len(rows),
            "train": len(train_rows),
            "validation": len(val_rows),
            "group_overlap": 0,
            "label_counts": dict(sorted(Counter(str(r["label"]) for r in rows).items())),
        },
        "recomputed_exported_weight_metrics": {
            "validation_accuracy": round(acc, 4),
            "mean_confidence": round(mean_conf, 4),
            "per_class": per_class,
        },
    }
    print(json.dumps(report, indent=2, sort_keys=True))


if __name__ == "__main__":
    main()
