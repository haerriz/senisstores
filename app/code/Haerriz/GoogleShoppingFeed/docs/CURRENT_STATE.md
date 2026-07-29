# Current State Audit (Phase 0)

## Existing Architecture
The current module (`Haerriz_GoogleShoppingFeed` version 1.0.0) is a prototype covering Phase 1-3. It utilizes a `FeedProfile` model to store configurations, a basic `FeedGenerator` to produce XML/CSV strings in-memory, a stubbed `MerchantClient` for Google API integration, and a simple `Rule/Condition.php` class for evaluating dynamic rules.

## Existing Database Schema
- **Table**: `haerriz_google_shopping_feed_profile`
- **Columns**: `profile_id` (int), `name` (varchar), `status` (smallint), `store_id` (varchar), `filename` (varchar), `feed_type` (varchar), `conditions_serialized` (text), `attributes_mapping_serialized` (text), `created_at` (timestamp), `updated_at` (timestamp).
- **Issue**: `store_id` is improperly defined as a `varchar` instead of a smallint/int, but migration will happen in Phase 1.

## Implemented Functionality
- Basic CRUD for Feed Profiles in Admin via UI Components.
- A functional Modifier Pool (e.g. `StripTags`, `RoundPrice`).
- Magento cron job setup (currently runs at 2 AM daily for all active profiles).

## Stubbed Functionality
- **Google Merchant API**: `MerchantClient` performs no actual HTTP requests. It stubs the authentication and pretends to succeed.
- **Product Filtering**: `conditions_serialized` is not correctly applied during product collection loading.
- **Cron Generation**: The cron runs `insertProduct(['dummy_sync' => true])`.

## Compilation & Runtime Risks
- `FeedGenerator` loads all product attributes and accumulates the entire XML output in memory, causing fatal out-of-memory errors on large catalogs.
- No XML tag/element validation, which can break the feed if users map invalid characters.

## Security Risks
- **ACL**: Completely missing. Any admin user can access the feed configurations.
- **Credentials**: The Google Service Account JSON is stored in plaintext in the database via `system.xml` text fields.
- **Path Traversal**: No backend validation on the `filename` input, theoretically allowing file writes outside the intended directory.

## Migration Risks
- Profiles saved in 1.0.0 have string-based `store_id` and simple serialized mappings which must be migrated cleanly when normalizing the schema in Phase 1.

## Feature Gap Matrix
| Feature | State | Required by v2.0 |
|---------|-------|------------------|
| Unlimited Profiles | Yes | Yes |
| Encrypted Secrets | No | Yes |
| History / Logs | No | Yes |
| FTP / SFTP | No | Yes |
| True Merchant API | No | Yes |
| Granular Schedule | No | Yes |
| Multi-process / Streaming | No | Yes |
| Validated UI Forms | No (Missing store_id) | Yes |
