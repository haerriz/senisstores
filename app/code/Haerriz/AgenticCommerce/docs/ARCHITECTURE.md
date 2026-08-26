# Architecture

> Version 5.0.0 · Reviewed 2026-08-26

## Component architecture

```mermaid
flowchart LR
    UI[Storefront client] --> AS[AgentService]
    AS --> ID[IdentityResolver]
    AS --> CONV[ConversationService]
    AS --> CP[CompositePlanner]
    CP --> RULES[PlannerRuleProviders]
    CP --> DET[Deterministic safety planner]
    CP --> MEM[Adaptive routing memory]
    CP --> PM[ProviderManager]
    PM --> OA[OpenAI Responses]
    PM --> GE[Gemini]
    PM --> RLLM[RizAI self-hosted LLM]
    PM --> COMP[Compatible gateway]
    CP --> NN[RizAI neural intent model]
    NN --> HASH[FeatureHasher]
    NN --> W[(Bundled learned weights)]
    CP --> TREG[ToolRegistry]
    AS --> GUARD[ToolIntentGuards]
    AS --> POLICY[ToolPolicy]
    AS --> TREG
    TREG --> DOMAIN[Magento domain services]
    DOMAIN --> MAG[(Magento APIs/data)]
    AS --> SYN[ResponseSynthesisService]
    SYN --> FACT[ExternalFactPolicy]
    AS --> SUG[SuggestionService]
    AS --> AUDIT[Audit/Telemetry/Learning]
```

## Request sequence

```mermaid
sequenceDiagram
    actor Shopper
    participant Client
    participant Agent as AgentService
    participant Planner
    participant Policy as IntentGuard + ToolPolicy
    participant Tool
    participant Magento
    participant AI as Optional external/self-hosted LLM
    participant NN as RizAI neural model
    Shopper->>Client: Natural-language request
    Client->>Agent: message + opaque client/conversation/cart context
    Agent->>Agent: resolve trusted identity and server conversation
    Agent->>Planner: plan(message, safe context)
    Planner->>AI: optional redacted plan request for non-locked intents
    Planner->>NN: local learned-weight fallback when needed
    NN-->>Planner: intent + confidence + margin
    Planner-->>Agent: bounded tool calls / scoped response
    Agent->>Policy: validate explicit intent and authorization
    Policy-->>Agent: allow or fail closed
    Agent->>Tool: execute arguments
    Tool->>Magento: repository/service/quote/CMS operation
    Magento-->>Tool: authoritative result
    Tool-->>Agent: structured facts + message
    Agent->>AI: optional privacy-filtered wording
    Agent-->>Client: bounded payload + trace ID
    Client-->>Shopper: message/cards/actions
```

## Authority hierarchy

```mermaid
flowchart TD
    A[Magento mutation/read result] -->|highest| OUT[Response]
    B[Store-scoped config, CMS, catalog evidence] --> OUT
    C[Server-owned conversation context] --> OUT
    D[External/self-hosted model wording] -->|may summarize only| OUT
    N[RizAI neural intent prediction] -->|routing signal only| OUT
    E[Client-supplied prose] -->|intent only; never authority| OUT
```

## Deployment topology

```mermaid
flowchart TB
    CDN[CDN/WAF] --> WEB1[Magento web node]
    CDN --> WEB2[Magento web node]
    WEB1 --> REDIS[(Shared Redis/cache)]
    WEB2 --> REDIS
    WEB1 --> DB[(Commerce DB)]
    WEB2 --> DB
    WEB1 --> SEARCH[(OpenSearch/Live Search)]
    WEB2 --> SEARCH
    WEB1 --> AIGW[Optional external/self-hosted AI gateway]
    WEB2 --> AIGW
    WEB1 --> NNW[Bundled RizAI neural weights]
    WEB2 --> NNW
    CRON[Magento cron/consumers] --> DB
```

Conversation, confirmation, idempotency, learning and audit authority is not process-local. Provider health/rate state should use shared Magento cache.

## Extension architecture

```mermaid
flowchart LR
    EXT[Extension module] --> PR[ProviderRegistry]
    EXT --> TR[ToolRegistry]
    EXT --> TP[ToolPolicy metadata]
    EXT --> AR[Authorization providers]
    EXT --> PP[Planner rule providers]
    EXT --> SP[StoreProfile providers]
    EXT --> TE[Telemetry processors]
    TR --> CORE[Agentic core]
    TP --> CORE
    AR --> CORE
    PP --> CORE
    SP --> CORE
```
