# Magento Project Context - senisstores.com

This file records verified project facts and architecture decisions.

## Verified platform baseline
- **Magento Edition/Version**: Magento Open Source 2.4.7-p10 (Community Edition)
- **PHP Version**: PHP 8.2.36
- **Database**: MariaDB 11.8.9 on localhost (`u434561653_senisstores`)
- **Search**: OpenSearch (exact server version not yet recorded)

## Storefront and checkout architecture
- **Storefront**: Custom child theme `Haerriz/Senisstores` extending `Magento/luma`
- **Checkout**: Standard Magento Luma-based Checkout flow
- **Guest Checkout**: Enabled and locked (`checkout/options/guest_checkout = 1`)

## Infrastructure and deployment
- **Host**: Hostinger Shared VPS/Cloud hosting
- **Compiler**: PHP CLI static compilation
- **Cache Management**: Fully cached (Config, Block HTML, FPC, Layout)

## Important modules and integrations
- **Haerriz_SocialLogin**: Custom Google Callback OAuth callback handling
- **Haerriz_GoogleFeed**: XML feed generator for Google Merchant Center (URL: `/googlefeed/feed/index`)
- **Haerriz_QtyUpdate**: Custom Knockout-based quantity updates widget on grids and product templates
- **Razorpay_Magento**: Razorpay payment gateway integration
- **Mageplaza_Smtp**: Custom SMTP configuration for transactional store emails
- **Haerriz_Shiprocket**: Live carrier rates use the Magento quote postcode plus product weight and `shipping_length`, `shipping_width`, and `shipping_height` attributes. Indian postcode city/state lookup is optional and must not block rate collection.

## Architectural decisions and their reasons
- **Guest Checkout Activation (2026-07-23)**: Enabled Guest Checkout globally in env.php to remove account-creation friction and prevent Cart abandonment.
- **Aligned Action Columns (2026-07-23)**: Redesigned the product grid actions to stack vertically and center secondary icons to eliminate "Add to C..." button clipping and fix mobile column overlap.
- **Checkout postcode synchronization (2026-11-19)**: The checkout form's `checkoutProvider` country/postcode is authoritative. Sync a valid Indian postcode to the quote and request rates immediately; the public postcode lookup is a time-bounded enhancement only.
- **Shiprocket package model (2026-11-19)**: Product shipping dimensions are centimetres and weight is kilograms. Add a configurable 5.08 cm (2 inch) overall dimension allowance and a configurable 0.25 kg packaging tare once per shipment. Configurable products are rated from their selected simple child. Missing catalog values use explicit configurable fallbacks and emit warnings.
- **Catalog shipping audit (2026-11-19)**: All 46 enabled products have positive weight, dimensions, and a live base image. Wire quantity variants use 1–5 kg weights; hook/chain images are mapped from original ruler photos; active base images are under 1 MB.
- **Anonymous FPC safety (2026-11-23)**: Cacheable storefront blocks must not inject a server-generated form key. The shopping assistant reads Magento's `form_key` cookie at action time so anonymous HTML can remain publicly cacheable without sharing a session token.
- **Organic catalogue policy (2026-11-23)**: Add substantial buying guidance only to categories with live products. Empty catalogue branches remain hidden/noindex rather than receiving generated doorway copy. Encourage combined compatible orders without inventing bundle SKUs, discounts, stock, load ratings, or delivery promises.
- **SEO hierarchy (2026-11-23)**: Canonical product URLs use a deterministic deepest active assigned category for visible breadcrumbs and matching `BreadcrumbList` JSON-LD. Category pages expose only active, non-empty child branches. Empty/noindex categories are excluded from the XML sitemap, keeping navigation, structured data, canonicals and sitemap signals aligned.
