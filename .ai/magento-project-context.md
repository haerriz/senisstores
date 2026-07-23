# Magento Project Context - senisstores.com

This file records verified project facts and architecture decisions.

## Verified platform baseline
- **Magento Edition/Version**: Magento Open Source 2.4.7 (Community Edition)
- **PHP Version**: PHP 8.2.31
- **Database**: MySQL 8.0 on localhost (`u434561653_senisstores`)

## Storefront and checkout architecture
- **Storefront**: Custom child theme `Haerriz/Senisstores` extending `Magento/luma`
- **Checkout**: Standard Magento Luma-based Checkout flow
- **Guest Checkout**: Enabled and locked (`checkout/options/guest_checkout = 1`)

## Infrastructure and deployment
- **Host**: Hostinger Shared VPS/Cloud hosting
- **Compiler**: PHP CLI static compilation
- **Cache Management**: Fully cached (Config, Block HTML, FPC, Layout)

## Important modules and integrations
- **Haerriz_SocialLogin**: Custom Google Callback OAuth callback handling
- **Haerriz_GoogleFeed**: XML feed generator for Google Merchant Center (URL: `/googlefeed/feed/index`)
- **Haerriz_QtyUpdate**: Custom Knockout-based quantity updates widget on grids and product templates
- **Razorpay_Magento**: Razorpay payment gateway integration
- **Mageplaza_Smtp**: Custom SMTP configuration for transactional store emails

## Architectural decisions and their reasons
- **Guest Checkout Activation (2026-07-23)**: Enabled Guest Checkout globally in env.php to remove account-creation friction and prevent Cart abandonment.
- **Aligned Action Columns (2026-07-23)**: Redesigned the product grid actions to stack vertically and center secondary icons to eliminate "Add to C..." button clipping and fix mobile column overlap.
