# Enterprise Multi-Channel Product Feed & Google Shopping Generator for Magento 2

[![Magento 2.4.7](https://img.shields.io/badge/Magento-2.4.7-orange.svg)](https://magento.com)
[![PHP 8.2](https://img.shields.io/badge/PHP-8.2-blue.svg)](https://php.net)
[![Build Status](https://img.shields.io/badge/DI%20Compile-100%25%20Passed-green.svg)](https://github.com/haerriz/magento2-google-shopping-feed)
[![License](https://img.shields.io/badge/License-MIT-brightgreen.svg)](LICENSE)

An enterprise-grade, multi-channel product feed generator for Magento 2. Fully supports **Google Shopping, Meta/Facebook, Instagram, Snapchat, TikTok, Pinterest, Microsoft/Bing, Amazon, eBay, Rakuten, and OpenAI/ChatGPT Agentic Commerce**.

---

## 🌟 Key Features

- 🧙‍♂️ **Exact 7-Step Admin Wizard**:
  - **Step 1: General Settings** – Name, Status, Feed Channel & Output Filename.
  - **Step 2: Exclude Categories** – Exclude entire category branches cleanly.
  - **Step 3: Rename Categories** – Map store categories to Google/Channel Taxonomy.
  - **Step 4: Basic Product Info** – SKU, Title, Price, Description, Availability mapping.
  - **Step 5: Optional Product Info** – Brand, GTIN, MPN, Condition, UTM tracking.
  - **Step 6: Schedule Settings** – Automated cron schedule expression.
  - **Step 7: Destination** – Local, FTP, SFTP, or Direct Merchant API delivery.

- 🛍️ **11 Multi-Channel Templates**:
  1. **Google Shopping** (`XML`)
  2. **Meta / Facebook Catalog** (`CSV`)
  3. **Instagram Shopping** (`CSV`)
  4. **Snapchat Product Catalog** (`CSV`)
  5. **TikTok Commerce Catalog** (`CSV`)
  6. **Pinterest Product Catalog** (`CSV`)
  7. **Microsoft / Bing Shopping** (`XML`)
  8. **Amazon Seller Catalog** (`CSV`)
  9. **eBay Inventory Feed** (`CSV`)
  10. **Rakuten Advertising** (`CSV`)
  11. **OpenAI / ChatGPT Agentic Commerce** (`JSONL`)

- ⚡ **Multi-Format Writer Engine**:
  - Automatically resolves `xml`, `csv`, `tsv`, `txt`, and `jsonl` formats with smart fallbacks.

- 🔍 **Interactive Admin Grid & Quick View**:
  - Actions Menu: Edit, Quick View, Generate Now, Duplicate, Job History, Download, Delete.
  - Live Quick View Preview window rendering real-time formatted output.

- 💻 **System Information & Diagnostics Panel**:
  - Located at `Stores -> Configuration -> Haerriz Extensions -> Product Feed`.
  - Real-time display of Magento Mode, Root Path, Server User, DB Timestamp, Opcache status, and CLI PHP path.

---

## 🛠️ CLI Commands

```bash
# Generate a feed profile manually
php bin/magento haerriz:feed:generate --profile=1

# Validate profile mapping & configuration
php bin/magento haerriz:feed:validate --profile=1
```

---

## 📄 License
MIT License. Created by Haerriz.
