# Seni's Stores — Magento 2 Custom Code

Custom modules and theme overrides for [senisstores.com](https://senisstores.com).

## Structure

- `app/code/Haerriz/GoogleFeed` — Google Merchant Center XML feed (`/googlefeed/feed/index`)
- `app/code/Haerriz/Csp` — CSP whitelist for third-party scripts
- `app/design/frontend/Haerriz/Senisstores/` — Theme overrides (a11y, performance, logo WebP)

## Deploy

Copy `app/code` and `app/design` into Magento root, then:

```bash
php bin/magento module:enable Haerriz_GoogleFeed Haerriz_Csp
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f en_US --theme Haerriz/Senisstores
php bin/magento cache:flush
```

## Google Merchant Feed

Feed URL: `https://senisstores.com/googlefeed/feed/index`

## Haerriz Abandoned Cart

- `app/code/Haerriz/AbandonedCart` — WooCommerce-style abandoned cart recovery for Magento 2
- Cron: every 15 minutes, rate-limited (3 emails/run, 8–18s delay)
- Admin: Stores → Configuration → Haerriz → Abandoned Cart

Enable with:

```bash
php bin/magento module:enable Haerriz_AbandonedCart
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:flush
```
