# Haerriz_AgenticCommerce 5.0.0

A governed Agentic Commerce platform for Magento Open Source and Adobe Commerce. **5.0 introduces RizAI as a real hybrid neuro-symbolic commerce model**: the existing deterministic Magento-native reasoning/safety kernel is now augmented by a bundled, independently trained neural intent network with learned weights and pure-PHP inference. External OpenAI/Gemini/self-hosted LLMs remain optional.

> This is a community/custom module. It is not an Adobe or Magento core module. The agent is never granted arbitrary SQL, arbitrary GraphQL, raw Magento database-table access, or unrestricted ObjectManager access. Magento service contracts, repositories, catalog/search/quote/customer APIs and bounded compatibility adapters remain authoritative.

## What changed in 5.0

RizAI 5.0 is no longer only a rule engine. The package now contains `RizAi/Model/rizai-commerce-intent-v1.json`, a trained feed-forward neural network that classifies commerce intents from hashed word/bigram/character-ngram features. Magento executes the learned weights locally through `NeuralModelRuntime`; Python/Torch are needed only to retrain a future model release, not to serve the bundled model.

The neural model is deliberately **not** allowed to authorize writes. High-confidence neural predictions may propose bounded read-only tools; `ToolPolicy`, identity checks, confirmation boundaries and Magento domain services still decide what is allowed and what is true. Deterministically locked operations remain owned by the safety kernel.

This makes RizAI accurately describable as a **hybrid neuro-symbolic Agentic Commerce model**. It is an independently trained machine-learning/neural model plus symbolic planning and governance. It is **not yet a proprietary foundation model or generative LLM**. An optional `rizai_local_llm` provider is included so a separately trained/fine-tuned RizAI generative model can be served through an OpenAI-compatible inference endpoint later.

## Core design rule

```text
Shopper language / explicit UI action
              ↓
    deterministic safety grammar
              ↓
 optional LLM / RizAI neural intent model
              ↓
          ToolPolicy
       (default deny)
              ↓
      Magento domain service
              ↓
 repository/service contract/quote API
              ↓
         Magento data
```

The goal is **"storefront data becomes agent-usable"**, not "the model gets database access". This preserves store scope, customer groups, plugins, extension attributes, MSI, catalog rules, tax, quote validation, payment/shipping integrations and third-party module behavior.

## 5.0 RizAI neural core

The local model architecture is intentionally small enough to execute inside a Magento PHP process while still being a genuine learned-weight neural network:

- 1,024-dimensional hashed word/bigram/character-ngram input;
- 96-neuron ReLU hidden layer;
- 19 commerce-intent softmax classes;
- 2,904 curated/synthetic training examples and 720 group-isolated holdout examples (3,624 total) in the bundled release corpus;
- confidence and top-2 margin gates before any neural route is accepted;
- read-only neural routing only;
- deterministic fallback for unsupported/unsafe cases;
- reproducible offline training pipeline under `RizAi/Training/`.

The bundled grouped holdout set reaches 90.56% classification accuracy with 91.81% mean confidence using the exported weights. That corpus is still controlled synthetic/curated data and **must not be represented as a production benchmark**. Real-world multilingual, merchant-specific and adversarial evaluation is still required.

## 4.3 enterprise safety kernel retained

The 4.3 enterprise hardening remains the safety foundation: tool metadata, AI/search providers, direct actions, confirmation handlers, suggestions, locale/vertical planning, authorization policy and telemetry processors are DI-mergeable. Provider endpoints/models, resilience controls, payload limits and operational retention are store-configurable. Unknown tools, client-controlled customer identity, raw quote IDs, payment secrets, prompt-overridden confirmations, MSI source inventory exposure and learned mutation authority remain fail-closed in code.

Enterprise runtime foundations include durable database-backed idempotency for retry-safe storefront mutations, provider circuit breaking/request budgets, extension-neutral telemetry events/processors, namespaced extension payloads, confirmation/action registries, and authorization providers for B2B/company/fraud/geo/catalog-permission policy.

## Planning stack

