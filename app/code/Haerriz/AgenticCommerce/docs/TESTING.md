# Validation and Testing

> Version 5.0.0 · Reviewed 2026-08-26 · Runtime procedures: [Runtime acceptance](RUNTIME_ACCEPTANCE.md) and [Operations runbook](OPERATIONS_RUNBOOK.md).

The module includes source/static validation that can run without a Magento installation plus PHPUnit tests intended to run inside a real Magento project.

## Package-local validation

From the module root:

```bash
bash dev/run_checks.sh
```

The current 5.0.0 enterprise package validates:

1. PHP and PHTML syntax with `php -l`.
2. Browser JavaScript syntax with `node --check`.
3. XML and JSON parsing.
4. Composer package/module/PSR-4 consistency.
5. Required Magento_Config and Magento_Webapi dependencies/sequence.
6. DI, service-contract, layout, observer and cron local class references.
7. GraphQL resolver references and basic schema structure.
8. Conversation/history/cart GraphQL surface presence.
9. GraphQL security invariants: no `customer_id` input and no client-owned `recent_products` input.
10. Declarative DB schema ↔ `db_schema_whitelist.json` consistency.
11. Guest masked-quote, customer quote ownership and cross-store protections.
12. Storefront-only customer-session fallback; GraphQL/REST identity uses trusted context.
13. External AI identity/cart context redaction.
14. Per-store guest/customer retention cleanup wiring.
15. Safe exact-SKU agent add guard.
16. Conversational cart tools use visible positions rather than arbitrary quote item IDs.
17. Persisted history controls are read-only on replay.
18. Auto-navigation is live-turn-only.
19. Old `Magento_AgenticCommerce` reference detection.
20. Windows `Zone.Identifier` artifact detection.
21. Executable deterministic-planner regressions.
22. Store-information intent isolation from product search.
23. Collective comparison (`compare the first two`) and missing-context clarification.
24. Direct CatalogSearch Fulltext collection usage and bounded strict-relevance guard.
25. Shopper-facing EAV display allow/deny separation and config-aware metadata cache.
26. CMS/suggestion deduplication, single frontend boot and no nested chat scrollbar.
27. Natural address wording routes to store information and CMS public-fact fallback.
28. Open-ended definitions route to store-scoped CMS knowledge before product search.
29. Unrelated arithmetic returns zero products and zero generic product suggestions.

The current 4.3 source release gate reports **432 structural checks**, **48 deterministic planner cases**, **23 multi-step Commerce Brain cases**, **4 Product Intelligence service cases**, **500/500 phrase coverage**, **4 provider/fallback cases**, **14 adversarial planner cases**, **5 ToolPolicy cases**, **6 enterprise registry cases**, **11 enterprise resilience/idempotency/authorization cases**, **24 enterprise hard-code boundary checks**, **21 enterprise hardcoding-audit checks**, and **212 intrusive security/contract checks**. The PHPUnit source tree contains **77 test methods**; execute those tests inside the destination Magento project.

## Planner regression cases

`dev/planner_smoke.php` currently verifies:

- `show 1 premium product course but costliest`
  - fresh product search;
  - page size 1;
  - price descending;
  - never previous-result #1.
- `latest products`
  - `created_at DESC`;
  - no meaningless fulltext phrase.
- `compare the first and third products`
  - server recent-product comparison indexes 1 and 3.
- `open the third product`
  - explicit previous-result navigation.
- `add the last shown product to my cart`
  - previous-result cart tool, last index.
- `add product SKU COURSE-001 to my cart`
  - exact-SKU add tool.
- `what's in my cart`
  - safe cart read.
- `set the second cart item quantity to 3`
  - cart item visible position + quantity.
- `clear my cart`
  - explicit destructive cart action.
- `black products under 5000 cheapest first`
  - custom option + price + sort.
- `no Puma shoes`
  - exclusion (`nin`) behavior.
- `contact number`
  - Magento store-information tool; never catalog search.
- `compare the first two`
  - indexes 1 and 2 from server recent-product context; without context it clarifies and does not search.
- `show cheaper options`
  - preserves the previous query phrase and sorts price ascending.
- legacy `add this product to cart <product name>`
  - resolves uniquely against server recent products instead of re-searching the name.
- `hello`
  - small-talk response with no commerce tool.
- `what courses do you have`
  - remains a normal catalog search (`courses`) despite the stricter intent gate.
- `show cheapest products`
  - catalog search with price ascending.

## Product Intelligence regression cases

