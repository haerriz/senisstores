# RizAI Generative Model Roadmap

> Version 5.0.0 · This document separates what is implemented today from what is required before RizAI can truthfully be called a generative LLM or foundation model.

## Current state

RizAI 5.0 already contains an **independently trained neural network** for commerce-intent classification. Its learned weights are bundled with the Magento module and inference is performed locally in PHP. This satisfies the definition of a trained neural/ML model, but it is not a transformer language generator.

## Why a full LLM should not execute inside Magento PHP

A useful generative LLM normally contains hundreds of millions to billions of parameters and needs optimized tensor kernels, large memory bandwidth and usually GPU inference. Loading such weights in a Magento PHP-FPM request would damage storefront latency, memory stability and horizontal scaling. The correct architecture is to keep Magento as the governed commerce/tool runtime and serve generative weights in a dedicated inference service.

## Phase G1 — RizAI Commerce Language Model

Train or fine-tune a small open-weight transformer specifically for commerce planning and tool calling. The recommended first production target is a **RizAI Commerce fine-tune**, not a claim of a foundation model trained from scratch.

Training corpus should include:

- shopper intent and paraphrase conversations;
- Magento catalog/product/price/inventory/cart/checkout tool schemas;
- safe read and mutation planning examples;
- refusal/abstention examples;
- product comparison and grounded explanation traces;
- multi-turn context-repair cases;
- multilingual merchant-specific examples;
- adversarial prompt-injection and data-exfiltration negatives.

Use supervised fine-tuning first. Preference optimization can follow only after a stable evaluation harness exists. Training data must not contain secrets, payment credentials or customer PII.

## Phase G2 — Tool-call constrained inference

Serve the model behind an OpenAI-compatible endpoint and select the module's `rizai_local_llm` provider. The generative model may propose tool calls, but `ToolPolicy`, identity, confirmation and Magento domain services stay authoritative. Consequential actions remain deterministic/confirmed.

Suggested serving stack can include vLLM, TGI or another production inference server that exposes the required compatible contract. The module intentionally does not couple the core to one GPU runtime.

## Phase G3 — Retrieval and commerce grounding

Add a governed retrieval layer for merchant content and product facts rather than attempting to bake volatile catalog data into model weights. The model should retrieve current Magento data through tools/RAG and cite/evidence it in generated explanations. Prices, stock and order state must never be treated as memorized model facts.

## Phase G4 — Evaluation and model registry

A generative RizAI release should not be promoted on qualitative demos alone. Maintain a model registry with:

- base-model lineage and license;
- training-data manifest and redaction record;
- immutable model/version ID;
- benchmark suite and safety scores;
- tool-call precision/recall;
- hallucination/unsupported-claim rate;
- mutation false-positive rate;
- latency/throughput/cost;
- rollback target and deployment checksum.

## Phase G5 — When “RizAI LLM” becomes a valid claim

The label **RizAI LLM** is valid once RizAI owns/deploys a generative transformer checkpoint that has been trained or fine-tuned for language generation, evaluated, versioned and served by the module through the governed provider boundary. If the checkpoint is derived from another open foundation model, describe it as a RizAI fine-tuned/derived LLM.

The stronger label **RizAI foundation model** should be reserved for a large general-purpose model trained from scratch or through a genuinely foundation-scale pretraining program with documented data, compute, evaluation and weights. A Magento extension by itself cannot truthfully create that status.

## Target architecture

```text
Storefront / Hyvä / PWA / Headless
             |
             v
      AgenticCommerce API
             |
             v
   CompositePlanner / Context
       |                 |
       |                 +--> local RizAI neural intent model (bundled, fast, read routing)
       |
       +--> rizai_local_llm --> dedicated GPU inference service --> RizAI generative weights
             |
             v
 ToolPolicy + Identity + Confirmation
             |
             v
 Magento commerce tools / services
             |
             v
  Catalog / Price / Stock / Cart / Orders
```

The result is intentionally **neuro-symbolic**: learned models understand and plan; deterministic governance controls authority; Magento owns transactional truth.

## Implemented developer bridge toward G1

The module now includes `RizAi/Generative/` with a deterministic SFT seed builder, LoRA/optional QLoRA training script, adapter merge utility and held-out structured-planning evaluator. The training target is a strict model-neutral JSON tool envelope so a fine-tuned model is not tied to one vendor's tool-call tokenizer. At Magento runtime, `RizAiLocalLlmProvider` still prefers native `tool_calls`, but can parse the strict JSON envelope and will accept only tool names present in the ToolPolicy-filtered definitions supplied to that request.

This is **training/integration infrastructure, not a bundled LLM checkpoint**. The “RizAI LLM” label remains reserved until actual generative weights/adapters are trained, evaluated, versioned and deployed.
