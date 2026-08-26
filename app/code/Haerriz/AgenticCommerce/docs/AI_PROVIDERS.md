# AI provider architecture

> Version 5.0.0 · Reviewed 2026-08-26 · See [Architecture](ARCHITECTURE.md), [Data/security flows](DATA_AND_SECURITY_FLOWS.md) and [Knowledge grounding](KNOWLEDGE_GROUNDING.md).

The Commerce Brain works without any external API. RizAI 5.0 is a **hybrid neuro-symbolic Magento-native model**: it contains a bundled trained neural intent network plus the deterministic safety planner. It is a real neural/ML model, but not a proprietary foundation model or generative LLM. External or self-hosted generative models are optional reasoning/wording providers and never become the authority for Magento state. See [RIZAI.md](RIZAI.md) and [RIZAI_NEURAL_MODEL.md](RIZAI_NEURAL_MODEL.md).

## Magento Admin

Configure under **Stores → Configuration → Haerriz → Agentic Commerce → AI & Commerce Brain**.

Supported planning providers:

1. **RizAI — Hybrid Neural Commerce Brain** — bundled local neural model + deterministic safety kernel, no API key (`deterministic` remains the stable internal provider code).
2. **RizAI self-hosted generative model** — OpenAI-compatible endpoint for a separately trained/fine-tuned RizAI LLM; no generative weights are bundled.
3. **OpenAI Responses API** — endpoint, model and encrypted API key are merchant-owned.
4. **Google Gemini API** — generateContent endpoint, model and encrypted API key are merchant-owned.
5. **OpenAI-compatible Chat Completions** — custom/legacy compatible gateway.

An ordered **External Provider Fallbacks** multiselect may be configured. The provider manager tries the primary provider first and then each configured fallback. If none return a valid tool plan, `CompositePlanner` falls back to RizAI.

## Security and privacy

- HTTPS is required for AI endpoints by default. Insecure HTTP is an explicit development-only opt-in.
- URL-embedded endpoint credentials are rejected.
- Magento encrypted config storage is used for API keys.
- Gemini API keys are sent through `x-goog-api-key`, not query strings.
- Customer IDs, guest client IDs, quote/cart IDs, credentials, payment secrets and direct PII are excluded/redacted from external planner context.
- External response synthesis receives only the fact categories allowed by **External AI Data Scope**.
- Catalog/CMS/review text is treated as untrusted **data**, never as instructions that may override system/tool policy.
- Mutation results are never replaced by model-generated success claims. Magento tool output remains authoritative.

## OpenAI

`OpenAiResponsesProvider` uses the Responses API and Magento-generated function tools. It supports configured reasoning effort where the selected model accepts it. The core does not enable OpenAI built-in web search automatically; external web facts must never silently override store product/price/inventory/order facts.

## Gemini

`GeminiProvider` uses native function declarations through `generateContent`. Gemini 3.x may receive `thinkingLevel`; for Gemini 2.5 variants the module leaves thinking control at the model default rather than sending an incompatible `thinkingLevel` field.

## Response synthesis

When enabled, `ResponseSynthesisService` can convert privacy-filtered read-only Magento facts into more natural shopper wording. Any tool classified as state-changing bypasses external synthesis.

## Headless/Hyvä/Venia

Provider choice is server-side. Luma, Hyvä, Venia/PWA Studio, GraphQL, REST and arbitrary headless clients all consume the same Commerce Brain and Magento domain services; they do not embed provider API keys.

Storefront scope is enforced before provider creativity: unrelated arithmetic/trivia is declined without catalog tools. Deterministically locked identity/safety flows remain authoritative; non-locked ambiguous reads may use external LLM planning or the local neural model before falling back to deterministic routing.

### RizAI generative model portable tool contract

A self-hosted RizAI model may return native OpenAI-compatible `tool_calls`. If its serving stack does not implement a model-specific tool parser, it may instead return a complete strict JSON document such as `{"tools":[{"name":"search_products","arguments":{"phrase":"running shoes"}}]}`. Markdown-fenced or prose-wrapped JSON is intentionally rejected. Generated names are checked against the request's ToolPolicy-filtered tool definitions before the plan is accepted.

Offline fine-tuning utilities are under `RizAi/Generative/`. Their presence does not mean generative weights are bundled with this module.