The 4.3 planner/service suites additionally verify:

- `describe the second product` → recent-product Product Content tool, not search.
- `what is the description of CPR Manual?` → unique recent-product resolution.
- `show me images of the first product` → Product Content/media path.
- `does the second product mention pediatric use?` → grounded Product Q&A.
- missing evidence (for example an unstated waterproof claim) → `not_stated`, not a fabricated negative.
- `compare the first two based on description` → exactly two recent products.
- `compare the first two based on description, price and stock` → rich comparison without inventory/price intent hijacking.
- exact SKU comparison (`ABC-1` vs `XYZ-2`) based on specifications.
- `which one is better for pediatric training?` → bounded evidence-fit assessment with a non-subjective disclaimer.
- description sanitizer strips script/style/template/noscript bodies while preserving shopper-visible text.
- comparison payload is bounded to four products.


## Required Magento integration validation

After copying the module into a complete Magento Open Source or Adobe Commerce project:

```bash
bin/magento module:enable Haerriz_AgenticCommerce
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

In production mode also run your normal static-content deployment.

`setup:di:compile` is essential because a standalone module archive cannot reproduce the exact Magento patch-level vendor tree, enabled-module set, generated classes, plugins or third-party extensions of the destination installation.

## PHPUnit

From the Magento root:

```bash
vendor/bin/phpunit app/code/Haerriz/AgenticCommerce/Test/Unit
```

Recommended project CI additions:

```bash
vendor/bin/phpcs --standard=Magento2 app/code/Haerriz/AgenticCommerce
vendor/bin/phpstan analyse app/code/Haerriz/AgenticCommerce
```

Use the project's existing Magento coding-standard/PHPStan configuration instead of shipping a conflicting second standard with this module.

## Manual storefront regression

### Search and previous products

1. Ask `contact number`; verify store contact data is returned and **no product-search result set is produced**.
2. Ask `show 1 premium product course but costliest`; verify exactly one highest-price matching result.
3. Ask `latest products`; verify newest catalog results.
4. Ask for at least three products, then `compare the first two`; verify exactly previous result #1 and #2 are compared.
5. Start a fresh conversation and ask `compare the first two`; verify a clarification rather than a broad catalog search.
6. After a product search ask `show cheaper options`; verify the original phrase/filters remain and sorting becomes price ascending.
7. Ask an unrelated non-commerce sentence; verify it does not silently dump the whole catalog.
8. Ask `what courses do you have`; verify normal product discovery still works under strict routing.
9. Navigate away, refresh/resume, then ask `open the third product`; verify server-side reference survives.
10. Apply normal PLP filters and then refine in chat; verify the event/GraphQL state can stay synchronized.
11. Verify technical EAV fields such as enable/config/visibility/pricing-mode attributes are not rendered on product cards unless explicitly allow-listed.
12. Verify CMS/action suggestions do not contain duplicate destinations and long product replies use page scrolling rather than an internal fixed-height chat scroller.

### Cart

Test all of these as both a guest and logged-in shopper where applicable:

- Search → `add the first shown product to my cart`.
- Current product card → Add to cart; verify exact-SKU route, not a stale ordinal.
- `what's in my cart`.
- `remove the first item from my cart`.
- `set the second cart item quantity to 3`.
- `clear my cart`.
- Configurable/bundle product → returns requires-options/PDP action instead of unsafe direct add.
- Historical/replayed product/cart controls do not mutate the current cart.

For headless guest carts additionally verify:

- masked cart ID succeeds;
- numeric quote ID is rejected;
- customer-owned masked quote is rejected;
- quote from another store view is rejected.

### Customer login and ownership

- Guest A cannot retrieve Guest B history with a different high-entropy `client_id`.
- Customer A cannot retrieve Customer B conversation public ID.
- Same anonymous browser logs into Customer A and keeps its `client_id`: those guest conversations become Customer A history.
- After claim, anonymous requests using that client ID cannot retrieve the now customer-owned conversations.
- Logout does not expose customer-owned history to the anonymous session.

### GraphQL authentication

1. Generate a normal Magento customer token.
2. Send `Authorization: Bearer TOKEN`.
3. Call `agenticCommerceChat`; `viewer.is_customer` must be true.
4. Verify no GraphQL Agentic Commerce input accepts `customer_id`.
5. Verify `session_id`, if used for legacy compatibility, behaves as an alias of `client_id`, while `conversation_id` is separate.

### Conversation history

