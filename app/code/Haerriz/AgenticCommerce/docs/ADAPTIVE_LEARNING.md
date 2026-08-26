# Adaptive learning

> Version 5.0.0 · Reviewed 2026-08-26 · See [Architecture](ARCHITECTURE.md) and [Knowledge grounding](KNOWLEDGE_GROUNDING.md).

Agentic Commerce 5.0 retains **adaptive routing memory** as a separate runtime mechanism from the new RizAI neural model. Runtime traffic never performs autonomous code or neural-weight self-modification.

## Relationship to the RizAI neural model

5.0 introduces a separately trained neural intent model, but adaptive memory does **not** update its weights. Sanitized observations can become candidates for an offline reviewed training dataset. A new neural artifact must be trained, evaluated, versioned and deployed as a release. This preserves rollback, reproducibility and poisoning resistance.

## What can be learned

The module observes successful/failed phrase→tool outcomes after ToolPolicy execution. Messages are privacy-normalized and hashed per store. Repeated proven aliases may become active routes only for a narrow public read-only allowlist:

- store information;
- store/CMS knowledge;
- store context;
- catalog navigation;
- CMS-page search;
- category search;
- product search.

Cart/order/account/wishlist/customer-sensitive reads are not auto-learned, and no mutation can self-authorize.

## Conflict handling

If the same normalized phrase develops credible evidence for competing tools, the pattern is marked `conflict`; the Commerce Brain refuses to use an ambiguous learned route. A learned plan is accepted only when exactly one active auto-approved mapping exists.

## Shopper feedback

Feedback is accepted only when the named tool actually appeared in the sanitized audit for the owned conversation **for that exact hashed user message**. A conversation/tool/message combination can receive feedback once, preventing repeated thumbs-up/down replay from artificially changing confidence.

## Privacy normalization

The normalizer replaces email addresses, phone-like values, URLs, order/quote identifiers and numeric values before persistence. Learning data has independent retention cleanup.

## What it never does

Adaptive learning never:

- edits PHP/JavaScript/XML;
- changes model weights;
- invents new Magento tools;
- changes ToolPolicy;
- bypasses authentication or quote ownership;
- learns transactional permissions;
- exposes raw customer/payment data to an external model.

Learned aliases affect routing only. Store facts still come from current-store Magento configuration, catalog or CMS evidence and remain subject to intent guards and ToolPolicy.
