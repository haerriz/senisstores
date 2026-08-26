# High-level design

> Version 5.0.0 · Reviewed 2026-08-26

## Objective

Provide one governed conversational-commerce core across Luma, Hyvä, PWA Studio, mobile and headless storefronts. It must discover products, explain Magento-managed content and perform supported commerce actions without allowing an AI provider to become the source of truth.

## System context

```mermaid
C4Context
    title Agentic Commerce system context
    Person(shopper, "Shopper", "Guest or authenticated customer")
    System(agent, "Agentic Commerce", "RizAI hybrid neural/symbolic brain + governed tools + APIs")
    System_Ext(ai, "Optional generative model", "OpenAI, Gemini, RizAI self-hosted LLM or compatible gateway")
    System(magento, "Magento / Adobe Commerce", "Catalog, CMS, customer, quote, order, configuration")
    System_Ext(search, "Optional search service", "Adobe Live Search / Catalog Service")
    Rel(shopper, agent, "Chats and invokes exact actions")
    Rel(agent, magento, "Service contracts, repositories, scoped collections")
    Rel(agent, ai, "Redacted context and function-tool plans")
    Rel(agent, search, "Bounded catalog queries")
```

## Core capabilities

- RizAI hybrid neuro-symbolic planning: learned neural intent model + deterministic safety kernel + safe external-provider fallback.
- Store-scoped catalog, price, stock, CMS, store-profile and navigation knowledge.
- Cart, coupon, wishlist, checkout, account, order, review, newsletter and alert tools.
- Persistent conversations, confirmation gates, idempotency, audit and telemetry.
- REST, GraphQL and theme-neutral domain services.
- DI registries for providers, tools, policies, planners, suggestions and extension data.

## Logical layers

```mermaid
flowchart TB
    subgraph Channels
        L[Luma/Blank]
        H[Hyvä]
        P[PWA/Venia]
        M[Mobile/headless]
    end
    subgraph Edge
        FC[Frontend controllers]
        G[GraphQL resolvers]
        R[REST service contracts]
        DA[Direct-action gateway]
    end
    subgraph Brain
        CP[CompositePlanner]
        RZ[Deterministic safety planner]
        NN[RizAI neural intent model]
        EP[External/self-hosted generative providers]
        CG[Commerce Context Graph]
        AL[Adaptive routing memory]
    end
    subgraph Governance
        IG[Intent guards]
        TP[ToolPolicy]
        CF[Confirmation]
        ID[Trusted identity]
    end
    subgraph Domains
        CAT[Catalog/Product]
        K[CMS/Store knowledge]
        CART[Cart/Checkout]
        CUST[Customer/Orders]
    end
    Channels --> Edge --> Brain --> Governance --> Domains
    Domains --> MAG[(Magento)]
```

## Quality attributes

| Attribute | Design response |
|---|---|
| Security | Default-deny tools, trusted identity, redaction, confirmation and endpoint policy |
| Accuracy | Magento-authoritative facts, evidence states, no invented commerce facts |
| Availability | Local RizAI neural/deterministic fallback, provider circuit breaker, native-search fallback |
| Scalability | Shared cache, database-backed state, bounded collections and payloads |
| Extensibility | Magento DI registries and public interfaces |
| Portability | Theme-independent PHP core with REST/GraphQL surfaces |
| Accessibility | Semantic storefront controls and live conversational status |
| Auditability | Trace IDs, sanitized tool audit and telemetry events |

## Out of scope

- General-purpose assistant/trivia service.
- Arbitrary web browsing or external facts overriding Magento.
- Direct SQL/model access to Magento business tables.
- Password or payment-secret collection.
- Unconfirmed consequential actions.
