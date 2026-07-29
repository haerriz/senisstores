# Haerriz Google Shopping Feed for Magento 2

A robust, enterprise-grade Magento 2 module for generating Google Shopping Feeds and synchronizing products directly with Google Merchant Center via the Merchant API.

## Features
- **Dynamic Attribute Mapping**: Infinite dynamic rows to map Magento attributes to Google attributes.
- **Google Taxonomy Mapping**: Effortlessly categorize your products according to Google's strict taxonomy requirements.
- **Enterprise Modifiers**: Modify feed output with an extensible Modifier Pool (e.g., Strip HTML Tags, Round Prices, Tax Calculation).
- **Merchant API Integration**: Uses OAuth Service Accounts to communicate directly with Google Merchant Center.
- **Automated Cron Jobs**: Automatically generates and syncs feeds silently in the background.

## Installation via Composer
```bash
composer require haerriz/module-google-shopping-feed
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:clean
```

## Configuration
1. Go to **Magento Admin > Haerriz Google Feed > Manage Feeds**.
2. Click **Add New Feed** to configure your mapping and conditions.
3. Ensure your Google Merchant Center ID and Service Account JSON are configured.

## License
MIT License. Free and Open Source.