- Refresh and resume the active conversation.
- Product/filter/cart payloads from earlier assistant messages reconstruct correctly.
- Start a new conversation without deleting older history.
- History orders by `last_message_at DESC`.
- Close a conversation; a new chat must not silently reopen it.

### Navigation

With automatic navigation enabled:

- `open checkout` follows the live explicit action.
- unique CMS/category match can follow automatically.
- ambiguous CMS/category results remain selectable choices.
- loading old conversation history never triggers navigation.

Disable automatic navigation and confirm the same actions render without redirecting.

### Theme compatibility

Test at least:

- Luma/default theme;
- light custom Blank-derived theme;
- dark custom theme;
- Hyvä store view;
- custom `.action.primary` color;
- Admin explicit accent override.

The component should derive storefront colors instead of forcing the original dark screenshot styling.

### Dynamic custom attributes

Create representative attributes:

- select;
- multiselect;
- boolean;
- searchable text.

Set appropriate storefront flags and verify metadata output, option-label natural-language matching, product-card custom attributes and filter refinement.

## Retention cron

Confirm Magento cron is enabled. In a disposable environment temporarily use small retention values, then run:

```bash
bin/magento cron:run --group=default
```

The job is `haerriz_agentic_commerce_cleanup` and executes per store view, using separate guest/customer retention windows. Verify deleting a conversation cascades its message rows.

## Database inspection

After `setup:upgrade`:

```sql
SHOW TABLES LIKE 'haerriz_agentic_%';
DESCRIBE haerriz_agentic_conversation;
DESCRIBE haerriz_agentic_message;
DESCRIBE haerriz_agentic_tool_audit;
```

Do not manually change conversation ownership/customer IDs outside a disposable test database.


## Agentic 3.0 regression matrix

In addition to the original catalog/cart/history cases, verify:

- `apply coupon SAVE10` and `remove the coupon` against a real configured sales rule.
- Guest coupon operations with a valid masked cart ID and rejection of numeric quote IDs.
- Customer-token wishlist list/add/remove; guest requests must be rejected.
- Customer-token recent orders and exact order lookup; another customer's increment ID must not resolve.
- Related/up-sell/cross-sell recommendations from a product with configured links.
- CMS knowledge questions return active current-store/All Store Views pages and reusable blocks,
  with exact visible headings/link labels ranked ahead of unrelated content.
- A footer link using `{{store direct_url=...}}` resolves to a safe storefront URL without executing
  a widget/block directive; hidden text and executable URL schemes are excluded.
- `answer_store_question` and `search_pages` keep authoritative Magento wording even when external
  response synthesis is enabled.
- Tool audit rows contain hashed anonymous client ID and sanitized arguments, never raw cart/client IDs.
- `haerriz_agentic_commerce_audit_cleanup` removes audit rows older than configured retention.
- Adobe Live Search provider uses SaaS results when configured and falls back to native search on a forced endpoint error.
- `haerriz:agentic:*` browser events fire without requiring jQuery/RequireJS/Knockout.
- Hyvä and Luma product-card controls are read-only when replaying historical messages.

Runtime Magento integration remains required for `setup:upgrade`, `setup:di:compile`, GraphQL schema compilation, sales-rule totals, wishlist persistence and order authorization because those require a full Magento installation/database.


## Commerce Brain / provider runtime matrix

On a disposable staging store, verify each configured provider independently and then verify the configured fallback sequence by temporarily supplying an invalid primary credential. The expected behavior is a valid fallback provider plan or, if no external provider is available, a safe local RizAI plan (deterministic and/or high-confidence neural read route)—not a storefront error.

Also test adaptive learning with a safe public read-only alias, conflicting aliases, duplicate shopper feedback, and a mutation phrase. Mutation phrases must never auto-activate through learning.


## 5.0 enterprise regression stages

The mandatory `dev/run_checks.sh` release gate also verifies:

- DI-mergeable AI/search/confirmation registries accept extension providers without core edits;
- ToolPolicy can be extended with authorization providers while unknown tools remain fail-closed;
- durable idempotency is identity scoped, hashes raw retry keys, replays completed results, rejects key reuse with a different payload and marks uncertain failures safely;
- GraphQL mutations reassert ToolPolicy inside the idempotent executor and emit bounded telemetry;
- provider circuit breaker/request budgets fail over rather than taking down the storefront;
- direct-action replays do not duplicate conversation turns;
- provider/core logs do not serialize raw exception objects;
- cleanup cron schedules are configuration driven;
- enterprise hard-coding audits distinguish configurable extension decisions from intentional security/protocol invariants.
