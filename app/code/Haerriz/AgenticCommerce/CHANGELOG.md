# Changelog

## 5.0.0

- Upgraded RizAI from a purely deterministic Commerce Brain to a **hybrid neuro-symbolic Agentic Commerce model**.
- Added the independently trained `rizai-commerce-intent-v1` feed-forward neural network with learned weights bundled in the module.
- Added pure-PHP neural inference (`FeatureHasher` + `NeuralModelRuntime`) so Magento does not require Python, Torch, a GPU or an external API at runtime.
- Added 19-way commerce-intent classification using hashed word unigrams, word bigrams and character 3/4-grams.
- Added confidence and top-2 margin gating plus a read-only `NeuralIntentPlanner`; learned weights cannot authorize mutations or bypass ToolPolicy.
- Integrated neural fallback into `CompositePlanner` after deterministic locks/proven routing memory/optional external providers and before final deterministic fallback.
- Added reproducible offline training assets and a bundled 3,624-example curated/synthetic corpus with group-isolated train/validation splits (2,904 / 720).
- Added model card and neural-model design documentation; the controlled holdout metric is explicitly not represented as a production benchmark.
- Added capability metadata for RizAI model ID/type/availability/enabled state and advertised hybrid-neuro-symbolic support.
- Added Admin controls for neural enablement, minimum confidence and minimum top-2 margin.
- Added `rizai_local_llm`, a governed OpenAI-compatible provider slot for a separately trained/self-hosted generative RizAI model. No generative LLM weights are bundled or claimed.
- Added self-hosted RizAI endpoint/model/optional-key Admin configuration with existing endpoint-policy, redaction, provider-budget and circuit-breaker protections.
- Clarified adaptive routing memory versus offline neural weight training; live shopper traffic never performs gradient updates.
- API capability version advanced to `2026-08-v5.0`.
- Version bumped to 5.0.0.

## 4.3.0

- Refactored core tool risk/category/customer/confirmation metadata into Magento DI-mergeable `ToolPolicy` metadata instead of compiled PHP decision maps.
- Refactored deterministic storefront direct actions into DI action maps, handler registries and argument sanitizer extension points.
- Added `ProviderRegistry` and `SearchAdapterRegistry` so AI/search provider choices and Admin source options are extension discovered rather than compiled allowlists.
- Added `PlannerRuleProviderInterface`, `ToolIntentGuardInterface`, `SuggestionProviderInterface`, `ToolAuthorizationProviderInterface`, `ConfirmationActionHandlerInterface`, `TelemetryProcessorInterface` and related registries for locale, vertical, B2B, fraud, geo, catalog-permission and observability extensions without a core fork.
- Added durable DB-backed idempotency reservations for retry-safe cart/wishlist/coupon/direct mutations, canonical request fingerprinting, concurrent duplicate protection and uncertain-outcome fail-closed behavior.
- Added idempotency retention cleanup and GraphQL/direct-action idempotency-key support for mutation contracts.
- Added external-provider circuit breaker, configurable cooldown/failure threshold and per-store request-budget shedding.
- Added extension-neutral telemetry events/processors with PII/secret sanitization for OpenTelemetry/Datadog/New Relic/SIEM adapters.
- Added configurable enterprise payload limits while retaining absolute defensive ceilings.
- Added store locale to planner context and pre-baseline locale/vertical rule providers; the built-in deterministic English grammar is now explicitly a replaceable baseline rule pack.
- Added namespaced headless extension payloads and documented Adobe Commerce/B2B/RMA/payment/search/provider extension patterns.
- Added enterprise hard-coding audits distinguishing configurable behavior from intentional authorization/privacy/transaction invariants.
- Added mandatory enterprise registry, resilience/idempotency/authorization and hard-coding-boundary tests to the release pipeline.
- Hardened GraphQL mutation idempotency with defense-in-depth ToolPolicy reauthorization and sanitized operation telemetry/replay events.
- Hardened direct-action retries so completed replays do not duplicate conversation turns and business-result errors release reservations safely.
- Replaced raw exception-object provider logging with bounded exception-class metadata.
- Hardened AI endpoint validation with private/reserved IPv4/IPv6 checks, best-effort DNS A/AAAA resolution and documented enterprise egress/DNS-rebinding controls.
- Moved all Agentic Commerce cleanup cron expressions behind global configuration paths with module defaults rather than compiled cron schedules.
- Updated/refactored PHPUnit fixtures for durable ResourceConnection idempotency, provider request budgets, Product Intelligence bounds and GraphQL governance.
- API capability version advanced to `2026-08-v4.3`.
- Version bumped to 4.3.0.

## 4.2.0

