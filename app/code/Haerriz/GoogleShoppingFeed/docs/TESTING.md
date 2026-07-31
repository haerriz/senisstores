# Testing & Verification Guide - Haerriz_GoogleShoppingFeed

## 1. Unit Tests
Run unit tests with PHPUnit:
```bash
vendor/bin/phpunit -c app/code/Haerriz/GoogleShoppingFeed/phpunit.xml
```

## 2. CLI Validation
Validate all feed profile definitions:
```bash
php bin/magento haerriz:feed:validate
```

## 3. Feed Generation Test
Generate feed for a specific profile ID:
```bash
php bin/magento haerriz:feed:generate --profile=1
```
