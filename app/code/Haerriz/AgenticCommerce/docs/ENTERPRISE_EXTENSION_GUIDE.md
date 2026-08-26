# Enterprise extension guide

> Version 5.0.0 · Reviewed 2026-08-26 · See [Architecture](ARCHITECTURE.md) and [Low-level design](LOW_LEVEL_DESIGN.md).

Agentic Commerce is designed so Adobe Commerce/B2B, vertical and merchant modules can extend the agent without forking `Haerriz_AgenticCommerce`.

## 1. Register a new tool

Implement the normal module tool contract and add the tool to `ToolRegistry` through DI. In the same extension module, merge its ToolPolicy metadata. Unknown/unclassified tools remain denied.

Example policy metadata concept:

```xml
<type name="Haerriz\AgenticCommerce\Model\Agent\ToolPolicy">
    <arguments>
        <argument name="toolMetadata" xsi:type="array">
            <item name="get_company_credit" xsi:type="array">
                <item name="category" xsi:type="string">b2b</item>
                <item name="risk_level" xsi:type="string">sensitive_read</item>
                <item name="mutates_state" xsi:type="boolean">false</item>
                <item name="requires_customer" xsi:type="boolean">true</item>
                <item name="requires_confirmation" xsi:type="boolean">false</item>
                <item name="planner_visible" xsi:type="boolean">true</item>
                <item name="enabled" xsi:type="boolean">true</item>
                <item name="feature" xsi:type="string">b2b</item>
            </item>
        </argument>
    </arguments>
</type>
```

An enterprise module can also implement `ToolAuthorizationProviderInterface` to enforce company roles, catalog permissions, fraud/geo policy or custom ACL decisions at execution time.

## 2. Add a locale or vertical planner

Implement `PlannerRuleProviderInterface` and inject it into `CompositePlanner::$ruleProviders`.

Providers run before the built-in English deterministic rule pack. Return `null` when the provider does not own the message.

A locale planner should return normal governed tool plans rather than executing Magento operations itself.

## 3. Add an intent guard

Implement `ToolIntentGuardInterface` and merge it into `AgentService::$intentGuards`.

Use this for locale-specific mutation wording, regulated verticals or domain-specific ambiguity rules. ToolPolicy remains the final authorization boundary.

## 4. Extend the public store profile

Implement `StoreProfileProviderInterface`, then inject implementations into `StoreInformationService::$profileProviders`. Return only public, non-secret, store-scoped facts. Do not use this extension point for customer data or credentials.

## 5. Extend storefront knowledge

Prefer Magento CMS pages/blocks with correct store assignments. Add a custom knowledge adapter only when facts live in another governed repository. Preserve active/store scope, bounded text extraction and the rule that content is untrusted data rather than instructions.

## 4. Add an AI provider

Implement `Model\Ai\ProviderInterface`; implement `ResponseProviderInterface` as well if the provider can synthesize natural wording from privacy-filtered facts.

Register the provider in `ProviderRegistry` through DI with a code and Admin label. The existing Admin source models discover registered providers dynamically.

Provider implementations should use `EndpointPolicy`, merchant-encrypted configuration, bounded timeout and the provider-management fallback/circuit-breaker path.

## 5. Add a search backend

Implement `SearchAdapterInterface` and register it in `SearchAdapterRegistry`. The Admin Search Provider select is registry driven. Native Magento search remains the final fallback when configured adapters fail.

## 6. Add an exact storefront action

For buttons/forms where intent is already known, do **not** send an English sentence to an LLM.

Implement `DirectActionHandlerInterface`, or register an action → tool mapping plus `DirectActionSanitizerInterface`. This keeps Hyvä/Luma/PWA button actions deterministic and auditable.

## 7. Add a consequential action

Implement `ConfirmationActionHandlerInterface` and register it in `ConfirmationActionRegistry`.

Prepare an opaque server-side confirmation containing only validated payload. The generic GraphQL confirmation mutation and direct-action confirmation both resolve through the same registry.

This is the preferred pattern for RMA submission, B2B purchase-order submission, company-credit changes or other consequential extension workflows.

## 8. Add checkout validation

Implement `CheckoutValidationProviderInterface` for terms acceptance, age gates, B2B PO requirements, company approval, custom checkout fields or merchant business rules.

The provider contributes missing/blocked checkout requirements to the checkout state machine rather than bypassing Magento checkout.

## 9. Add a payment integration

Implement `PaymentMethodAdapterInterface`. The agent may orchestrate a selected method code and safe tokenized/provider state, but raw card numbers/CVV/passwords must stay in the payment provider's secure UI.

## 10. Add suggestions

Implement `SuggestionProviderInterface`. Providers are aggregated and deduplicated. Locale/vertical modules should provide localized suggestion chips here rather than editing core strings.

## 11. Add safe context

Implement `CommerceContextProviderInterface` to contribute bounded context such as company role, loyalty tier or extension state. Never put secrets in context; the core applies another sensitive-key sanitization boundary before provider use.

## 12. Add observability

Implement `TelemetryProcessorInterface`. Processors receive already-sanitized scalar attributes and can forward to OpenTelemetry, New Relic, Datadog or an enterprise SIEM.

## 13. Return extension-specific data

A tool may return:

```php
'extension_data' => [
    'vendor.module' => [
        'status' => 'approved',
        'reference' => '...'
    ]
]
```

Core normalizes this into the bounded `extensions` response envelope. GraphQL exposes namespace + JSON so headless clients can consume extension data without expanding the core schema for every Adobe Commerce module.

## 14. Retry-safe mutations

For retry-safe GraphQL/direct mutations, classify the tool as `idempotent=true` and accept an `idempotency_key`. The durable idempotency table stores hashes/final responses, not raw payloads. Reusing a key with a different request fingerprint is rejected.

On an exception after reservation, the key is marked **uncertain** and fails closed until expiry. This prioritizes avoiding duplicate commerce side effects over blind automatic retry.
