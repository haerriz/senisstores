# Changelog

All notable changes to this project will be documented in this file.

## [2.1.0] - 2026-07-29

### Changed
- Migrated Google Merchant API integration from discontinued `v1beta` endpoints to stable `v1`.
- Upgraded `google/shopping-merchant-products` to `^1.7` and `google/shopping-merchant-datasources` to `^1.3`.
- Updated product input mapping to the v1 `ProductAttributes` schema and v1 request-object clients.
- Changed the dynamic feed dispatcher schedule from daily to every five minutes.

### Fixed
- Replaced ineffective page-number collection pagination with entity-ID keyset pagination, preventing infinite duplicate exports.
- Normalized Google feed price, currency, availability, product links, image links, condition, brand, and XML CDATA output.
- Corrected Merchant API client namespaces, OAuth scope, data-source list/create requests, and product input/delete requests.
- Restored production static-content deployment after missing assets caused storefront CSS requests to return 404.

## [Unreleased]
### Added
- `docs/CURRENT_STATE.md` with full architecture audit.
- `docs/PHASE_STATUS.md` to track project evolution.
- `etc/acl.xml` for strict access control across admin resources.
- PHPUnit tests for `MerchantClient` and `Feed/Save` controller to catch security vulnerabilities.
- **Phase 1**: Database declarative schema fully normalized. `store_id` cast to `smallint` with core FK constraint.
- **Phase 1**: Added base queue and log tables (`haerriz_google_shopping_feed_job`, `haerriz_google_shopping_feed_log`).
- **Phase 1**: Introduced full suite of Magento Service Contracts (`Api/Data/` and `Api/`) for strict data type enforcement.
- **Phase 1**: Created proper `Repository` and SearchCriteria implementations.
- **Phase 2**: Secure config reader (`Model/Config`) implemented with memory caching.
- **Phase 2**: Dedicated system logger channel (`var/log/haerriz_googleshoppingfeed.log`) configured via custom Monolog integration.
- **Phase 2**: Enforced strict input validation on Google Merchant Center ID in configuration backend.
- **Phase 3**: Refactored `FeedGenerator` with collection pagination to process catalogs in batch sizes of 500.
- **Phase 3**: Replaced full-string in-memory XML accumulation with structured chunk streaming directly to target files.
- **Phase 3**: Implemented PHPUnit coverage for streaming validation.
- **Phase 4**: Added `delivery_*` columns in `db_schema.xml` to support local, FTP, and SFTP endpoints.
- **Phase 4**: Expanded `FeedProfile` model and interface to support delivery credentials.
- **Phase 4**: Encrypted remote delivery passwords using Magento's core `EncryptorInterface` at the Controller layer.
- **Phase 4**: Implemented dynamic delivery field toggling in Admin Feed Edit UI.
- **Phase 4**: Created modular delivery storage system with `Local`, `Ftp`, and `Sftp` storage adapters and `AdapterPool`.
- **Phase 4**: Leveraged Magento's native `Ftp`/`Sftp` filesystem utilities for reliable file uploading.
- **Phase 5**: Integrated standard Magento Rule models (`Magento\Rule\Model\AbstractModel`) to support recursive catalog rules.
- **Phase 5**: Created custom admin UI conditions block `Tab\Conditions` to load standard Magento rule selection UI templates.
- **Phase 5**: Added AJAX conditions controller (`Controller/Adminhtml/Feed/Conditions`) to dynamically fetch condition lists.
- **Phase 5**: Wired conditions saving/serialization inside Save controller.
- **Phase 5**: Hooked rule validations directly into paginated product collections during CSV/XML feed generations.
- **Phase 7**: Added scheduling columns to declarative schema (`db_schema.xml`).
- **Phase 7**: Built dynamic timezone-aware `Scheduler` tool computing next run timestamps for hourly/daily/weekly/monthly/custom cron tasks.
- **Phase 7**: Implemented robust dynamic scheduling locks, concurrency rules, stale job recovery, retries, and failure auto-disabling.
- **Phase 7**: Merged execution status and error tracking directly into the db logger tables (`haerriz_google_shopping_feed_log`).
- **Phase 7**: Built execution logs custom handler (`Model/FeedLogHandler.php`) and dynamic admin grid component (`haerriz_googleshoppingfeed_job_listing.xml`).
- **Phase 7**: Developed Schedule actions controller (`Trigger.php`) to execute manual runs/enables/disables directly from the admin workspace.
- **Phase 9**: Required official PHP SDKs `google/shopping-merchant-products` and `google/shopping-merchant-datasources` in `composer.json`.
- **Phase 9**: Created dynamic OAuth2 service client wrapper `MerchantClientV1.php` using ServiceAccount credentials.
- **Phase 9**: Created dynamic datasource provisions locator `DataSourceManager.php` using primary API data sources.
- **Phase 9**: Developed `ProductSynchronizer.php` converting Magento products to `ProductInput` resources with patch mask updates and deletes.
- **Phase 9**: Implemented exponential backoff with random jitter for transient errors in `ErrorHandler.php`.
- **Phase 9**: Built `StatusReconciliation.php` to fetch issues and disapprovals directly from Google Merchant Center APIs.
- **Phase 9**: Removed legacy dummy sync client file `MerchantClient.php`.
- **Phase 10**: Added tracking metadata columns (trigger_source, exported_count, skipped_count, file_size, duration, checksums) to `db_schema.xml`.
- **Phase 10**: Developed secure log `Sanitizer.php` redacting passwords and service account keys.
- **Phase 10**: Created `JobManager.php` allowing manual cancellation and safe cron retention cleanups preserving active/latest successful files.
- **Phase 10**: Updated `FeedGenerator.php` to calculate and populate job run counters, size, and MD5 file checksums.
- **Phase 10**: Updated UI layout component `haerriz_googleshoppingfeed_job_listing.xml` to expose metrics and counts.

### Changed
- `composer.json` dependencies locked to specific Magento `~103.0`/`~104.0` framework versions.
- `etc/adminhtml/system.xml`: Swapped Service Account JSON textarea for `obscure` encrypted backend model.
- Admin Feed Form UI component now requires `store_id` and `currency`.

### Fixed
- Directory path traversal vulnerability in `Controller/Adminhtml/Feed/Save.php` blocked via `basename` parsing.
- Cron `GenerateFeeds.php` false-positive `dummy_sync` removed; replaced with informational logging.
- API Client stub now actively throws `LocalizedException` instead of falsely simulating successful API push.
