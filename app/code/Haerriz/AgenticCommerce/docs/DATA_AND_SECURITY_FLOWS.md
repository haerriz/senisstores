# Data and security flows

> Version 5.0.0 · Reviewed 2026-08-26

## Data classification

| Class | Examples | External AI |
|---|---|---|
| Public storefront | Product/CMS/store profile, public price | Configurable, bounded/redacted |
| Customer commerce | Cart, wishlist, checkout state | Only when allowed; PII removed |
| Customer PII | Name, email, phone, address | Excluded from planner; direct forms/services |
| Authentication | Passwords/tokens | Never |
| Payment secrets | PAN/CVV/provider payload | Never |
| Operational secrets | AI keys, internal IDs | Never |

## External provider flow

```mermaid
flowchart TD
    CTX[Server context] --> SAFE[PromptRedactor + safeContext allow-list]
    SAFE --> AI[External provider]
    AI --> PLAN[Proposed tool plan/wording]
    PLAN --> GUARD[Intent guard]
    GUARD --> POLICY[ToolPolicy]
    POLICY --> TOOL[Magento tool]
    TOOL --> FACTS[Authoritative facts]
    FACTS --> EFP[ExternalFactPolicy]
    EFP --> SYN[Optional synthesis]
    SYN --> OUT[Shopper response]
```

## Identity trust boundary

```mermaid
flowchart LR
    C[Client request] --> OPAQUE[client_id/conversation_id/masked cart ID]
    SESSION[Magento session/token] --> ID[IdentityResolver]
    OPAQUE --> ID
    ID --> TRUST[customer/store/group/channel identity]
    CLAIM[client customer_id/group_id] -. rejected .-> ID
```

## Mutation boundary

```mermaid
flowchart TD
    INTENT[Explicit shopper intent] --> GUARD[Intent guard]
    GUARD --> AUTH[ToolPolicy/ownership]
    AUTH --> CONS{Consequential?}
    CONS -- no --> IDEM[Idempotent execution]
    CONS -- yes --> TOKEN[Bound confirmation token]
    TOKEN --> IDEM
    IDEM --> MAG[Magento mutation]
    MAG --> AUDIT[Sanitized audit + telemetry]
```

## Prompt-injection handling

Catalog names, product descriptions, reviews and CMS content are data. They can provide evidence but cannot redefine tool policy, request secrets, authorize actions or change system scope.

## Retention

Conversation messages, audit, confirmation, learning/feedback and idempotency records have separate configurable cleanup schedules. Production requires healthy Magento cron and shared cache.
