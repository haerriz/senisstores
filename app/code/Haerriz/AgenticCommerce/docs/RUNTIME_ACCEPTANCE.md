# Runtime Acceptance Matrix

> Version 5.0.0 · Reviewed 2026-08-26 · Execution guide: [Operations runbook](OPERATIONS_RUNBOOK.md).

Run these against the real Magento/Adobe Commerce project after source validation.

## Installation

1. `bin/magento setup:upgrade` succeeds.
2. `bin/magento setup:di:compile` succeeds.
3. Static-content deployment succeeds for the active Luma/Blank/Hyvä themes.
4. Hyvä Tailwind build includes the module source.

## Identity and conversations

1. Guest conversation survives refresh/navigation.
2. Same browser guest conversation is claimed only after that shopper signs in.
3. Customer A cannot retrieve Customer B conversation/address/order/cart.
4. Customer-token GraphQL uses the same customer group as Magento pricing.

## Search regressions

- `contact number` never returns catalog results.
- `compare the first two` uses server recent products.
- `show cheaper options` preserves the prior query.
- unknown/small-talk messages do not dump the catalog.
- custom searchable/filterable EAV works across the real catalog/search engine.

## CMS and scope regressions

- `what's the address?` resolves Store Information or active store-scoped CMS footer/contact content.
- `what is this website about?` uses the configured homepage metadata/content.
- `what's blended learning?` uses the matching active CMS page/block and returns no unrelated product cards/suggestions.
- `2+2` and unrelated trivia are declined with zero products and zero generic suggestions.
- Repeat across every store view to detect CMS assignment leakage.

## Product Intelligence

1. `describe the second product` returns the Magento short/long description for the server-owned recent product, not a catalog search.
2. Product/Page Builder HTML is rendered as readable bounded text and script/style/template/noscript content never appears in chat.
3. `show images of the first product` returns only the actual Magento media gallery and keeps payload size bounded.
4. `does this product mention <claim>?` returns evidence when explicitly present; an absent claim returns `not_stated` rather than a fabricated `No`.
5. `compare the first two based on description` compares exactly those recent products.
6. `compare description, price and stock` combines content with authoritative pricing/inventory instead of allowing one intent handler to hijack the comparison.
7. Dynamic custom EAV specifications appear only when shopper-safe exposure allows them.
8. `which one is better for <use case>?` is labelled as catalog-evidence fit and never as an objective quality score.
9. Exact-SKU Product Intelligence GraphQL and REST return the same underlying content/comparison facts.
10. Venia/PWA/headless and Luma/Hyvä return equivalent domain facts under the same store/customer-group context.

## Inventory/MSI

1. Simple in-stock/out-of-stock matches storefront salability.
2. Requested quantity feasibility respects min/max/increments/backorders.
3. Exact salable quantity appears only when Admin exposure is enabled.
4. No source/warehouse-level MSI data is shown.
5. Configurable Red/XL resolves the exact child and reports its availability, not the parent aggregate.
6. Ambiguous/missing configurable selections ask for selection instead of guessing.

## Cart/minicart

1. Add from assistant updates the actual Magento quote.
2. Guest first add persists the created quote into checkout session.
3. Luma header minicart refreshes through customer-data.
4. Hyvä private cart/customer section refreshes.
5. Configurable/bundle/grouped/downloadable/custom option buy requests match PDP behavior.
6. Insufficient requested inventory refuses mutation with a useful availability message.

## Checkout

1. Guest email/address/shipping/payment state matches native checkout.
2. Saved owned addresses can be selected in classic chat and customer-token GraphQL.
3. Another customer's address ID is rejected.
4. Payment card/secret values never enter the agent API.
5. Place order requires a confirmation token; modifying quote after preparation invalidates confirmation.
6. Real shipping/payment extension validators are honored.

## Account/post-purchase

- profile and saved-address ownership checks.
- newsletter subscription state.
- wishlist ownership.
- recent orders and exact owned order lookup.
- tracking data where shipments contain tracks.
- review guest config/moderation.
- stock/price alert feature flags.

## Failure injection

- stop OpenSearch/Adobe Live Search and confirm expected search fallback/error behavior.
- disable MSI modules and verify CatalogInventory fallback.
- expired/used confirmation token fails.
- invalid/missing AI provider falls back to deterministic planner where configured.
- unknown AI tool call is default-denied.
- cross-store/masked-cart abuse is rejected.

## Commerce Brain / provider acceptance