- Added the Magento-native **Commerce Brain** architecture: deterministic safety routing, bounded Commerce Context Graph, multi-step plan enhancement and adaptive routing memory.
- Added an executable corpus of exactly **500 common Magento/e-commerce shopper phrases** and made 500/500 coverage a mandatory release-gate stage.
- Added merchant-configurable **OpenAI Responses API**, **Google Gemini**, and OpenAI-compatible providers with encrypted Magento Admin credentials.
- Added ordered external-provider fallback chains; if external providers fail or return no valid tool plan, the Default Commerce Brain remains the final fallback.
- Added native OpenAI Responses function-tool calling and Gemini function declarations/thinking controls while keeping Magento ToolPolicy authoritative.
- Hardened Gemini credentials by moving the API key from query parameters to the `x-goog-api-key` header and limiting `thinkingLevel` overrides to compatible Gemini 3.x models.
- Added HTTPS-by-default provider endpoint policy with an explicit development-only insecure-HTTP opt-in and rejection of URL-embedded credentials.
- Added external response synthesis over privacy-filtered Magento facts; state-changing operations always retain authoritative Magento wording/results.
- Added a prompt-injection boundary that treats product descriptions, reviews, CMS text and other commerce content as untrusted data, never provider instructions.
- Added store-scoped adaptive routing patterns and shopper feedback persistence/cleanup.
- Restricted automatic learning to public read-only routes; customer-sensitive reads and mutations never self-authorize.
- Added conflict quarantine for ambiguous learned phrase→tool mappings, exact-message audit binding for feedback, and duplicate-feedback replay protection.
- Expanded privacy normalization for learned phrases to redact email, phone, URL, order identifiers and numeric values.
- Expanded connected multi-step reasoning for search→stock, search→cart, search→comparison, search→price, search→description and combined product read intents; negated dependent mutations remain blocked.
- Restored/retained native Magento CatalogSearch Fulltext collection usage for OpenSearch/Elasticsearch-backed discovery.
- Added provider-manager and multi-step standalone smoke tests plus PHPUnit coverage for provider fallback, endpoint policy, phrase normalization and multi-step plans.
- Expanded capability discovery with AI provider, external fallback chain, external-data scope, reasoning mode, adaptive-learning and response-synthesis metadata.
- Release validation expanded to ten mandatory stages.
- Version bumped to 4.2.0.

## 4.1.0

- Added a dedicated Product Intelligence API/service layer for shopper-safe descriptions, short descriptions, highlights, EAV specifications and bounded Magento media galleries.
- Added grounded product Q&A with explicit `evidence_found` / `not_stated` behavior so missing catalog evidence is not hallucinated into a negative product claim.
- Added rich multi-product comparison across description, attributes/specifications, price, inventory, categories, product options and approved review context, bounded to four products.
- Added optional use-case fit assessment (for prompts such as `which is better for pediatric training?`) based only on explicit catalog evidence and labelled as an evidence match rather than a subjective quality score.
- Added recent-product and exact-SKU planner intents for descriptions, images/media, specifications, product Q&A, review lookup and description/spec/price/stock comparisons.
- Added Product Intelligence GraphQL queries: `agenticCommerceProductContent`, `agenticCommerceProductQuestion`, `agenticCommerceProductComparison`, while retaining `agenticCommerceProductExperience`.
- Added Product Intelligence REST service-contract routes for content/intelligence, question answering and comparison.
- Added deterministic product-card details/compare flows so exact product actions do not depend on an LLM reinterpreting the SKU.
- Added PWA Studio/Venia capability advertising and documentation; Venia/headless clients consume the same GraphQL PHP core rather than a separate React-specific business implementation.
- Hardened description sanitization to remove executable/non-shopper HTML bodies before text extraction and bounded media/evidence/comparison payloads.
- Fixed Product Intelligence GraphQL resolver class escaping and added a validator preventing single-backslash resolver regressions.
- Added ProductContentService, ProductQuestionService and ProductComparisonService PHPUnit coverage plus standalone Product Intelligence smoke tests.
- Expanded deterministic planner and intrusive security/contract regressions for Product Intelligence.
- Version bumped to 4.1.0.

## 4.0.0

- Reframed the module as a governed Magento Storefront Agent Platform rather than a catalog chatbot.
- Added storefront-safe inventory/MSI salability, optional exact salable quantity exposure, low-stock/backorder/min-max/increment reasoning and batch stock comparison.
- Added configurable variant availability that resolves real Magento option labels/values to child SKUs before checking inventory and price.
- Added product-experience snapshots combining shopper-safe product data, categories, descriptions, reviews, product options, price and inventory.
- Added trusted customer-group propagation into native product search, price insight and product experience for storefront/customer-token parity.
- Expanded product option support for configurable, bundle, grouped, downloadable and native custom options; unsupported file input is routed to secure PDP UI.
- Hardened cart inventory validation and synchronized storefront quote mutations back to Magento checkout-session/minicart refresh mechanisms.
- Added checkout state machine, guest email, structured checkout addresses, saved-address selection, shipping/payment method orchestration and confirmation-gated order placement.
- Added GraphQL saved-address checkout mutations for headless customer-token clients.
- Added customer profile/address CRUD, countries/regions directory data, newsletter, reviews and product alerts with Magento ownership/configuration enforcement.
- Added payment adapter and checkout-validation provider interfaces for secure third-party/Adobe Commerce extensions.
- Added extension capability registry/tool metadata for optional B2B/RMA/store-credit/payment integrations while keeping core Magento Open Source compatible.
- Added native secure navigation for sign-in, account registration and password reset; passwords are never accepted in chat/GraphQL.
- Added a direct-action gateway for deterministic product/cart/checkout/account UI actions so buttons/forms do not round-trip through NLP.
- Expanded default-deny policy, confirmation persistence, sanitized audit and adversarial security/contract validation.
- Added intrusive GraphQL checks for customer identity, payment/authentication secrets, raw storefront SQL, inventory source privacy, customer-group context and confirmation boundaries.
- Expanded planner regressions for stock, quantity feasibility, product experience, secure auth navigation and ambiguous recent-product references.
- API capability version advanced to `2026-08-v4`.
- Version bumped to 4.0.0.