Merchant-configurable AI providers are under **Stores → Configuration → Haerriz → Agentic Commerce → AI & Commerce Brain**:

- **RizAI Hybrid Neural Commerce Brain** — bundled learned-weight neural intent model + deterministic safety planner, no external API key (`deterministic` remains the stable internal provider code);
- **RizAI self-hosted generative model** — optional OpenAI-compatible endpoint for a separately trained/fine-tuned RizAI LLM;
- OpenAI Responses API;
- Google Gemini generateContent;
- OpenAI-compatible/custom gateways.

The planner order is intentionally governed: extension rule packs → deterministic locked intent → proven safe adaptive alias → optional external model → local neural intent fallback → deterministic fallback. External or neural intelligence proposes; ToolPolicy authorizes; Magento supplies authoritative commerce state.

### Adaptive learning versus neural training

Adaptive routing memory still does not rewrite model weights online. Runtime feedback remains privacy-normalized and bounded. Neural model releases are retrained **offline** from reviewed corpora using `RizAi/Training/train.py`, evaluated, versioned and then deployed as model artifacts. This prevents a shopper from poisoning live model weights or teaching the agent new permissions.

See `docs/RIZAI.md`, `docs/RIZAI_NEURAL_MODEL.md`, `docs/AI_PROVIDERS.md` and `docs/ADAPTIVE_LEARNING.md`.

## 4.1 Product Intelligence

Product information is now a first-class agentic domain rather than a side-effect of product search.

Supported requests include:

- `Describe the second product.`
- `Summarize this product description.`
- `What are its features/specifications?`
- `Show the images for the first product.`
- `Does this product mention pediatric use?`
- `Compare the first two based on description.`
- `Compare description, price, stock, reviews and specifications.`
- `What are the similarities and differences?`
- `Which one is a better catalog match for pediatric training?`

The Product Intelligence layer exposes bounded, shopper-safe data from Magento product repositories/EAV/media and composes it with price, inventory, categories, options and approved reviews. Product Q&A distinguishes **evidence found** from **not stated** so absence of catalog evidence is not turned into an invented `No` answer.

Rich comparisons are bounded to four products and can compare:

- short and long descriptions;
- dynamic shopper-approved EAV specifications;
- price/discount/tier context;
- inventory and requested-quantity availability;
- categories;
- configurable/bundle/grouped/downloadable/custom-option structure;
- approved reviews;
- similarities/differences;
- optional use-case fit grounded in explicit catalog evidence.

Use-case fit is labelled as an evidence match, **not a subjective quality score**.

## Major capabilities

- **RizAI** is the built-in, API-key-free hybrid neuro-symbolic Commerce Brain: a bundled trained neural intent network plus deterministic planning, policy and Magento-grounded tools. It is a real neural/ML model, but not a proprietary foundation model or generative LLM. See [docs/RIZAI.md](docs/RIZAI.md).
- Storefront scope enforcement declines unrelated general knowledge/arithmetic without performing catalog searches or returning stale product cards.
- Store knowledge is grounded in store-scoped Magento configuration, the configured CMS homepage, enabled CMS pages and reusable CMS blocks such as contact/footer content.

### Product discovery and catalog understanding

- Natural-language product discovery, sorting, exclusions and iterative refinements.
- Dynamic custom EAV attribute discovery/filtering; shopper-display attributes are isolated from technical EAV metadata.
- Native Magento CatalogSearch/OpenSearch integration with optional bounded relevance guard.
- Optional Adobe Live Search/Catalog Service adapter with automatic native fallback.
- Category/catalog navigation, CMS/store-policy knowledge, store contact information and product recommendations.
- Recent-result references such as `the second one`, server-owned conversational context and persistent history.
- Product Content, Product Q&A, media gallery and Rich Comparison services introduced in 4.1.
- Rich product experience snapshot: product data, descriptions, categories, reviews, price, inventory and options under one trusted context.

### Inventory and availability

