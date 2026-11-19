<?php
declare(strict_types=1);

namespace Haerriz\Seo\Setup\Patch\Data;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Cms\Model\PageFactory;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class ImproveSeo implements DataPatchInterface
{
    public function __construct(
        private readonly WriterInterface $configWriter,
        private readonly PageFactory $pageFactory,
        private readonly CollectionFactory $productCollectionFactory
    ) {}

    public function apply(): self
    {
        foreach ([
            'design/head/includes' => '',
            'design/head/title_prefix' => '',
            'design/head/title_suffix' => '',
            'design/head/default_title' => "Hardware Store in Theni & Online India | Seni's Stores",
            'design/head/default_description' => "Shop hardware, tools, measuring cups, hooks, wire and construction supplies from Seni's Stores in Theni, with delivery across India.",
            'design/head/default_keywords' => '',
            'catalog/seo/product_canonical_tag' => '1',
            'catalog/seo/category_canonical_tag' => '1',
        ] as $path => $value) {
            $this->configWriter->save($path, $value, 'stores', 1);
        }

        $page = $this->pageFactory->create()->load('home', 'identifier');
        if ($page->getId()) {
            $page->setTitle("Hardware Store in Theni & Online India | Seni's Stores")
                ->setMetaTitle("Hardware Store in Theni & Online India | Seni's Stores")
                ->setMetaKeywords('')
                ->setMetaDescription("Buy hardware, tools, measuring cups, hooks, wire and construction supplies from Seni's Stores, Theni. Retail, bulk orders and delivery across India.")
                ->setContent($this->homeContent())
                ->save();
        }

        $products = $this->productCollectionFactory->create()
            ->addAttributeToSelect(['name', 'meta_title', 'meta_description']);
        foreach ($products as $product) {
            $name = trim((string)$product->getName());
            if ($name === '') {
                continue;
            }
            $title = $this->truncate("Buy {$name} Online in India | Seni's Stores", 60);
            $description = $this->truncate(
                "Buy {$name} online from Seni's Stores, Theni. Genuine hardware and supplies with secure checkout and delivery across India.",
                155
            );
            $product->setMetaTitle($title)->setMetaDescription($description)->getResource()->saveAttribute($product, 'meta_title');
            $product->getResource()->saveAttribute($product, 'meta_description');
        }

        return $this;
    }

    private function homeContent(): string
    {
        return <<<'HTML'
<section class="senis-local-intro">
<h1>Hardware Store in Theni with Delivery Across India</h1>
<p>Seni's Stores supplies dependable hardware and project essentials for homes, tradespeople and businesses. Shop measuring cups, S-hooks, GI and PVC wire, hand tools, cement and other construction supplies online, or visit our store on Periyakulam Main Road, Theni.</p>
<p>We serve customers across Theni, Allinagaram, Periyakulam, Bodinayakanur, Cumbum, Uthamapalayam and Andipatti, and ship eligible products throughout India. For product guidance, availability or a bulk-order quote, call <a href="tel:+919443050753" class="contact-phone-link">+91 94430 50753</a>.</p>
<ul class="senis-store-benefits"><li>Retail and bulk orders</li><li>Shipping calculated from destination and packed dimensions</li><li>Secure online checkout</li><li>Local help from our Theni store</li></ul>
</section>
<section class="senis-location">
<h2>Visit Seni's Stores in Theni</h2>
<p><strong>1129A, opposite Edamaal Street, Periyakulam Main Road, Theni, Tamil Nadu 625531</strong><br>Open daily, 9:00 AM–9:00 PM.</p>
<div style="text-align:center;margin:auto;padding:20px 0"><iframe loading="lazy" style="width:100%;height:450px;max-width:100%;border:0" src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d1066.5089927482327!2d77.47669350617157!3d10.011618403695248!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3b0714af163af169%3A0xb5832480e2e7af1d!2sSeni%20S%20Stores!5e0!3m2!1sen!2sin!4v1706251691966!5m2!1sen!2sin" allowfullscreen title="Seni's Stores location in Theni"></iframe></div>
</section>
HTML;
    }

    private function truncate(string $value, int $limit): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }
        return rtrim(mb_substr($value, 0, $limit - 1), " ,.-|") . '…';
    }

    public static function getDependencies(): array { return []; }
    public function getAliases(): array { return []; }
}
