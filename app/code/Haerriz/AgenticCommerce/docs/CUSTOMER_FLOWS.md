# Customer flows

> Version 5.0.0 · Reviewed 2026-08-26

## 1. Product discovery and refinement

```mermaid
journey
    title Shopper discovers a suitable product
    section Discover
      Ask for a product/course: 5: Shopper
      Receive scoped results and filters: 5: Agent
    section Refine
      Ask for cheaper/in-stock/specific attributes: 4: Shopper
      Preserve query and update filters: 5: Agent
    section Decide
      Ask for description/stock/price/comparison: 5: Shopper
      Receive Magento-grounded evidence: 5: Agent
    section Act
      Select exact product/options: 4: Shopper
      Add through governed direct action: 5: Agent
```

## 2. CMS/storefront question

```mermaid
sequenceDiagram
    actor Shopper
    participant Agent
    participant RizAI
    participant CMS as KnowledgeService
    participant AI as Optional OpenAI/Gemini
    Shopper->>Agent: "What's blended learning?"
    Agent->>RizAI: classify
    RizAI-->>Agent: answer_store_question
    Agent->>CMS: current-store pages + blocks
    CMS-->>Agent: ranked bounded evidence
    Agent->>AI: optional privacy-safe wording
    Agent-->>Shopper: grounded definition; no product dump
```

## 3. Address/store identity

```mermaid
flowchart TD
    Q[Ask address/owner/site/assistant identity] --> CFG{Magento config has fact?}
    CFG -- yes --> A[Return configured fact]
    CFG -- no, address --> CMS[Scan enabled store CMS blocks/pages]
    CMS --> FOUND{Postal address found?}
    FOUND -- yes --> A
    FOUND -- no --> NS[State that it is not configured]
```

## 4. Cart and checkout

```mermaid
flowchart LR
    P[Select exact product/options] --> ADD[Add to cart]
    ADD --> CART[Review/update/remove/coupon]
    CART --> STATE[Checkout state]
    STATE --> EMAIL[Guest email if required]
    EMAIL --> ADDR[Shipping/billing address]
    ADDR --> SHIP[Shipping method]
    SHIP --> PAY[Payment method metadata]
    PAY --> PREP[Prepare order]
    PREP --> CONF[Explicit confirmation token]
    CONF --> ORDER[Magento places order]
```

Payment secrets remain in native/provider UI, not chat.

## 5. Signed-in customer

```mermaid
flowchart TD
    LOGIN[Native Magento sign-in] --> CLAIM[Safely claim same-browser guest conversation]
    CLAIM --> PROFILE[Profile/address reads and low-risk updates]
    CLAIM --> WISH[Wishlist]
    CLAIM --> ORD[Owned orders/tracking]
    PROFILE --> POLICY[Trusted customer identity + ToolPolicy]
    WISH --> POLICY
    ORD --> POLICY
```

## 6. Out-of-scope request

```mermaid
flowchart LR
    Q["2 + 2" / unrelated trivia] --> SCOPE[Storefront scope guard]
    SCOPE --> REFUSE[Concise scoped refusal]
    REFUSE --> ZERO[0 products, 0 generic product suggestions]
```

## Expected customer experience

- A new question never displays stale product cards from a prior turn.
- Informational CMS/store-profile answers suppress unrelated latest/cheapest suggestions.
- Existing conversation history remains immutable; corrected behavior applies to new turns.
- A customer sees only their owned cart, wishlist, addresses and orders.
