# RizAI 5.0 — Neural Core Upgrade Summary

## What is now true

RizAI 5.0 is no longer only a deterministic/rule-based commerce brain. The module contains a real, independently trained feed-forward neural network with learned weights, an offline training pipeline, a pure-PHP inference runtime and a confidence-gated planner bridge.

**Accurate classification:** RizAI 5.0 is a **hybrid neuro-symbolic Agentic Commerce model**.

**Do not claim yet:** proprietary foundation model or proprietary generative LLM.

## Neural architecture

- Input: hashed word unigrams, word bigrams, and character 3/4-grams
- Input dimension: 1,024
- Hidden layer: 96 ReLU neurons
- Output: 19-way softmax intent classifier
- Training: PyTorch / AdamW, offline only
- Runtime: pure PHP learned-weight matrix inference
- Artifact: `RizAi/Model/rizai-commerce-intent-v1.json`
- Dataset: 3,624 supervised examples (2,904 train / 720 grouped validation)

The bundled corpus is synthetic/curated. Its controlled holdout is for release regression, not a production benchmark.

## Safety design

The neural network does **not** directly mutate commerce state. It can only propose high-confidence, high-margin read-only routes. ToolPolicy, trusted identity, confirmations and Magento services remain authoritative. Deterministically locked actions always win.

## New core components

- `Model/RizAi/FeatureHasher.php`
- `Model/RizAi/NeuralModelRuntime.php`
- `Model/Planner/NeuralIntentPlanner.php`
- `RizAi/Training/build_dataset.py`
- `RizAi/Training/train.py`
- `RizAi/Training/commerce_intents.jsonl`
- `RizAi/Model/rizai-commerce-intent-v1.json`
- `RizAi/Model/MODEL_CARD.md`
- `Model/Ai/RizAiLocalLlmProvider.php`

## Generative future

The module now has a `rizai_local_llm` provider slot for a separately trained/fine-tuned generative RizAI transformer served through a dedicated OpenAI-compatible inference endpoint. This preserves Magento performance and governance instead of loading giant transformer weights in PHP-FPM.

## One-line presentation statement

> RizAI 5.0 is our Magento-native hybrid neuro-symbolic Agentic Commerce model: it combines a locally executed trained neural intent network with deterministic safety, context and Magento-authoritative commerce tools, while remaining extensible to self-hosted or external generative LLMs.

## Finalization additions

- The training corpus split is now deterministic and group-isolated by base utterance; dataset generation and training reproduce the same model checksum with seed `4305`.
- `RizAi/Training/validate_artifact.py` recomputes metrics from the exported rounded weights, validates matrix shapes, checks the SHA-256 artifact checksum and detects recorded train/validation group leakage.
- The bundled model checksum is verified before PHP inference; a missing or mismatched checksum disables the neural model and falls back safely.
- High-confidence `smalltalk` and `out_of_scope` neural predictions can now produce an assistant-only bounded response instead of falling through to broad product search.
- `RizAi/Generative/` now contains a practical LoRA/QLoRA training kit for a future commerce-specialized causal transformer. **No generative checkpoint is bundled or claimed yet.**
- `RizAiLocalLlmProvider` accepts native OpenAI-compatible tool calls or a strict portable JSON tool envelope and rejects tool names that were not in the ToolPolicy-filtered definitions supplied for that request.
