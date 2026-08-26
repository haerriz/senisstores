# Final package-local validation — Haerriz_AgenticCommerce 5.0.0

Validated: 2026-08-26

## Result

**PASS for package-local validation.** Full Adobe Commerce integration remains a destination-environment test because this archive does not contain a complete Magento installation, database, search service, MSI topology, payment/shipping integrations, Hyvä build or PWA application.

## RizAI neural artifact

| Check | Result |
|---|---|
| Model ID | `rizai-commerce-intent-v1` |
| Type | feed-forward neural network / MLP |
| Input | 1,024 hashed word/bigram/character-ngram features |
| Hidden layer | 96 ReLU units |
| Output | 19 softmax commerce intents |
| Training examples | 2,904 |
| Group-isolated validation examples | 720 |
| Total curated/synthetic corpus | 3,624 |
| Exported-weight validation accuracy | 0.9056 |
| Mean validation confidence | 0.9181 |
| Model SHA-256 | `a1f19d2ef93be0706d664aa41e52bcd27a87d1e396142244ddcb03841a15d707` |
| Recorded train/validation group overlap | 0 |

These metrics are release-regression results on a controlled synthetic/curated corpus. They are **not** a production accuracy SLA or a multilingual benchmark.

## Validation performed

- PHP syntax: **268 PHP files passed** `php -l`.
- XML well-formedness: **12 XML files passed** parsing.
- JSON/JSONL parsing: **5 artifacts/corpora passed** parsing.
- Python syntax: **7 Python files passed** bytecode compilation.
- Neural artifact validator: **PASS** — checksum, matrix shapes, metadata counts, grouped split isolation and recomputed exported-weight metrics.
- PHP learned-weight runtime smoke: **PASS** for product search, store information, store policy, recent orders, out-of-scope, smalltalk, price and product comparison examples.
- Portable generative tool-call parser smoke: **PASS** with allowlisted empty-argument tool call.
- Dataset build determinism: repeated builds produced the same corpus SHA-256 `65b2303e1edc4abf1008d824ec74b5300d583b70d93d3cb78bd321277b50ab87`.
- Neural training determinism: repeated training with seed `4305` produced the same model SHA-256 shown above.

## New safety/finalization behavior

1. The neural model checksum is verified before inference. Missing/corrupt/mismatched bundled weights fail closed to the existing planner fallback.
2. Neural predictions remain a routing signal, not an authorization mechanism.
3. Neural routing cannot propose state-mutating tools in the 5.0 fallback bridge.
4. High-confidence `out_of_scope` and `smalltalk` predictions can return an assistant-only bounded response instead of accidentally falling into broad catalog search.
5. A self-hosted RizAI generative model may emit native OpenAI-compatible `tool_calls` or strict portable JSON. Portable calls are accepted only when the name is present in the ToolPolicy-filtered tool definitions for that request.
6. Magento remains authoritative for catalog, price, inventory, cart, customer, checkout and order facts.

## Generative-model development kit

`RizAi/Generative/` now includes:

- deterministic PII-free SFT seed-data builder;
- 78 training + 18 held-out validation seed examples;
- LoRA / optional QLoRA causal-LM fine-tuning pipeline;
- adapter merge/export utility;
- held-out strict-JSON/tool-sequence evaluator;
- model-neutral strict JSON tool-call contract compatible with `rizai_local_llm`.

This is a **training and serving bridge**, not a bundled generative checkpoint. The package can truthfully claim a bundled trained neural model today. It should only claim a “RizAI LLM” after a chosen generative base checkpoint is fine-tuned, evaluated, versioned and deployed.

## Required destination Magento checks

Run in the target Magento Open Source / Adobe Commerce project:

```bash
bin/magento module:enable Haerriz_AgenticCommerce
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
vendor/bin/phpunit app/code/Haerriz/AgenticCommerce/Test/Unit
vendor/bin/phpcs --standard=Magento2 app/code/Haerriz/AgenticCommerce
```

Also run the project's normal static-content deployment, Hyvä/Tailwind build where applicable, GraphQL/PWA tests, payment/shipping integration tests, MSI tests, customer-group/catalog-rule tests and store-view/locale acceptance tests.
