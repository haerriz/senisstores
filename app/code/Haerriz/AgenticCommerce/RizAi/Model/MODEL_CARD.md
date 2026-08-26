# Model card — rizai-commerce-intent-v1

## Summary

`rizai-commerce-intent-v1` is a compact feed-forward neural network for commerce-intent classification in `Haerriz_AgenticCommerce` 5.0.0. It is intended to complement deterministic planning, not replace Magento authorization or business logic.

## Architecture

- input dimension: 1,024 hashed features;
- hidden layer: 96 ReLU units;
- output: 19 intent classes with softmax;
- feature families: word unigram, word bigram, character 3-gram and character 4-gram;
- inference runtime: pure PHP.

## Training data

The bundled corpus is generated from reviewed, synthetic commerce-language templates and contains no production customer conversations, PII, order history or payment information.

The current artifact reports 2,904 training examples and 720 grouped holdout examples from 3,624 total curated/synthetic rows. The exported-weight holdout accuracy is 90.56% with mean confidence 91.81%. These are controlled synthetic/curated metrics and are not a real-world benchmark.

## Intended use

- local commerce-intent fallback;
- safe read-only tool proposal;
- language-understanding signal in a hybrid neuro-symbolic planner.

## Out of scope

- general knowledge;
- free-form generation;
- factual product answers from model memory;
- price/stock/order truth;
- authorization;
- direct state-changing actions;
- autonomous online retraining.

## Known limitations

- primarily English training language;
- synthetic data distribution;
- no semantic transformer embeddings;
- no long-context reasoning;
- no generative capability;
- model quality depends on future real-world benchmark coverage.

## Governance

Any retrained artifact should carry a new model ID, dataset/version reference, evaluation results and deployment approval. ToolPolicy must remain independent of model weights.