- Configure **RizAI**, confirm common catalog/cart/product-intelligence/CMS phrases work without an external API key.
- Configure OpenAI Responses with a staging key/model and verify an ambiguous but tool-resolvable phrase produces a governed Magento tool call.
- Configure Gemini and repeat the same test.
- Configure a primary external provider plus a valid fallback; invalidate the primary credential and confirm the fallback is used without a storefront exception.
- Invalidate all external providers and confirm RizAI remains available.
- Verify API keys do not appear in page HTML, JS config, GraphQL responses, REST responses, browser Network response payloads, conversation history or tool audit JSON.
- Keep insecure AI endpoints disabled and verify an HTTP provider URL is rejected. Enable only on an isolated development environment if testing a local proxy.

## Adaptive-learning acceptance

- Enable adaptive learning and exercise a safe public read-only alias repeatedly until the configured threshold; verify it can become an active learned route.
- Produce credible conflicting outcomes for the same normalized phrase and verify it is quarantined rather than auto-routed.
- Submit feedback twice for the same conversation/tool/message and verify duplicate replay is rejected.
- Exercise cart/order/account mutations repeatedly and verify no learned mutation route becomes active.
- Verify phone numbers, emails, URLs, order-like identifiers and quantities are normalized/redacted in learning persistence.


## 5.0 RizAI neural-model acceptance

Run these on a disposable staging store with the bundled model enabled:

- Confirm `agenticCommerceCapabilities` reports `rizai_model_id = rizai-commerce-intent-v1`, `rizai_model_type = feed_forward_neural_network`, `rizai_neural_available = true` and `rizai_neural_enabled = true`.
- Exercise representative learned-language variants for product search, store information, CMS/store policy, cart, wishlist, recent orders, checkout state, customer profile, newsletter status, product content, comparison, inventory, price and recommendations.
- Raise `neural_intent_min_confidence` above a known prediction and verify the neural route abstains and the deterministic fallback remains functional.
- Raise `neural_intent_min_margin` above a known prediction margin and verify the same safe abstention behavior.
- Ask out-of-domain questions and verify the neural `out_of_scope` class does not become a commerce tool call.
- Attempt mutation-like language and verify the neural planner never proposes a tool whose ToolPolicy metadata has `mutates_state = true`.
- Verify deterministic-locked actions remain deterministic even when a semantically similar neural prediction exists.
- Verify product-content, price, inventory, comparison and recommendation neural intents require server-owned recent-product context rather than accepting a shopper-invented index/SKU as trusted state.
- Disable the neural model in Admin and verify deterministic/external-provider functionality is unchanged.
- Corrupt/remove the model artifact only on a disposable node and verify inference fails closed with a warning and storefront planning falls back rather than throwing a customer-facing exception.
- Measure PHP-FPM request latency and memory on representative production-sized conversations before enabling the model broadly.

For the optional `rizai_local_llm` provider, verify endpoint allowlisting/private-network policy, invalid-credential/provider fallback, prompt redaction, tool-policy enforcement and response grounding exactly as for other external providers.

## Enterprise-core acceptance

Run these against the actual Magento/Adobe Commerce deployment after `setup:upgrade` and `setup:di:compile`:

- Register a small test implementation through the documented AI/search/authorization/telemetry DI extension points and confirm Magento DI merges it without a core preference rewrite.
- Send the same GraphQL/direct mutation twice with the same idempotency key and payload; verify the second call replays the first completed result without repeating the Magento mutation or conversation user turn.
- Reuse that key with a different payload and verify it is rejected.
- Force a mutation exception after execution cannot be proven; verify the idempotency record is `uncertain` rather than immediately retried.
- Fail the primary AI provider repeatedly and verify the circuit opens/fallback provider or RizAI continues serving requests.
- Exhaust the configured provider request budget and verify graceful fallback rather than an uncaught storefront failure.
- Attach a `TelemetryProcessorInterface` test processor and verify bounded operation/provider/tool events arrive without raw prompt, address, token, cookie or payment values.
- Change each Agentic Commerce cleanup cron expression through configuration deployment/Admin and verify `bin/magento cron:run` uses the configured schedules.
- Test two web nodes against the shared DB/cache: conversation, confirmation and idempotency state must remain consistent across nodes.
- Re-run catalog/customer-group/MSI/cart/checkout scenarios under the merchant's actual B2B, catalog-permission, shipping, payment, Hyvä/Venia and third-party module stack.
