# Enterprise Multi-Channel Product Feed Generator for Magento 2

[![Magento 2.4+](https://img.shields.io/badge/Magento-2.4%2B-orange.svg)](https://magento.com)
[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-brightgreen.svg)](LICENSE)

Multi-channel product feed generator for Magento 2 with Google Merchant API sync, FTP/SFTP delivery, taxonomy mapping, cron scheduling, and admin wizard tooling.

Supported channels: **Google Shopping, Meta/Facebook, Instagram, Snapchat, TikTok, Pinterest, Microsoft/Bing, Amazon, eBay, Rakuten, OpenAI/ChatGPT Commerce**.

---

## Requirements

- Magento Open Source / Adobe Commerce 2.4.x
- PHP 8.1 / 8.2 / 8.3
- Magento cron configured
- Optional: `phpseclib` (via Magento dependencies) for SFTP
- Optional: Google Cloud service account for Merchant API sync

---

## Installation

```bash
composer require haerriz/module-google-shopping-feed
bin/magento module:enable Haerriz_GoogleShoppingFeed
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

Manual install: copy this module to `app/code/Haerriz/GoogleShoppingFeed`, then run the Magento commands above.

---

## Configuration

1. Go to **Stores → Configuration → Haerriz Extensions → Product Feed**
2. Enable the module
3. (Optional) Configure Google Merchant API:
   - Merchant Account ID
   - Service Account JSON key (stored encrypted)
   - Target country / currency
   - API mode (`production` or `sandbox`)
4. Create feed profiles under **Marketing → Google Shopping Feeds**

### Feed profile wizard

1. General: name, status, channel, filename
2. Exclude categories
3. Rename / map categories to channel taxonomy
4. Basic product attributes (SKU, title, price, description, availability)
5. Optional attributes (brand, GTIN, MPN, condition, UTM)
6. Schedule (cron expression)
7. Destination: Local / FTP / SFTP / Merchant API

---

## CLI Commands

```bash
# Generate one profile
php bin/magento haerriz:feed:generate --profile=1

# Validate mapping/config
php bin/magento haerriz:feed:validate --profile=1

# Import Google taxonomy
php bin/magento haerriz:feed:import-taxonomy

# Sync / reconcile Merchant API
php bin/magento haerriz:feed:merchant-sync
php bin/magento haerriz:feed:merchant-reconcile

# Consume queued jobs / cleanup artifacts
php bin/magento haerriz:feed:consume-jobs
php bin/magento haerriz:feed:cleanup-artifacts
```

---

## Security notes

- Delivery passwords and Google service-account JSON are encrypted with Magento's encryptor.
- Remote FTP/SFTP host validation blocks private, loopback, link-local, and reserved IPs (SSRF hardening).
- Profile duplication scrubs credentials and resets lock/retry runtime state.
- Admin ACL resources separate profile management, generation, downloads, jobs, and credentials.

---

## Supported product types

| Type | Feed behavior |
|------|----------------|
| Simple | Exported as one offer |
| Virtual / Downloadable | Exported as one offer (no shipping weight metadata) |
| Configurable | Expands to enabled child variants (`item_group_id` = parent SKU) |
| Grouped | Expands to enabled associated products |
| Bundle | Exported as the sellable parent offer |

Currency for price fields is taken from the profile currency, then store currency (no hardcoded INR).

---

## Testing

```bash
vendor/bin/phpunit -c phpunit.xml
```

---

## License

MIT License. Created by Haerriz.