- `is this in stock?`, `how many are left?`, `can I buy 5?`, multi-product stock comparison.
- MSI storefront salability when InventorySalesApi is available, CatalogInventory fallback otherwise.
- Admin-controlled exposure of storefront-safe salable quantity.
- Minimum/maximum sale quantity, quantity increments and backorder awareness.
- Low-stock messaging when exact quantity exposure is enabled.
- Configurable-product variant availability such as `Is Red / XL available?` resolves to the matching child SKU first.
- Variant candidates include customer-group-aware price and storefront-safe inventory.
- MSI source/warehouse quantities and `source_code` are never exposed.

### Pricing

- Regular/final/special price explanation.
- Discount amount and percentage.
- Tier price data.
- Trusted customer-group context for storefront sessions and customer-token GraphQL clients.

### Cart

- Read cart, exact-SKU add, recent-product add, remove, update quantity, clear and coupon lifecycle.
- Simple/configurable/bundle/grouped/downloadable/native custom-option orchestration.
- Requested stock quantity validated before mutation.
- Product-card/direct actions bypass NLP and execute exact structured operations.
- Storefront quote state synchronizes with Magento Checkout Session and refreshes Luma customer-data / Hyvä private content.
- Guest headless carts require masked cart IDs; customer quote ownership is server-resolved.

### Checkout orchestration

- Checkout state and missing-requirement model.
- Guest checkout email.
- Structured shipping/billing addresses and authenticated saved-address selection.
- Shipping-method and payment-method discovery/selection.
- Payment secrets are not accepted in chat/GraphQL; provider adapters own secure payment details.
- Checkout validation-provider interface for terms, B2B/custom fields and extension requirements.
- Place-order uses an expiring confirmation token plus quote-state fingerprint; there is no direct public `placeOrder()` resolver.

### Customer, wishlist and post-purchase

- Authenticated profile read/update for low-risk fields.
- Saved-address list/create/update/delete with ownership validation.
- Countries/regions directory API.
- Native secure navigation for login, registration and password reset; passwords are never collected in chat.
- Newsletter subscription state and mutations.
- Wishlist list/add/remove.
- Recent orders, exact owned-order lookup and shipment tracking metadata.
- Product reviews/read/submission respecting Magento review configuration.
- Price/stock alerts respecting Magento product-alert configuration.

### Conversation and agent infrastructure

- Persistent server-side conversations and structured turn payloads.
- Anonymous guest ownership plus safe same-browser guest→customer conversation claim.
- Conversation/tool-audit/confirmation retention cron.
- Sanitized tool audit trail.
- Capability manifest for storefront/headless clients.
- Optional external OpenAI-compatible planner plus deterministic fallback.
- Restricted planner tool manifest and execution-time ToolPolicy authorization.
- Direct-action gateway for exact buttons/forms.
- Extension capability registry for Adobe Commerce/B2B/RMA/store-credit/payment/third-party adapters.

## API surfaces

### GraphQL

The same domain services are exposed to PWA Studio/Venia and generic headless storefronts. Major queries/mutations include catalog discovery, conversations, product content/Q&A/comparison/experience, inventory/variant availability, cart/coupons, checkout, customer addresses, wishlist, orders, reviews, alerts and capability discovery. `agenticCommerceStoreProfile` exposes the same store-scoped assistant identity, merchant identity, contact data, URL and supported channels used by both the built-in planner and external AI providers.

Product Intelligence queries include:

```text
agenticCommerceProductContent
agenticCommerceProductQuestion
agenticCommerceProductComparison
agenticCommerceProductExperience
```

### REST

Product Intelligence also exposes REST service-contract routes:

```text
GET  /V1/agentic-commerce/product/:sku/intelligence
POST /V1/agentic-commerce/product/question
POST /V1/agentic-commerce/product/compare
```

Other REST/controller surfaces continue to share the same PHP services and policy layer.

`GET /V1/agentic-commerce/store-profile` is the theme-independent identity endpoint for Hyvä, PWA, mobile and other headless clients. A module may inject `StoreProfileProviderInterface` implementations into `StoreInformationService::profileProviders` through DI to contribute additional public, non-secret profile facts without editing Agentic Commerce core.

