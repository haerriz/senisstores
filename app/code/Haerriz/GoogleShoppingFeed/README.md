<div align="center">

# 🛒 Magento 2 Google Shopping Feed

### Enterprise-Grade Multi-Channel Product Feed Generator & Google Merchant API Sync

[![Magento 2](https://img.shields.io/badge/Magento-2.4.x-orange?style=for-the-badge&logo=magento)](https://magento.com)
[![PHP](https://img.shields.io/badge/PHP-8.1%20|%208.2%20|%208.3-blue?style=for-the-badge&logo=php)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)](LICENSE)
[![Version](https://img.shields.io/badge/Version-2.0.0-red?style=for-the-badge)](https://github.com/haerriz/magento2-google-shopping-feed/releases)
[![Hyva Compatible](https://img.shields.io/badge/Hyv%C3%A4-Compatible-ff6600?style=for-the-badge)](https://hyva.io)

---

**Generate, optimize, and sync product feeds across 11+ shopping channels — Google, Meta, Amazon, TikTok, Pinterest, Bing, eBay, Rakuten, OpenAI/ChatGPT, and more.**

[Features](#-features) · [Installation](#-installation) · [Supported Channels](#-supported-channels) · [Google Merchant API](#-google-merchant-api-v1) · [Documentation](#-documentation)

</div>

---

## ✨ Features

### 🔄 Multi-Channel Feed Generation
- **Unlimited Feed Profiles** — Create as many feeds as you need for different channels, stores, and currencies
- **11+ Ready-Made Templates** — Pre-configured for Google, Meta, Amazon, Bing, TikTok, Pinterest, eBay, Rakuten, OpenAI/ChatGPT
- **Custom Templates** — Build your own XML, CSV, TSV, TXT, or JSONL feed formats
- **Multiple Output Formats** — XML, CSV, TSV, TXT, JSONL with optional GZ compression

### 🗺️ Smart Attribute Mapping
- **Dynamic Rows** — Infinite attribute mapping rows with drag-and-drop ordering
- **Google Taxonomy Autocomplete** — Map categories to Google's official product taxonomy
- **Enterprise Modifiers** — Strip HTML, round prices, capitalize text, append/prepend values
- **Condition-Based Filtering** — Include/exclude products by category, attribute, stock status, visibility

### 🔗 Google Merchant Center API v1
- **Direct API Sync** — Push products directly to Google Merchant Center via the official Merchant API v1
- **Service Account Auth** — Secure OAuth2 authentication with encrypted credentials
- **Data Sources Management** — Create, list, and manage API data sources
- **Product Operations** — Insert, update, patch, and delete products programmatically
- **Status Reconciliation** — Track disapproved products and sync status back to Magento
- **Exponential Backoff** — Intelligent retry logic with jitter for API rate limits

### ⏰ Advanced Scheduling & Automation
- **Timezone-Aware Cron** — Schedule feeds hourly, daily, weekly, monthly, or with custom cron expressions
- **Concurrency Control** — Skip, queue, or replace policies for overlapping jobs
- **Lock Management** — Distributed locking prevents duplicate executions
- **Retry & Recovery** — Configurable retry count with exponential backoff
- **Stale Job Detection** — Automatic recovery from stuck or abandoned jobs
- **Failure Notifications** — Auto-disable after consecutive failures with optional alerts

### 📊 Analytics & UTM Attribution
- **Per-Profile UTM Settings** — Configure `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`
- **Dynamic Placeholders** — Use `{sku}`, `{platform}`, `{profile}`, `{store}`, `{product_id}`, `{category}`
- **Smart URL Merging** — Preserves existing query strings and URL fragments
- **Platform Defaults** — Pre-configured UTM values for each channel

### 📦 Delivery & Distribution
- **Local Storage** — Save feeds to the Magento filesystem
- **FTP Upload** — Automated FTP delivery with configurable host, port, and credentials
- **SFTP Upload** — Secure SFTP delivery with encrypted password storage
- **Google Merchant API** — Direct API push (no file upload needed)

### 📋 Job History & Monitoring
- **Comprehensive Logging** — Track selected, processed, exported, skipped, warning, and error counts
- **Job Timeline** — Queued, started, and completed timestamps with duration tracking
- **Admin Grid** — Full-featured job history grid with filters and sorting
- **Log Sanitization** — Automatic credential redaction in all log outputs
- **Manual Controls** — Retry failed jobs, cancel running jobs, download logs

### 🎨 Theme Compatibility
- **100% Theme-Agnostic** — All processing runs server-side with zero storefront dependencies
- **Hyva Theme** ✅ — Fully compatible, no RequireJS or Knockout.js required
- **Hyva Checkout** ✅ — No checkout interference whatsoever
- **Luma Theme** ✅ — Full support for Magento's default theme
- **PWA/Headless** ✅ — Works with any headless or PWA storefront

### 🔒 Security
- **ACL Protection** — Granular admin permissions for feed management
- **CSRF Protection** — Form key validation on all admin actions
- **Credential Encryption** — FTP/SFTP passwords encrypted using Magento's core encryptor
- **Path Traversal Prevention** — Strict basename validation on file operations
- **Log Redaction** — Passwords, API keys, and secrets automatically stripped from logs
- **No PII Exposure** — Customer personal data is never included in product feeds

---

## 🛍️ Supported Channels

| Channel | Format | Template | API Sync |
|---------|--------|----------|----------|
| **Google Shopping** | XML | ✅ Built-in | ✅ Merchant API v1 |
| **Meta / Facebook / Instagram** | CSV | ✅ Built-in | — |
| **Microsoft / Bing Shopping** | XML | ✅ Built-in | — |
| **TikTok Catalog** | CSV | ✅ Built-in | — |
| **Pinterest Catalog** | CSV | ✅ Built-in | — |
| **Amazon** | CSV | ✅ Built-in | — |
| **eBay** | CSV | ✅ Built-in | — |
| **Shopping.com** | CSV | ✅ Built-in | — |
| **Rakuten** | CSV | ✅ Built-in (Beta) | — |
| **OpenAI / ChatGPT** | JSONL.gz | ✅ Built-in | — |
| **Custom** | XML/CSV/TSV/TXT/JSONL | ✅ Configurable | — |

---

## 📦 Installation

### Via Composer
```bash
composer require haerriz/module-google-shopping-feed
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:flush
```

### Manual Installation
1. Download and extract to `app/code/Haerriz/GoogleShoppingFeed/`
2. Run the Magento setup commands above

---

## 🔧 Configuration

### Admin Panel
Navigate to **Stores → Haerriz Google Feed → Manage Feeds**

1. **Create a Feed Profile** — Name, store view, currency, output format
2. **Map Attributes** — Map Magento attributes to channel-specific fields
3. **Set Conditions** — Filter products by category, attribute, or stock status
4. **Configure Delivery** — Local, FTP, SFTP, or Google Merchant API
5. **Schedule Generation** — Set automatic cron schedules with timezone support
6. **Enable UTM Tracking** — Configure analytics attribution parameters

### Google Merchant API v1
1. Navigate to **Stores → Configuration → Haerriz → Google Shopping Feed**
2. Enter your **Merchant Account ID**
3. Upload your **Service Account JSON** key file
4. Click **Test Connection** to verify permissions
5. Create a feed profile with delivery type set to **Google Merchant API**

---

## 🖥️ CLI Commands

```bash
# Generate feed for a specific profile
php bin/magento haerriz:feed:generate --profile_id=1

# Generate all active feeds
php bin/magento haerriz:feed:generate-all
```

---

## 🔌 Compatibility Matrix

| Component | Supported Versions |
|-----------|-------------------|
| **Magento** | 2.4.4 — 2.4.7+ |
| **PHP** | 8.1, 8.2, 8.3 |
| **Hyva Theme** | 1.x |
| **Hyva Checkout** | 1.x |
| **MySQL** | 8.0+ |
| **MariaDB** | 10.4+ |

---

## 📚 Documentation

- [Installation Guide](#-installation)
- [Configuration Guide](#-configuration)
- [Google Merchant API Setup](#google-merchant-api-v1)
- [CLI Reference](#%EF%B8%8F-cli-commands)
- [Supported Channels](#-supported-channels)
- [Compatibility Matrix](#-compatibility-matrix)

---

## 🏗️ Architecture

```
Haerriz/GoogleShoppingFeed/
├── Api/                    # Service contracts & interfaces
├── Block/                  # Admin UI blocks
├── Controller/             # Admin controllers (CRUD, triggers)
├── Cron/                   # Scheduled feed generation
├── Model/
│   ├── Api/                # Google Merchant API v1 clients
│   ├── Cron/               # Scheduler, Dispatcher, JobManager
│   ├── Modifier/           # Attribute value transformers
│   ├── Storage/            # Local, FTP, SFTP adapters
│   ├── Template/           # Channel presets & exporters
│   ├── Url/                # UTM parameter builder
│   └── Logger/             # Log sanitization
├── Test/Unit/              # PHPUnit test suite
├── etc/                    # Module configuration, DI, ACL, cron
└── view/adminhtml/         # Admin UI components & layouts
```

---

## 📄 Changelog

### v2.0.0 (2026-07-29)
- 🎉 Initial enterprise release
- Multi-channel feed generation (11+ platforms)
- Google Merchant Center API v1 integration
- Timezone-aware scheduling with concurrency control
- UTM/GA attribution tracking
- FTP/SFTP delivery adapters
- OpenAI Agentic Commerce (ChatGPT) feed support
- Comprehensive job history & monitoring
- Full Hyva Theme & Checkout compatibility
- Security hardening (ACL, CSRF, encryption, log redaction)

---

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

---

## 📜 License

This project is licensed under the MIT License — see the [LICENSE](LICENSE) file for details.

---

<div align="center">

**Built with ❤️ for Magento 2**

[⬆ Back to Top](#-magento-2-google-shopping-feed)

</div>
