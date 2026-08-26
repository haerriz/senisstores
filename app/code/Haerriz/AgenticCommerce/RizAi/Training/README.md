# RizAI model training

This directory contains the reproducible offline training pipeline for the built-in neural intent model.

## Rebuild the current corpus

```bash
python3 build_dataset.py
```

This creates `commerce_intents.jsonl` from reviewed templates. The generated corpus contains no production customer data.

## Train/export

```bash
python3 -m venv .venv
. .venv/bin/activate
pip install -r requirements.txt
python3 train.py
```

The exported artifact is written to `../Model/rizai-commerce-intent-v1.json` and is consumed directly by the Magento PHP runtime.

## Important

Do not use live shopper feedback to update weights automatically. Export sanitized candidate observations, review/label them, retrain offline, run safety/quality evaluations, version the model, and only then deploy a new artifact.

## Validate a release artifact

```bash
python3 validate_artifact.py
```

The validator checks the model SHA-256, matrix shapes, dataset counts, group-isolated split integrity and recomputes validation metrics from the exact rounded weights consumed by PHP.

The deterministic dataset builder now records a `group_id` for each base utterance and keeps all of its polite variants in exactly one split. This reduces near-duplicate leakage between training and validation.
