# Changelog - Haerriz_GoogleShoppingFeed

All notable changes to the `Haerriz_GoogleShoppingFeed` Magento 2 module are documented here.

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
