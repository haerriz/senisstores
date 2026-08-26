# Validation Report — Haerriz_AgenticCommerce 5.0.0

This report records validation performed for the 5.0.0 RizAI neural-model upgrade and preserves the runtime boundary between package-local checks and checks that require a complete Magento Open Source / Adobe Commerce installation.

## What changed in 5.0

RizAI now ships a versioned learned-weight model artifact at `RizAi/Model/rizai-commerce-intent-v1.json`. The model is a feed-forward neural network trained offline with PyTorch and executed at Magento runtime through a pure-PHP inference implementation. It is used as a bounded read-only intent-routing signal inside the existing deterministic safety/governance architecture.

The release also includes:

- `Model/RizAi/FeatureHasher.php` — runtime feature encoder matching the offline training contract;
- `Model/RizAi/NeuralModelRuntime.php` — ReLU + softmax learned-weight inference;
- `Model/Planner/NeuralIntentPlanner.php` — confidence/margin-gated neural-to-tool bridge;
- `RizAi/Training/build_dataset.py` and `train.py` — reproducible offline training pipeline;
- `RizAi/Training/commerce_intents.jsonl` — curated/synthetic supervised corpus;
- `Test/Unit/Model/RizAi/NeuralModelRuntimeTest.php` — model-load and intent-prediction unit coverage;
- `rizai_local_llm` — governed provider slot for a separately trained/self-hosted future generative RizAI model.

## Neural training execution

The bundled `rizai-commerce-intent-v1` artifact was trained from **2,904 training examples** and evaluated on **720 group-isolated validation examples** spanning **19 commerce-intent classes**. The exported artifact reached **0.9997 training accuracy**, **0.9056 validation accuracy** and **0.9181 mean validation confidence**. The dataset builder keeps every polite variant of a base utterance on one side of the train/validation boundary, and `validate_artifact.py` verifies zero recorded group overlap.

These numbers prove that the exported weights learn the controlled corpus; they are **not a production accuracy claim**. Real merchant traffic, languages, verticals, adversarial prompts and domain drift require separate benchmark sets before a production SLA is stated.

## Safety validation target

The neural model is intentionally not an authorization mechanism. Release/runtime testing must verify all of the following invariants:

1. Predictions below the configured confidence threshold are ignored.
2. Predictions below the configured top-1/top-2 margin are ignored.
3. Neural routing can propose only tools marked enabled and planner-visible by `ToolPolicy`.
4. Any tool marked `mutates_state` is rejected by `NeuralIntentPlanner`.
5. Product-reference intents require server-owned recent-product context.
6. Deterministically locked/consequential actions are never replaced by the neural plan.
7. Magento catalog, price, inventory, quote, customer and order services remain the source of truth.
8. External/self-hosted generative providers cannot bypass ToolPolicy or trusted identity.

## Package-local validation

Before release, execute and record:

```bash
# PHP syntax
find . -name '*.php' -print0 | xargs -0 -n1 php -l

# JSON model + dataset parse
python -m json.tool RizAi/Model/rizai-commerce-intent-v1.json >/dev/null
python - <<'PY2'
import json
from pathlib import Path
for line in Path('RizAi/Training/commerce_intents.jsonl').read_text().splitlines():
    if line.strip(): json.loads(line)
print('dataset JSONL: OK')
PY2

# XML well-formedness
python - <<'PY2'
import xml.etree.ElementTree as ET
from pathlib import Path
for p in Path('etc').rglob('*.xml'):
    ET.parse(p)
print('XML: OK')
PY2

# Reproducible neural training (developer/release environment)
python RizAi/Training/build_dataset.py
python RizAi/Training/train.py
```

The training scripts are release/development tooling only. Magento runtime does not require Python or PyTorch.

## Required Magento runtime verification

This standalone module workspace does not contain the destination project's full `vendor/` tree, database, generated DI, OpenSearch/Elasticsearch, MSI topology, payment/shipping providers, customer groups, Hyvä build or Venia application. Therefore full Adobe Commerce integration must still be verified in the destination project:

```bash
bin/magento module:enable Haerriz_AgenticCommerce
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
vendor/bin/phpunit app/code/Haerriz/AgenticCommerce/Test/Unit
vendor/bin/phpcs --standard=Magento2 app/code/Haerriz/AgenticCommerce
```

In production mode also run the project's normal static-content deployment and Hyvä Tailwind build. For Venia/PWA Studio run the storefront's GraphQL build/tests.

## Production model acceptance

Before enabling the neural router broadly, build a merchant-specific blind evaluation set that was never used for training or template generation. Measure at least intent accuracy, per-class precision/recall, abstention rate, false-positive rate for identity-sensitive tools, latency, memory usage and confidence calibration. Canary the model by store view and keep deterministic fallback enabled.

See `docs/RIZAI_NEURAL_MODEL.md`, `RizAi/Model/MODEL_CARD.md`, `docs/SECURITY.md` and `docs/RUNTIME_ACCEPTANCE.md`.
