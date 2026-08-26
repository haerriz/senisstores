# 500-phrase commerce coverage

> Version 5.0.0 · Reviewed 2026-08-26 · See [Testing](TESTING.md) and [Customer flows](CUSTOMER_FLOWS.md).

`dev/ecommerce_500_phrases.json` is a fixed regression corpus of exactly 500 common storefront phrases. It focuses on Magento/e-commerce language rather than generic assistant trivia.

Coverage areas include product discovery, sort/filter/refinement, custom EAV attributes, product descriptions/Q&A/comparison/media, inventory/quantity, price, configurable options, cart, coupons, wishlist, orders/tracking, CMS/store info, checkout, customer account, newsletter, reviews, alerts, navigation, safety/negation and connected multi-step requests.

Run:

```bash
php dev/phrase_coverage_500.php
```

Release 5.0 retains the existing deterministic regression requirement:

```text
500-PHRASE COVERAGE: 500/500 PASS
```

The 500-phrase corpus is separate from the neural training corpus. It remains a deterministic/regression contract and should not be reused as a blind production neural benchmark.

The corpus validates routing intent. `dev/multistep_smoke.php` separately validates dependent tool sequences such as:

```text
find cheapest manuals → add first result to cart
show black shoes → inspect stock of first result
find premium courses → compare first two
show running shoes → show first result price
show courses → describe first result
```

This separation prevents a misleading metric where only the first tool of a complex request is tested.

The live regression set must additionally cover natural variants such as `what's the address?`, open-ended CMS definitions such as `what's blended learning?`, and out-of-scope arithmetic. These validate store-information/CMS routing and prevent accidental broad catalog searches.
