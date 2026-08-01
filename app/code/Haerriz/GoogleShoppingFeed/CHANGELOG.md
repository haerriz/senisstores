# Changelog - Haerriz_GoogleShoppingFeed

All notable changes to the `Haerriz_GoogleShoppingFeed` Magento 2 module are documented here.

## [2.3.0] - 2026-08-01

### Added
- **Preview UX**: Rich preview payload with row count, format, channel, field errors, completeness score, and “only changed since last job” dry-run filter.
- **Merchant status UI**: Remote state model/resource/repository, dashboard Merchant Status cards, recent disapproved offers table, and Reconcile action.
- **Completeness QA**: `CompletenessScorer` plus admin CSV QA report download (`Feed/QaReport`).
- **Taxonomy auto-map**: `Taxonomy\AutoMapper` and `Taxonomy/AutoMap` controller to map Magento categories to Google taxonomy paths.
- **Health alerts**: Failure alerter with configurable threshold/email/Slack webhook; hooked from cron `Dispatcher`.
- **Delivery webhook**: `WebhookNotifier` posts `{profile_id, filename, exported, checksum}` after successful adapter delivery.
- **Schedule presets**: `SchedulePreset` source + `PresetApplier` cron expressions for google_daily / meta_hourly / bing_daily / weekly.
- **Conflict detection**: Warns on dashboard when legacy `Haerriz_GoogleFeed` module is enabled.

### Improved
- **Configurable variants**: Child offers inherit parent name/image, set `item_group_id`, and expose color/size from configurable attributes.
- **Product value resolver**: Explicit color/size resolution; price supports include-tax via profile flags and `Catalog\Helper\Data::getTaxPrice`.
- **Remote state schema**: Added nullable `issues` and `synced_at` columns.

## [2.2.0] - 2026-08-01

### Fixed (Security & Correctness Audit)
- **SSRF hardening**: Implemented real private/loopback/link-local/reserved IP blocking in `RemoteHostValidator` (with `validate()` + `isValid()` APIs).
- **CredentialProvider completeness**: Implemented `encrypt()`, `decrypt()`, and `getConfigSecret()` required by SFTP/FTP/Save flows.
- **Currency bug**: Removed hardcoded `INR` from `ProductValueResolver`; price currency now comes from profile/store.
- **Config path mismatch**: Aligned `etc/config.xml` and `Model\Config` paths with `system.xml` (`general/enable`, `google_merchant_api/*`).
- **Save controller mapping**: Canonical `delivery_*` fields + legacy `ftp_*` aliases; encrypts delivery passwords; escapes success message HTML.
- **ConnectionTester**: Uses delivery host/user/password fields and host validator before opening sockets.
- **Cron accessors**: Added `getCronExpr()`/`setCronExpr()` aliases and dual-column support for `cron_expr` / `cron_expression`.
- **Profile clone safety**: Scrubs secrets and resets lock/retry/next-run runtime state.
- **SystemInfo**: Safe handling when `get_current_user()` is unavailable; shows PHP version.

### Improved
- **Product type strategies**: Configurable/Grouped expand to enabled children; Simple/Virtual/Downloadable/Bundle return sellable offers with metadata.
- **ProductTypeResolver**: Uses type strategy pool and filters disabled products.
- **Sensitive field registry**: Includes delivery private key/passphrase and service account JSON.
- **Admin config**: Added logging/debug toggle group.
- **Docs/tests**: Rewrote README install/security docs; updated unit tests for Save, ProductTypeResolver, and FeedProfileCloner.

## [2.6.0] - 2026-07-31

### Fixed (P0 Security & Correctness Audit)
- **Delivery Adapter Pool DI**: Registered `DeliveryPool` with `local`, `ftp`, and `sftp` adapters in `etc/di.xml`.
- **FeedType vs Extension Validation**: Updated `Save` controller to validate filename extensions (`.xml`, `.csv`, `.jsonl`, `.tsv`, `.txt`) cleanly against channel types.
- **Generate Now Action Parameter**: Updated `FeedActions` grid column to pass `action=run` in trigger URL.
- **Duplicate/Delete Request Protocol**: Enabled dual GET/POST support in `Duplicate` and `Delete` controllers.
- **CLI Generation Exit Codes**: Updated `haerriz:feed:generate` to handle errors gracefully and return exit code 1 (`Command::FAILURE`) on failures.
- **Real Profile Validation Command**: Refactored `haerriz:feed:validate` to load all profiles and run `RowBuilder::validate()`.
- **SearchResults Preferences**: Added search-results preferences in `etc/di.xml` for `FeedProfileSearchResultsInterface`, `FeedJobSearchResultsInterface`, and `FeedLogSearchResultsInterface`.
- **Security Scrub on Duplication**: Scrubbed `delivery_password`, `delivery_private_key`, and `delivery_key_passphrase` in `FeedProfileCloner`.
- **Safe JSON-LD Script Encoding**: Encoded product JSON-LD output with `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP` to prevent script execution breaks.
