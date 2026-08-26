# RizAI Generative Commerce Model training kit

This directory is the next step beyond the bundled `rizai-commerce-intent-v1` classifier. It is a **real fine-tuning pipeline for a causal transformer**, but the Magento package intentionally does **not** bundle or claim a generative checkpoint until an operator chooses a licensed base model, runs training/evaluation, versions the resulting adapter/weights and deploys them to an inference service.

## What the kit provides

- deterministic, PII-free commerce/tool-calling SFT seed data;
- strict model-neutral JSON tool-call targets;
- LoRA/optional QLoRA fine-tuning using Hugging Face Transformers + PEFT;
- an adapter merge/export utility;
- a Magento runtime provider (`rizai_local_llm`) that accepts either native OpenAI-compatible `tool_calls` or the strict JSON tool envelope;
- server-side allowlisting: generated tool names are checked against the **ToolPolicy-filtered definitions for the current request** before a plan is accepted.

## Build the seed corpus

```bash
python build_sft_dataset.py
```

The generated data is written to `data/rizai-commerce-sft-v1.jsonl`. It contains synthetic examples only; do not treat it as sufficient production training data.

## Fine-tune a chosen open-weight model

```bash
python -m venv .venv
. .venv/bin/activate
pip install -r requirements.txt
python train_lora.py \
  --base-model /path/or/model-id \
  --qlora \
  --output output/rizai-commerce-lora
```

The base model is intentionally configurable instead of hard-coded. Before training, review its license, commercial-use terms, context length, tokenizer, tool-use capability, target languages and GPU requirements.

## Merge for serving when appropriate

```bash
python merge_adapter.py \
  --adapter output/rizai-commerce-lora \
  --output output/rizai-commerce-merged
```

You can also serve the base model + adapter directly if your inference stack supports LoRA adapters.

## Runtime contract

The preferred serving API is OpenAI-compatible Chat Completions. Native `tool_calls` are accepted. If the server/model does not implement a native tool parser, RizAI can output exactly:

```json
{"tools":[{"name":"search_products","arguments":{"phrase":"running shoes"}}]}
```

No markdown fences or surrounding prose are accepted by the portable parser. Unknown tool names are discarded. `ToolPolicy`, customer identity, confirmation gates and Magento services still own authorization and truth.

## What must happen before calling this a “RizAI LLM”

1. select and document a legally usable generative base transformer;
2. build reviewed train/dev/blind-test corpora beyond the included synthetic seed set;
3. fine-tune and produce versioned adapter or merged weights;
4. evaluate tool-call precision/recall, malformed JSON, hallucination, prompt injection, PII leakage, mutation false positives, multilingual behavior and latency;
5. create a model card with base-model lineage, license and benchmark results;
6. deploy the checkpoint behind a hardened GPU inference service;
7. configure Magento's `rizai_local_llm` provider and keep deterministic fallback enabled.

When those steps are complete, it is accurate to describe the result as a **RizAI commerce-specialized fine-tuned generative language model derived from the selected base model**. It is still not accurate to call it a foundation model trained from scratch unless foundation-scale pretraining is actually performed.
