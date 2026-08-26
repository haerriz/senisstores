# Agentic Storefront Capability Map

> Version 5.0.0 · Reviewed 2026-08-26 · Design maps: [HLD](HIGH_LEVEL_DESIGN.md), [LLD](LOW_LEVEL_DESIGN.md), [Customer flows](CUSTOMER_FLOWS.md).

The core represents shopper-facing Magento behavior as bounded capabilities. A capability may be exposed to chat, direct UI actions, GraphQL or an extension adapter, but Magento domain APIs remain authoritative.

| Domain | Reads | Mutations / actions | Notes |
|---|---|---|---|
| Catalog | search, categories, product, product experience, custom EAV, options | navigation | Native/Adobe Live Search adapter |
| Product intelligence | descriptions, highlights, media, specifications, grounded Q&A, rich comparison, evidence-based use-case fit | details/compare direct actions | bounded shopper-safe projection; no unsupported inference |
| Inventory | salability, remaining qty (optional), requested-qty feasibility, variant availability, compare stock | stock/price alerts | MSI sales stock; no source warehouse leakage |
| Pricing | regular/final/special/tier/group-aware prices | coupon via cart | customer group from trusted identity |
| Cart | items/totals/coupon | add/remove/update/clear/apply coupon | masked guest carts; session synchronization |
| Checkout | state, missing requirements, shipping/payment methods | email/address/method selection; prepare/confirm order | payment secrets excluded |
| Customer | profile, saved addresses, countries/regions | low-risk profile/address CRUD | password/login flows stay native Magento |
| Wishlist | saved items | add/remove | customer-only |
| Orders | order list/detail, shipment tracking | none in core | owned-order checks |
| Content | CMS page/block knowledge, configured-homepage overview, contact/store profile | safe navigation | active and store-scoped; out-of-scope trivia is declined |
| Reviews | approved reviews | submit review | Magento guest-review config enforced |
| Alerts | price/stock alert eligibility | subscribe | Magento alert config enforced |
| Newsletter | subscription state | subscribe/unsubscribe | customer-only core flow |
| Recommendations | related/up-sell/cross-sell | product/card actions | adapter interface |
| Store context | store/currency/view metadata | secure navigation | future switch adapters can extend core |

## Extension targets

Adobe Commerce / third-party modules should integrate through `StorefrontCapabilityProviderInterface`, ToolPolicy metadata, payment adapters or checkout-validation providers rather than injecting raw DB/LLM access. Typical extensions: B2B companies, requisition lists, negotiable quotes, purchase orders/approvals, RMA, store credit, gift cards, rewards, custom shipping/payment methods and vendor-specific personalization.

## Direct action vs natural language

Natural language chooses *what the shopper means*. A UI control that already knows the exact SKU/option/method/address should call a direct action or GraphQL mutation. This eliminates unnecessary intent ambiguity for actions such as Add to cart, select Red/XL, choose shipping, choose payment and save an address.


## PWA Studio / Venia and headless

Venia/PWA Studio, React, Vue, Next.js and native clients consume the same GraphQL/service layer as Luma and Hyvä. The business core does not depend on React or a theme framework. Capability discovery advertises `pwa`, `pwa_studio`, `venia`, `headless`, `graphql`, `rest`, `hyva` and `luma` so clients can progressively enable supported workflows.

## Product Intelligence grounding

Descriptions and specifications come from Magento product/EAV data. Product Q&A returns explicit evidence and a `not_stated` state when the catalog does not contain support for a claim. Rich comparison is bounded to four products. Use-case fit compares explicit description/category/approved-attribute evidence and is not presented as an objective product-quality ranking.


## RizAI Commerce Brain 5.0

The core is now **hybrid neuro-symbolic**. It combines a bundled independently trained feed-forward neural intent model, deterministic Magento-native safety planning, Commerce Context Graph, persistent server conversation state, ToolPolicy, bounded multi-step enhancement and safe adaptive routing memory. The neural model performs learned statistical intent inference locally in PHP. High-confidence neural predictions may propose only planner-visible read-only tools; they cannot authorize writes or override Magento facts.

Optional OpenAI Responses, Gemini, OpenAI-compatible providers or the `rizai_local_llm` self-hosted slot can add generative reasoning/wording. Magento tools remain authoritative and the local RizAI neural model remains API-key-free. The fixed 500-phrase corpus is still release-gated, while the neural model has a separately versioned supervised training dataset and model artifact. See `RIZAI.md`, `RIZAI_NEURAL_MODEL.md`, `PHRASE_COVERAGE.md`, `AI_PROVIDERS.md` and `ADAPTIVE_LEARNING.md`.


## Enterprise extension and governance layer

Version 5.0 retains the 4.3 governance work and adds a versioned neural inference boundary. The enterprise extension layer moves extension-sensitive decisions behind Magento DI registries and policy providers. AI providers, search adapters, confirmation actions, direct-action handlers/sanitizers, planner rule packs, suggestions, authorization policy and telemetry processors can be extended without forking the core. Stable public contracts and fail-closed security invariants remain intentionally fixed.

Durable idempotency, provider circuit-breaker/request-budget controls, sanitized telemetry and configuration-driven cleanup schedules support horizontally scaled storefront deployments. See `ENTERPRISE_HARDCODING_AUDIT.md`, `ENTERPRISE_EXTENSION_GUIDE.md` and `ENTERPRISE_OPERATIONS.md`.
