<?php
declare(strict_types=1);

namespace Haerriz\Seo\Setup\Patch\Data;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Improves pages that already earn impressions, using Search Console evidence.
 *
 * Keep this deliberately small: it is better to strengthen proven landing pages
 * than generate near-duplicate pages for every query or nearby town.
 */
class ApplySearchConsoleInsights implements DataPatchInterface
{
    public function __construct(
        private readonly CollectionFactory $productCollectionFactory
    ) {}

    public function apply(): self
    {
        $copy = [
            'birla-white-portland-cement-1kg' => [
                'Birla White Cement 1kg Price & Online Order | Seni\'s Stores',
                'Check the current Birla White Cement 1kg price and order online from Seni\'s Stores, Theni. Suitable for small finishing, patching and decorative work.',
                '<p>Birla White Cement 1kg is a convenient pack for small finishing, patching and decorative applications where a full-size bag is unnecessary. The current selling price and stock status are shown on this page.</p><h2>Using and storing the 1kg pack</h2><p>Keep the unopened pack dry, prepare a clean surface and follow the manufacturer\'s water ratio and application instructions printed on the pack. Actual coverage depends on the surface, mix and thickness.</p><p>Order online for eligible postcode-based delivery across India, or contact Seni\'s Stores in Theni for stock and larger quantities.</p>',
            ],
            'focus-screw-driver-521' => [
                'Focus 521 Screwdriver Price & Details | Seni\'s Stores',
                'Buy the Focus 521 screwdriver online from Seni\'s Stores, Theni. Check the current price, tip compatibility, stock and postcode-based delivery availability.',
                '<p>The Focus 521 screwdriver is intended for routine fastening and repair work. Check that the tip profile and size match the screw before applying force; using the wrong tip can damage both the fastener and tool.</p><h2>Before ordering</h2><p>Review the product image and current price on this page. For repeated trade use or bulk requirements, contact our Theni store to confirm suitability and available quantity.</p>',
            ],
            'mseal-regular-90g' => [
                'M-Seal Regular 90g Price & Online Order | Seni\'s Stores',
                'Check M-Seal Regular 90g price and availability. Order the two-part epoxy repair compound online from Seni\'s Stores in Theni for eligible India delivery.',
                '<p>M-Seal Regular 90g is a two-part epoxy repair compound for compatible household, workshop and plumbing repairs. The price and current availability are displayed on this page.</p><h2>Application guidance</h2><p>Clean and dry the surface, mix only as directed on the manufacturer pack and allow the stated curing time. Confirm material compatibility before use; this product is not a substitute for a structural repair.</p>',
            ],
            '18-g-gi-wire' => [
                '18 Gauge GI Wire Price & Online Order | Seni\'s Stores',
                'Check 18 gauge GI wire price and order online from Seni\'s Stores, Theni. For tying, binding and general utility work with delivery to eligible postcodes.',
                '<p>18 gauge galvanised iron wire is a practical option for suitable tying, binding and general utility work. The current price and sale unit are shown on this page.</p><h2>Selecting GI wire</h2><p>Gauge affects wire thickness and handling. Confirm the required gauge, quantity and intended application before ordering, especially for fencing, load-bearing or bulk work.</p>',
            ],
            '50ml-aluminium-measuring-cup' => [
                '50ml Aluminium Measuring Cup Price | Seni\'s Stores',
                'Buy a 50ml aluminium measuring cup online from Seni\'s Stores, Theni. Check current price, dimensions, stock and eligible delivery across India.',
                '<p>This compact 50ml aluminium measuring cup is useful for repeatable small-volume measuring in suitable kitchen, retail, workshop and mixing tasks. The current price, stock and available product dimensions are shown on this page.</p><p>For multiple capacities, compare the individual cups with our five-piece aluminium measuring cup set.</p>',
            ],
            '500ml-aluminium-measuring-cup' => [
                '500ml Aluminium Measuring Cup Price | Seni\'s Stores',
                'Buy a 500ml aluminium measuring cup online from Seni\'s Stores, Theni. View current price, dimensions, stock and eligible India delivery.',
                '<p>This 500ml aluminium measuring cup provides a practical half-litre capacity for suitable kitchen, retail, workshop and mixing tasks. Check the current price, stock and product dimensions on this page before ordering.</p><p>For several capacities in one purchase, compare our five-piece aluminium measuring cup set.</p>',
            ],
            '1-ltr-aluminium-measuring-cup' => [
                '1 Litre Aluminium Measuring Cup Price | Seni\'s Stores',
                'Buy a 1 litre aluminium measuring cup online from Seni\'s Stores, Theni. Check current price, dimensions, stock and postcode-based India delivery.',
                '<p>This 1 litre aluminium measuring cup is suited to repeatable larger-volume measuring for compatible kitchen, retail, workshop and mixing tasks. The current price, availability and product dimensions are displayed on this page.</p><p>Choose the capacity that matches the task and physically verify critical measurements where process accuracy is required.</p>',
            ],
        ];

        $products = $this->productCollectionFactory->create()
            ->setStoreId(1)
            ->addAttributeToSelect(['url_key', 'meta_title', 'meta_description', 'description']);

        foreach ($products as $product) {
            $urlKey = (string)$product->getUrlKey();
            if (!isset($copy[$urlKey])) {
                continue;
            }
            [$title, $metaDescription, $description] = $copy[$urlKey];
            $product->setMetaTitle($title)
                ->setMetaDescription($metaDescription)
                ->setDescription($description);
            foreach (['meta_title', 'meta_description', 'description'] as $attribute) {
                $product->getResource()->saveAttribute($product, $attribute);
            }
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [FinalizeCatalogSeo::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