## Storefront compatibility

| Surface | Strategy |
|---|---|
| Magento Blank/Luma | Framework-neutral assistant + optional `customer-data` refresh bridge |
| Hyvä | `hyva_*` layout/template hooks, Tailwind source registration, private-content refresh bridge |
| Magento PWA Studio / Venia | GraphQL-first integration through Apollo; capability discovery and client-owned presentation state |
| Generic headless React/Vue/Next/native | Same GraphQL/REST/domain services; no dependency on bundled PHTML |
| Adobe Live Search | Optional search adapter with native Magento fallback |
| External AI | Restricted planner/tool manifest; Magento remains the commerce authority |

The PHP business core has no jQuery, Knockout, RequireJS, Hyvä-theme or Venia/React dependency. Compatibility-specific code is isolated to presentation/adapters.

## Product Intelligence safety

- Descriptions are sanitized and bounded before entering conversational output.
- `<script>`, `<style>`, `<template>` and `<noscript>` bodies are removed from description text.
- Media-gallery output is bounded.
- Shopper specifications come only from approved/storefront-safe EAV metadata.
- Product Q&A returns an explicit `not_stated` state when evidence is absent.
- Product comparisons are bounded to four products.
- Use-case comparison is evidence-labelled and not presented as an objective quality score.
- Price, inventory and order/cart facts remain authoritative Magento-service results rather than model-generated facts.

## Security boundaries

- No client-supplied `customer_id`, `customer_group_id`, numeric quote ID, recent-product authority, MSI stock ID or source code.
- No passwords, card numbers, CVV/CVC/PAN or payment secrets in Agentic Commerce GraphQL.
- No raw SQL against Magento storefront tables.
- `ResourceConnection` is restricted to this module's persistence tables.
- Unknown tools are default-deny.
- Customer-only tools are hidden from guest planner manifests and re-authorized server-side.
- Order placement and destructive account operations are confirmation-gated.
- Historical conversation replay is read-only for old mutation controls.

## Installation

Place the module at:

```text
app/code/Haerriz/AgenticCommerce
```

Then run:

```bash
bin/magento module:enable Haerriz_AgenticCommerce
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

Production/static assets:

```bash
bin/magento setup:static-content:deploy -f
```

For Hyvä, run the normal theme Tailwind build used by the project.

## Admin configuration

`Stores → Configuration → Haerriz → Agentic Commerce`

Key controls include feature gates, AI provider, search provider, strict native-search relevance, EAV exposure, history retention, rate limits, inventory quantity exposure/low-stock threshold and optional Adobe Live Search credentials.

## Design documentation

Start with [docs/README.md](docs/README.md). The module includes separate [high-level design](docs/HIGH_LEVEL_DESIGN.md), [architecture](docs/ARCHITECTURE.md), [low-level design](docs/LOW_LEVEL_DESIGN.md), [customer flows](docs/CUSTOMER_FLOWS.md), [knowledge grounding](docs/KNOWLEDGE_GROUNDING.md), [API/channel design](docs/API_AND_CHANNELS.md), [data/security flows](docs/DATA_AND_SECURITY_FLOWS.md), and an [operations runbook](docs/OPERATIONS_RUNBOOK.md). Mermaid diagrams are kept in source for Bitbucket review and version control.

## Validation

Run standalone checks from the module root:

```bash
bash dev/run_checks.sh
```

The source also ships PHPUnit tests for Magento-project execution and a dedicated Product Intelligence standalone smoke suite. See `VALIDATION.md`, `docs/TESTING.md`, `docs/RUNTIME_ACCEPTANCE.md`, `docs/SECURITY.md`, `docs/HEADLESS.md` and `docs/AGENTIC_CAPABILITIES.md`.

A complete Magento project is still required for `setup:upgrade`, `setup:di:compile`, Magento PHPUnit/integration tests, OpenSearch/Live Search behavior, Hyvä compilation and real payment/shipping/MSI runtime verification.
