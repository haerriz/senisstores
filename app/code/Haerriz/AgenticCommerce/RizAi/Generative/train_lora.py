#!/usr/bin/env python3
"""Fine-tune a configurable open-weight causal language model as a RizAI commerce planner.

This script intentionally does not hard-code a foundation model. The operator must choose a base
checkpoint whose license, language coverage, size and serving stack are acceptable for the merchant.
It supports ordinary LoRA and optional 4-bit QLoRA when bitsandbytes/CUDA are available.
"""
from __future__ import annotations

import argparse
import json
from pathlib import Path

import torch
from torch.utils.data import Dataset
from transformers import (
    AutoModelForCausalLM,
    AutoTokenizer,
    BitsAndBytesConfig,
    DataCollatorForSeq2Seq,
    Trainer,
    TrainingArguments,
)
from peft import LoraConfig, get_peft_model, prepare_model_for_kbit_training


class ChatSftDataset(Dataset):
    def __init__(self, path: Path, tokenizer, max_length: int, split: str = "train"):
        self.items = []
        for line in path.read_text(encoding="utf-8").splitlines():
            if not line.strip():
                continue
            row = json.loads(line)
            if split and str(row.get("meta", {}).get("split", "train")) != split:
                continue
            messages = row["messages"]
            if len(messages) < 2 or messages[-1].get("role") != "assistant":
                continue
            prompt_messages = messages[:-1]
            answer = str(messages[-1]["content"])
            if hasattr(tokenizer, "apply_chat_template") and tokenizer.chat_template:
                prompt = tokenizer.apply_chat_template(prompt_messages, tokenize=False, add_generation_prompt=True)
                full = prompt + answer + (tokenizer.eos_token or "")
            else:
                prompt = "\n".join(f"{m['role'].upper()}: {m['content']}" for m in prompt_messages) + "\nASSISTANT: "
                full = prompt + answer + (tokenizer.eos_token or "")
            prompt_ids = tokenizer(prompt, add_special_tokens=False)["input_ids"]
            encoded = tokenizer(full, add_special_tokens=False, truncation=True, max_length=max_length)
            input_ids = encoded["input_ids"]
            attention_mask = encoded["attention_mask"]
            labels = input_ids.copy()
            prompt_len = min(len(prompt_ids), len(labels))
            labels[:prompt_len] = [-100] * prompt_len
            self.items.append({"input_ids": input_ids, "attention_mask": attention_mask, "labels": labels})

    def __len__(self):
        return len(self.items)

    def __getitem__(self, index):
        return self.items[index]


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--base-model", required=True, help="Open-weight causal LM checkpoint or local path")
    ap.add_argument("--data", type=Path, default=Path(__file__).with_name("data") / "rizai-commerce-sft-v1.jsonl")
    ap.add_argument("--output", type=Path, default=Path(__file__).with_name("output") / "rizai-commerce-lora")
    ap.add_argument("--epochs", type=float, default=3.0)
    ap.add_argument("--max-length", type=int, default=2048)
    ap.add_argument("--learning-rate", type=float, default=2e-4)
    ap.add_argument("--batch-size", type=int, default=1)
    ap.add_argument("--grad-accum", type=int, default=16)
    ap.add_argument("--qlora", action="store_true", help="Load base model in 4-bit NF4; requires CUDA + bitsandbytes")
    ap.add_argument("--trust-remote-code", action="store_true")
    args = ap.parse_args()

    tokenizer = AutoTokenizer.from_pretrained(args.base_model, trust_remote_code=args.trust_remote_code)
    if tokenizer.pad_token_id is None:
        tokenizer.pad_token = tokenizer.eos_token

    quant = None
    if args.qlora:
        quant = BitsAndBytesConfig(
            load_in_4bit=True,
            bnb_4bit_quant_type="nf4",
            bnb_4bit_use_double_quant=True,
            bnb_4bit_compute_dtype=torch.bfloat16 if torch.cuda.is_available() else torch.float16,
        )
    model = AutoModelForCausalLM.from_pretrained(
        args.base_model,
        trust_remote_code=args.trust_remote_code,
        quantization_config=quant,
        device_map="auto" if args.qlora else None,
        torch_dtype="auto",
    )
    if args.qlora:
        model = prepare_model_for_kbit_training(model)

    lora = LoraConfig(
        r=16,
        lora_alpha=32,
        lora_dropout=0.05,
        bias="none",
        task_type="CAUSAL_LM",
        target_modules="all-linear",
    )
    model = get_peft_model(model, lora)
    dataset = ChatSftDataset(args.data, tokenizer, args.max_length, split="train")
    if not dataset:
        raise SystemExit("training dataset is empty")

    args.output.mkdir(parents=True, exist_ok=True)
    training_args = TrainingArguments(
        output_dir=str(args.output),
        num_train_epochs=args.epochs,
        learning_rate=args.learning_rate,
        per_device_train_batch_size=args.batch_size,
        gradient_accumulation_steps=args.grad_accum,
        warmup_ratio=0.05,
        weight_decay=0.01,
        logging_steps=10,
        save_strategy="epoch",
        bf16=torch.cuda.is_available() and torch.cuda.is_bf16_supported(),
        fp16=torch.cuda.is_available() and not torch.cuda.is_bf16_supported(),
        report_to=[],
        remove_unused_columns=False,
    )
    collator = DataCollatorForSeq2Seq(tokenizer=tokenizer, padding=True, label_pad_token_id=-100, return_tensors="pt")
    trainer = Trainer(model=model, args=training_args, train_dataset=dataset, data_collator=collator)
    trainer.train()
    trainer.save_model(str(args.output))
    tokenizer.save_pretrained(str(args.output))
    manifest = {
        "artifact_type": "rizai_commerce_lora_adapter",
        "base_model": args.base_model,
        "dataset": str(args.data),
        "examples": len(dataset),
        "portable_tool_contract": {"tools": [{"name": "tool_name", "arguments": {}}]},
    }
    (args.output / "rizai_training_manifest.json").write_text(json.dumps(manifest, indent=2), encoding="utf-8")
    print(json.dumps(manifest, indent=2))


if __name__ == "__main__":
    main()