## 3.1.0

- Added dedicated Magento store-information tool for contact phone, email, address and store-hours requests.
- Fixed `contact number` and similar non-catalog prompts incorrectly falling through to broad product search.
- Fixed `compare the first two` collective-reference parsing and added a safe clarification when recent products are unavailable.
- Added server-side intent/tool compatibility checks so an external AI cannot route contact/policy/compare intents into arbitrary catalog search.
- Hardened native Magento fulltext search with an optional bounded relevance guard to prevent unrelated phrases from returning nearly the entire catalog.
- Switched native search injection to the Magento CatalogSearch Fulltext collection factory directly and removed the incompatible collection-factory DI override.
- Added context-preserving `show cheaper options` refinement.
- Added safe legacy product-name cart-command recovery against server-owned recent results; exact-SKU product-card actions remain preferred.
- Split shopper-visible product-card attributes from searchable/filterable EAV metadata and added Admin display allow-list/hidden deny-list configuration.
- Hidden common technical attributes such as enable/config/visibility/pricing-mode flags from cards by default.
- Added config-aware metadata cache keys, bounded search-term/attribute guard work, CMS action deduplication and suggestion deduplication.
- Removed the nested assistant message scrollbar and duplicate frontend `boot()` declaration; improved shown-vs-total result status.
- Expanded standalone validation to 186 structural checks, 26 planner regressions and 5 tool-policy regressions.
- Version bumped to 3.1.0.

## 3.0.0

- Added tool policy and sanitized tool audit trail with retention cron.
- Added coupons, authenticated wishlist, authenticated orders/tracking, Magento product-link recommendations and CMS knowledge tools.
- Added GraphQL/REST capabilities discovery and direct GraphQL coupon/wishlist/order operations.
- Added optional Adobe Live Search/Catalog Service adapter with native fallback.
- Added product match reasons and conversational follow-up suggestions.
- Added `haerriz:agentic:*` integration/analytics browser events.
- Expanded deterministic planner and regression suite.
- Fixed missing `ToolPolicy`, `ToolAuditLogger`, `SuggestionService`, capability service and audit cleanup wiring present in the previous working tree.
- Version bumped to 3.0.0.

## 2.0.0

- Renamed module/vendor to `Haerriz_AgenticCommerce` / `haerriz/module-agentic-commerce`.
- Added persistent server-side conversations and structured assistant message payload history.
- Added guest and authenticated-customer conversation ownership with same-client guest-history claim on login.
- Added GraphQL conversation list/detail, start/close, chat, cart read, add, remove, quantity update and clear APIs.
- Added trusted GraphQL customer-token context and storefront-only classic customer-session fallback.
- Removed client authority over previous product results; no `recent_products` GraphQL input is exposed.
- Added guarded cart read/add/remove/update/clear conversational tools.
- Conversational remove/update tools resolve visible cart positions server-side rather than accepting arbitrary quote item IDs from an LLM.
- Added exact-SKU product-card cart adds with explicit-message/SKU validation.
- Added masked guest cart enforcement, customer quote ownership and cross-store protection.
- Added safe fallback for configurable/bundle/grouped/downloadable/option-requiring products.
- Added server-owned recent product references across page navigation/reload.
- Added comparison of recently shown products by conversational position.
- Fixed count-vs-reference parsing for `show 1 ... costliest`.
- Added latest/newest product sorting via `created_at DESC`.
- Added natural-language exclusions and filter removal/refinement support.
- Added dynamic EAV/custom-attribute metadata, option-label normalization and product attribute output.
- Added search-adapter interface for a future Adobe Live Search/Product Discovery compatibility module.
- Added theme-adaptive storefront rendering and optional Admin accent override.
- Added explicit/unambiguous auto-navigation with live-turn-only browser following and a headless `auto_navigate` action flag.
- Added Hyvä `hyva_*` layout/config registration and Tailwind source registration while keeping standalone framework-neutral JS/CSS.
- Added per-store guest/customer retention settings and daily cleanup cron.
- Added external AI context redaction and registered-tool-only planner execution.
- Added history replay safety so old product/cart controls are read-only.
- Clarified `session_id` as a deprecated alias of `client_id`; `conversation_id` is the conversation identifier.
- Added Magento_Config and Magento_Webapi package/sequence dependencies used by Admin configuration and REST declarations.
- Expanded headless/testing documentation, deterministic planner smoke tests and structural validation.

## 1.0.0

- Initial conversational product discovery, EAV filters, navigation, Luma/Blank, Hyvä and GraphQL foundation.
