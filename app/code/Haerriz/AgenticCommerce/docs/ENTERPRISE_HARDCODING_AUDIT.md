# Enterprise hard-coding audit

> Version 5.0.0 · Reviewed 2026-08-26 · See [Knowledge grounding](KNOWLEDGE_GROUNDING.md) and [Extension guide](ENTERPRISE_EXTENSION_GUIDE.md).

This document separates **merchant/extension behavior that must be configurable** from **security and protocol invariants that must remain enforced in code**.

## Summary

Agentic Commerce 5.0 is intentionally not a database-driven rules engine that allows Admin configuration to redefine every safety boundary. Enterprise configurability is provided through Magento store configuration and DI-mergeable registries, while authorization, privacy and transaction invariants remain fail-closed.

## Removed from core PHP decision tables

| Concern | 5.0 mechanism | Enterprise extension path |
|---|---|---|
| Tool category/risk/customer/confirmation metadata | `ToolPolicy::$toolMetadata` injected from DI | Merge `toolMetadata` and/or add `ToolAuthorizationProviderInterface` |
| Direct action → tool mapping | `DirectActionService::$actionMap` + handler registry | Add `DirectActionHandlerInterface` / `DirectActionSanitizerInterface` |
| Consequential confirmation execution | `ConfirmationActionRegistry` | Add `ConfirmationActionHandlerInterface` |
| AI provider choices | `ProviderRegistry` | Register another `ProviderInterface` |
| Catalog search providers | `SearchAdapterRegistry` | Register another `SearchAdapterInterface` |
| Suggestions | `SuggestionProviderInterface[]` | Add locale/vertical/merchant suggestion provider |
| Locale/vertical deterministic planning | `PlannerRuleProviderInterface[]` | Add a locale or vertical rule pack before core English rules |
| Intent validation | `ToolIntentGuardInterface[]` | Add locale/industry intent guards |
| External policy | `ToolAuthorizationProviderInterface[]` | Company role, B2B, fraud, geo, catalog permissions |
| Observability export | `TelemetryProcessorInterface[]` | OpenTelemetry, Datadog, New Relic, SIEM |
| Extension result data | `extensions` namespaced envelope | Return bounded `extension_data` from extension tools |
| Search/product result limits | store configuration | Admin limits with defensive absolute ceilings |
| Provider endpoints/models | encrypted/store-scoped Admin configuration | OpenAI, Gemini, compatible/custom provider |
| Built-in provider display name | RizAI hybrid-neural label; stable `deterministic` code | Change presentation without breaking saved config |
| Neural intent artifact / thresholds | Versioned bundled model + store-scoped confidence/margin gates | Retrain/version a reviewed artifact; tune acceptance thresholds without weakening ToolPolicy |
| Self-hosted RizAI generative provider | `rizai_local_llm` registry entry + encrypted endpoint/model/key config | Serve a separately trained/fine-tuned transformer without embedding GPU inference into Magento PHP |
| Store identity/contact/site purpose | Magento config + scoped CMS homepage/pages/blocks | `StoreProfileProviderInterface` or managed CMS content |

Merchant facts such as addresses, definitions and site purpose must not be compiled into planner rules. Planner rules identify intent; scoped Magento content supplies evidence.

## Configurable operational behavior

The following are store scoped and do not require a core fork:

- AI provider, fallback sequence, endpoint, model and credentials;
- AI timeout, circuit breaker, failure threshold, cooldown and request budget;
- AI endpoint hostname allowlist and private-network development opt-in;
- reasoning mode and external reasoning effort;
- external-AI data scope and response synthesis;
- conversation, audit, learning and idempotency retention;
- cleanup cron expressions;
- rate limiting and maximum message size;
- exact stock-quantity exposure;
- comparison/media/specification/Q&A/suggestion/order/recommendation result limits;
- feature gates for inventory, checkout, account, wishlist, coupons, reviews, alerts, knowledge and orders;
- strict native-search relevance behavior and product attribute exposure.

## Intentionally hard invariants

These are not considered undesirable hard-coding. Making them freely configurable would weaken the security model.

1. **Unknown tools are denied.** A model cannot invent a PHP/SQL/GraphQL capability.
2. **Customer identity comes from Magento authentication context**, never a shopper-provided `customer_id`.
3. **Guest carts never accept raw numeric quote IDs** through the agent contract.
4. **Payment/authentication secrets are excluded** from agent GraphQL and external-provider facts.
5. **Consequential actions use server-owned confirmation state.** Prompts cannot waive confirmation.
6. **External AI endpoints are HTTPS/private-network restricted by default.** Development exceptions require explicit Admin opt-in.
7. **MSI source-level warehouse inventory is not exposed.** Shopper stock responses use salable storefront inventory.
8. **Adaptive learning never self-authorizes mutations.** Learning affects safe routing, not permissions.
9. **External AI never becomes the source of truth** for Magento price, inventory, cart, customer, order or checkout state.
10. **Payload limits have absolute safety ceilings** even when Admin values are higher.

## Built-in English grammar

`DeterministicPlanner` and the core intent guard contain an English baseline grammar. This is a built-in rule pack, not the enterprise localization boundary. `PlannerRuleProviderInterface` runs **before** the baseline planner, and `ToolIntentGuardInterface` is DI injected. A locale module can therefore own Tamil, German, Arabic, French or vertical-specific language without editing the core module.

The current core does not claim universal multilingual understanding. The bundled neural classifier is trained primarily on the shipped commerce-intent corpus and the deterministic baseline grammar remains English-centric. Locale/vertical rule packs, a future multilingual RizAI model, or an external/self-hosted generative provider may extend interpretation, while Magento tool authorization remains independent of language.

## Canonical API strings that remain stable

GraphQL field names, REST routes, Magento route destinations and tool names are public contracts. They are deliberately stable rather than merchant-renamable. Enterprise modules should adapt through the documented service/DI extension points instead of changing public contracts at runtime.

## Automated audit

Run:

```bash
python3 dev/enterprise_hardcoding_audit.py
```

The audit asserts that merchant/extension behavior is registry/config driven while the security invariants above remain enforced.
