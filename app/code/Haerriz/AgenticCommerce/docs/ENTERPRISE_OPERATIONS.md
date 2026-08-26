# Enterprise operations

> Version 5.0.0 · Reviewed 2026-08-26 · Practical procedures: [Operations runbook](OPERATIONS_RUNBOOK.md).

## Horizontal deployment

The module avoids process-local shopper authority. Conversation history, confirmations, adaptive learning, audit and idempotency are database-backed. Rate/provider circuit state uses Magento shared cache and should use the same Redis/shared-cache topology as the Commerce deployment.

## External AI egress

Production recommendations:

- keep HTTPS enforcement enabled;
- configure `AI Endpoint Host Allowlist` where enterprise egress policy permits;
- leave private-network endpoints disabled unless a controlled internal gateway is required;
- prefer an enterprise AI gateway when organization-wide billing/quotas/data-loss prevention are required;
- keep provider timeout, circuit breaker and request budget bounded;
- use OpenAI/Gemini keys stored through Magento encrypted configuration or replace provider credential resolution in an extension backed by a secrets manager.
- Network egress controls should also protect against DNS rebinding/redirect-based SSRF; the module validates configured A/AAAA targets but application validation is not a substitute for an enterprise egress firewall or AI gateway.

## Idempotency

Headless/mobile clients should provide a unique idempotency key for retry-safe mutations. For guest cart operations, the hashed masked-cart identifier anchors retry scope when available. The module never stores the raw idempotency key.

A thrown mutation is marked `uncertain` until expiry instead of becoming immediately retryable. Clients should inspect current Magento state and use a new key only when they intentionally issue a new operation.

## Cron

Ensure Magento cron is healthy. Agentic Commerce uses cron for:

- conversation retention;
- sanitized tool-audit retention;
- confirmation cleanup;
- adaptive-learning/feedback cleanup;
- durable idempotency cleanup.

All five schedules are read through `agentic_commerce/operations/*_cron` configuration paths rather than compiled `<schedule>` values. Defaults ship in `etc/config.xml`; enterprise deployments can override the global expressions through Admin/config deployment without forking module XML.

## Knowledge/cache operations

CMS knowledge follows Magento active/store assignments. Its sanitized page/block index uses the
shared Magento cache and is tagged with CMS page, CMS block, configuration and store tags; normal
Magento saves invalidate it automatically. After direct database imports, clean the relevant
configuration/block/full-page caches and validate in a new assistant turn. Persisted historical
responses are intentionally immutable.

## Observability

Core telemetry dispatches the `haerriz_agentic_telemetry` Magento event and supports `TelemetryProcessorInterface`. Attributes are bounded and sensitive-key filtered.

Recommended production dimensions include:

- `trace_id`;
- tool/action name;
- provider code;
- store ID;
- duration;
- success/failure/circuit-open/budget-exhausted state.

Do not export raw prompts, email, phone, address, tokens, cookies or payment data as telemetry.

## Database retention

The module-owned tables use the `haerriz_agentic_*` namespace. Tune retention by store and monitor growth for conversation/audit/learning data. Idempotency rows have short expiry and should remain operational metadata, not analytics storage.

## Search and catalog

Native Magento full-text/OpenSearch remains supported and is the safe fallback. Adobe Live Search/custom adapters are registry based. Validate customer-group pricing, catalog permissions and store/website scope in the actual deployment because those depend on the merchant's extension stack and indexing configuration.

## Luma / Hyvä / PWA

Business services do not depend on a theme. Luma/Hyvä compatibility code exists only at the frontend state-refresh/presentation boundary. PWA Studio/Venia and generic headless clients should use GraphQL and stable `client_id`, `conversation_id`, masked `cart_id`, plus idempotency keys for mutations.

## Deployment acceptance

A source ZIP cannot reproduce a merchant's complete Commerce runtime. Before production promotion run at minimum:

```bash
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
vendor/bin/phpunit app/code/Haerriz/AgenticCommerce/Test/Unit
```

Then execute `docs/RUNTIME_ACCEPTANCE.md` against the real database, OpenSearch/Live Search, MSI, customer groups, shipping/payment modules, Hyvä/PWA storefront and cron topology.
