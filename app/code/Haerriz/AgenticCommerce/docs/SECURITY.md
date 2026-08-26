# Security Architecture

> Version 5.0.0 · Reviewed 2026-08-26 · Diagrams: [Data/security flows](DATA_AND_SECURITY_FLOWS.md).

1. **Magento authority** — storefront data/mutations use Magento services/repositories/domain models; the LLM has no arbitrary SQL or arbitrary GraphQL execution.
2. **Trusted identity** — customer/customer-group identity comes from Magento customer session or GraphQL authentication context, never request arguments.
3. **Guest cart isolation** — headless guests use Magento masked cart IDs; numeric quote IDs and customer-owned quotes are rejected.
4. **Default-deny tools** — every registered tool must be classified; unknown tools are treated as consequential and denied.
5. **Two-stage authorization** — planner visibility is filtered and execution is re-authorized by ToolPolicy.
6. **No auth/payment secrets** — password/login secrets, card numbers, CVV/CVC/PAN are not accepted by the agent GraphQL contract. Native Magento/provider UI owns those flows.
7. **Inventory privacy** — only storefront salability/salable quantity may be returned. Quantity exposure is Admin controlled. MSI `source_code` and per-source warehouse quantity are not exposed.
8. **Confirmation gate** — order placement and destructive account operations use expiring, identity/store/conversation-bound server confirmations. Order confirmation fingerprints quote state before execution.
9. **PII forms bypass external planners** — structured addresses/profile forms call direct service endpoints; raw PII need not be sent to the AI provider.
10. **Audit sanitization** — tool audit records omit/hash client/cart/auth secrets and store operational metadata rather than raw credentials.
11. **Raw SQL isolation** — ResourceConnection is limited to AgenticCommerce persistence tables (conversation, messages, audit, confirmation). Magento storefront tables are never queried directly.
12. **Bounded work** — product results, attributes, variant candidates, history, tool loops and batch inventory are bounded to reduce abuse/resource exhaustion.
13. **Historical replay safety** — replayed old turn controls do not mutate the current cart/account.
14. **Native authentication navigation** — sign in, account registration and password reset route to Magento's native secure pages; passwords are never requested in chat.

15. **Product-content grounding** — product descriptions are sanitized/bounded, executable HTML bodies are removed, media/evidence payloads are bounded, and Q&A explicitly distinguishes `evidence_found` from `not_stated`.
16. **Comparison boundedness** — rich product comparison is limited to four products and evidence-based fit is labelled as catalog evidence rather than an objective quality score.
17. **Storefront scope boundary** — unrelated general questions do not invoke catalog tools or receive stale product payloads.
18. **CMS scope and injection boundary** — only active current-store/all-store CMS content is evidence; it is sanitized, bounded and treated as untrusted data.


## Neural-model boundary

The bundled RizAI neural network is treated as an **untrusted probabilistic routing signal**, not as an authorization engine. A prediction must pass store-scoped confidence and top-2 margin gates before `NeuralIntentPlanner` considers it. The planner then re-checks ToolPolicy metadata and rejects disabled, hidden or state-mutating tools. Deterministically locked operations cannot be displaced by neural inference. Product-reference neural routes also depend on server-owned recent-product context.

Neural weights are updated only through an offline reviewed training/release process; storefront traffic never performs gradient updates. This prevents live prompt/data poisoning from becoming an immediate permission or model-weight change.

## External AI provider boundary

Provider keys are encrypted in Magento configuration and never exposed to storefront clients. HTTPS is required by default. Gemini credentials are sent through a header, not a URL query parameter. Product/CMS/review text is explicitly classified as untrusted data to reduce prompt-injection risk. Response synthesis is privacy-filtered and never overrides state-changing Magento tool results.

## Adaptive-learning boundary

Learning cannot create/modify tools or policy. Auto-activation is restricted to public read-only routes; customer-sensitive reads and mutations are excluded. Conflicting routes are quarantined and duplicate feedback replay is rejected.
