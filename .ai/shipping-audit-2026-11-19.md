# Shipping and Catalog Audit — 2026-11-19

## Scope and evidence

- Audited all 46 enabled catalog products on the live Magento Open Source 2.4.7 store.
- Compared stored product weight/dimensions with all 37 available base images.
- Exercised Shiprocket serviceability for pincode `641002` with the measuring-cup set.
- Reviewed checkout state synchronization, package calculation, configurable-product handling, fallbacks, and configuration.

## Calculation decisions

- Catalog dimensions remain centimetres; catalog and packaging weights remain kilograms.
- The package allowance is 5.08 cm (2 inches), added once to length, width, and packed height.
- Packaging tare is 0.25 kg per shipment by default and is admin-configurable.
- A missing product weight falls back to 0.5 kg per quantity and produces a warning.
- Missing dimensions fall back to the existing configured 10 × 10 × 10 cm values and produce a warning.
- Configurable products use the selected simple child's shipping data instead of the parent product.
- Current packing remains conservative: maximum item length, maximum item width, and summed item heights by quantity.

## Catalog data gaps requiring physical measurements

These ten enabled SKUs have no weight or shipping dimensions:

- `mseal-phataphat-25g`
- `mseal-regular-90g`
- `ezy-seal`
- `mini-jhola-chain-heavy`
- `steel-karni-pvc-handle`
- `shook-2-5-inch-small`
- `shook-4-inch-large`
- `shook-4-inch-large-spl`
- `shook-6-inch-xl`
- `mini-jhola-chain-regular`

Values were not invented from product names. Each should be weighed and measured in its sale-ready packaging, then entered in Magento.

## Image audit

- Nine enabled products have no base image: every SKU in the missing-data list except `shook-2-5-inch-small`.
- The `shook-2-5-inch-small` source image is 4590 × 8160 and about 6.1 MB; replace it with an optimized product image.
- GI-wire variants reuse one photo, and PVC-wire variants reuse one photo. Confirm this is intentional or add variant-specific images.
- Existing images for cement, spades, crowbars, measuring cups, weighing machines, scissors, wire, and the screwdriver are visually consistent with their product type and broadly plausible stored dimensions.
- The five individual measuring cups all use 0.1 kg; physically verify these weights because the different capacities are unlikely to have identical mass.
- Physically verify the 100 cm packaged length for the handled spade; the image suggests the assembled item may be longer.

## Operational follow-up

1. Photograph and measure the ten incomplete SKUs.
2. Replace the nine missing base images and optimize the oversized S-hook image.
3. Weigh actual packaging materials; update the 0.25 kg global tare if the normal packed box differs.
4. If packaging varies materially by product, add a per-product packaging tare in a separate catalog-data project rather than guessing values in shipping code.
