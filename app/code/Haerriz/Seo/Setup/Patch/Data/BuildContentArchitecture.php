<?php
declare(strict_types=1);

namespace Haerriz\Seo\Setup\Patch\Data;

use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Cms\Model\PageFactory;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class BuildContentArchitecture implements DataPatchInterface
{
    public function __construct(
        private readonly CategoryFactory $categoryFactory,
        private readonly CollectionFactory $productCollectionFactory,
        private readonly PageFactory $pageFactory
    ) {}

    public function apply(): self
    {
        $this->updateCategories();
        $this->updatePriorityProducts();
        $this->upsertLandingPages();
        return $this;
    }

    private function updateCategories(): void
    {
        $content = [
            9 => ['Tools for Construction & Repairs', 'Shop hand tools for masonry, carpentry, gardening and everyday repairs. Order online across India or get local product guidance from our Theni hardware store.', '<p>Browse practical hand tools for construction, repair, carpentry and garden work. Compare the product size and intended use before ordering, and contact our Theni team for bulk requirements.</p><p>Popular sections: <a href="/tools/masonry-tools.html">masonry tools</a> and <a href="/tools/common-tools.html">common tools</a>.</p>'],
            10 => ['Hardware Products Online & in Theni', 'Buy hooks, chains, sealants and everyday hardware from Seni\'s Stores in Theni, with secure checkout and delivery across India.', '<p>Shop frequently used hardware for homes, workshops, shops and construction sites. Our current online range includes <a href="/hardware-products/hooks-chains.html">hooks and chains</a> and <a href="/hardware-products/sealants-adhesives.html">sealants and adhesives</a>.</p>'],
            19 => ['Carpentry Tools', 'Shop selected carpentry and general-purpose tools online from Seni\'s Stores, Theni, with delivery across India.', '<p>Selected tools for fitting, fastening and routine woodwork. Check the product page for the current model and availability.</p>'],
            21 => ['Masonry Tools', 'Shop masonry tools including karni and screwdrivers from Seni\'s Stores in Theni. Retail and bulk enquiries welcome.', '<p>Tools for plastering, masonry finishing and construction-site tasks. Choose by handle, working edge and intended job; call our Theni store when you need help selecting an item.</p>'],
            22 => ['Common Hand Tools', 'Find useful hand tools for home, workshop and site work. Order from Seni\'s Stores, Theni, for delivery across India.', '<p>Everyday tools for maintenance, fixing and site work. Product pages show current pricing and availability.</p>'],
            24 => ['GI & PVC Coated Wire Online', 'Buy GI wire and PVC coated wire in multiple gauges and variants from Seni\'s Stores, Theni. Shipping available across India.', '<p>Choose wire for tying, binding, fencing and general fabrication work. Browse <a href="/wire-mesh/gi-wire-config.html">GI wire gauges</a> or <a href="/wire-mesh/pvc-coated-wire.html">PVC coated wire</a>. Gauge, coating and quantity affect suitability, so confirm requirements before bulk orders.</p>'],
            26 => ['GI Wire by Gauge', 'Shop galvanised iron wire in 8g to 24g options for binding, fencing and general work. Delivery from Theni across India.', '<p>Galvanised iron wire is available in multiple gauges. Lower gauge numbers are generally thicker; select the gauge based on the tying, binding or fencing job rather than price alone.</p><p>Need a bulk quantity or a particular gauge? Call <a href="tel:+919443050753">+91 94430 50753</a> before ordering.</p>'],
            28 => ['PVC Coated Wire', 'Buy PVC coated wire for protected tying, fencing and general-purpose applications from Seni\'s Stores, Theni.', '<p>PVC coated wire adds a protective outer layer for handling and weather exposure. Confirm colour, thickness and available roll or cut quantity with the store for project orders.</p>'],
            30 => ['Screwdrivers Online', 'Shop screwdrivers for routine repair, fitting and construction tasks from Seni\'s Stores in Theni.', '<p>Browse available screwdriver models and check the tip, length and grip on the product page before ordering.</p>'],
            32 => ['Measuring Cups & Weighing Machines', 'Shop aluminium measuring cups, measuring cup sets and electronic weighing machines online across India from Theni.', '<p>Accurate measuring and weighing products for kitchens, retail counters, workshops and mixing tasks. Compare capacity, maximum load and platform size before purchase.</p><p>Start with the <a href="/measuring-cup-set-of-5-combo.html">five-piece aluminium measuring cup set</a> or browse individual cup capacities.</p>'],
            33 => ['Agricultural & Garden Hand Tools', 'Buy spades, crowbars and garden scissors online from Seni\'s Stores, Theni, serving farms and homes across India.', '<p>Hand tools for soil work, gardening and general outdoor jobs. Product pages state the available size or handle configuration; select based on the user and task.</p>'],
            34 => ['Sealants & Repair Adhesives', 'Shop M-Seal, Ezy Seal and repair compounds online from Seni\'s Stores in Theni, with delivery across India.', '<p>Repair compounds and sealants for small household, plumbing and workshop jobs. Follow the manufacturer instructions on the pack and prepare surfaces before application.</p>'],
            35 => ['S-Hooks & Jhola Chains', 'Buy S-hooks in multiple sizes and Jhola chains online from Seni\'s Stores, Theni. Retail and bulk orders available.', '<p>Hooks and hanging chains for organised storage and general utility use. Compare hook length and load suitability before use; do not use a product beyond its confirmed rated application.</p><p>Available sizes include <a href="/s-hook-2-5-inch-small.html">2.5-inch</a>, 4-inch and 6-inch S-hooks.</p>'],
        ];
        foreach ($content as $id => [$title, $description, $body]) {
            $category = $this->categoryFactory->create()->setStoreId(1)->load($id);
            if (!$category->getId()) { continue; }
            $category->setMetaTitle($title)->setMetaDescription($description)->setDescription($body)->save();
        }

        foreach (range(3, 35) as $id) {
            if (isset($content[$id])) { continue; }
            $category = $this->categoryFactory->create()->setStoreId(1)->load($id);
            if ($category->getId() && $category->getProductCollection()->addAttributeToFilter('status', 1)->getSize() === 0) {
                $category->setIncludeInMenu(false)->save();
            }
        }
    }

    private function updatePriorityProducts(): void
    {
        $copy = [
            'Focus Screw Driver 521' => ['Focus Screw Driver 521 for Everyday Repairs', 'Focus Screw Driver 521 for fastening and routine repair work. Check tip compatibility before use. Available online from Seni\'s Stores, Theni.'],
            'Birla White Portland cement 1kg' => ['Birla White Cement 1kg for Small Repairs', 'Birla White Cement in a convenient 1kg pack for small surface, finishing and repair jobs. Store dry and follow the manufacturer mixing instructions.'],
            'Birla White Portland cement 5kg' => ['Birla White Cement 5kg for Finishing Work', 'Birla White Cement 5kg pack for finishing, decorative and repair applications. Keep the pack dry and use according to manufacturer directions.'],
            'Measuring cup set of 5 combo' => ['5-Piece Aluminium Measuring Cup Set', 'A set of five aluminium measuring cups—50ml, 100ml, 200ml, 500ml and 1 litre—for repeatable liquid measurement in kitchens, shops and workshops.'],
            'Garden scissors ' => ['Garden Scissors for Pruning and Home Gardening', 'General-purpose garden scissors for trimming and routine plant care. Clean and dry the blades after use, and keep the cutting edge away from children.'],
            'mseal-phataphat-25g' => ['M-Seal Phataphat 25g Quick Repair Compound', 'M-Seal Phataphat 25g is a quick-setting epoxy compound for suitable small repair jobs. Prepare the surface and follow the instructions printed on the pack.'],
            'mseal-regular-90g' => ['M-Seal Regular 90g Epoxy Repair Compound', 'M-Seal Regular 90g is a two-part epoxy compound for suitable sealing and repair work. Mix and apply only as directed on the manufacturer packaging.'],
            'ezy-seal' => ['Ezy Seal Repair Sealant', 'Ezy Seal for suitable small sealing and repair applications. Check surface compatibility and follow all preparation and curing directions on the package.'],
            'mini-jhola-chain-heavy' => ['Mini Jhola Chain – Heavy', 'Heavy variant of the Mini Jhola Chain for suitable hanging and utility applications. Confirm dimensions and intended load with the store before use.'],
            'mini-jhola-chain-regular' => ['Mini Jhola Chain – Regular', 'Regular Mini Jhola Chain for light utility and hanging applications. Confirm the required length and suitable load before ordering.'],
            'steel-karni-pvc-handle' => ['Steel Karni with PVC Handle and Rubber Grip', 'Steel karni with a PVC handle and rubber grip for masonry and plaster finishing work. Keep the working edge clean and dry after use.'],
            'shook-2-5-inch-small' => ['2.5-Inch Small S-Hook', 'Compact 2.5-inch S-hook for suitable light hanging and organisation tasks. Confirm material, fit and safe working load for your application.'],
            'shook-4-inch-large' => ['4-Inch Large S-Hook', 'Four-inch S-hook for suitable storage and utility hanging. Check the attachment points and safe load requirements before use.'],
            'shook-4-inch-large-spl' => ['4-Inch Large Special S-Hook', 'Special 4-inch S-hook variant for suitable hanging and organisation tasks. Confirm shape, fit and load suitability before ordering.'],
            'shook-6-inch-xl' => ['6-Inch XL S-Hook', 'Extra-large 6-inch S-hook for suitable utility hanging. Verify the mounting points and safe working load for the intended application.'],
        ];
        $products = $this->productCollectionFactory->create()->addAttributeToSelect(['sku','name']);
        foreach ($products as $product) {
            $key = (string)$product->getSku();
            if (!isset($copy[$key])) { continue; }
            [$title, $description] = $copy[$key];
            $body = '<p>' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</p><p>Order online for eligible delivery across India, or contact Seni\'s Stores in Theni for stock and bulk-order guidance.</p>';
            $product->setMetaTitle($title . ' | Seni\'s Stores')
                ->setMetaDescription(mb_substr($description . ' Buy online from Seni\'s Stores, Theni.', 0, 155))
                ->setShortDescription($description)
                ->setDescription($body);
            foreach (['meta_title','meta_description','short_description','description'] as $attribute) {
                $product->getResource()->saveAttribute($product, $attribute);
            }
        }
    }

    private function upsertLandingPages(): void
    {
        $pages = [
            'hardware-delivery-theni-district' => [
                'Hardware Delivery Across Theni District',
                'Hardware Delivery in Theni District | Seni\'s Stores',
                'Order eligible hardware and tools from Seni\'s Stores for delivery across Theni, Periyakulam, Bodinayakanur, Cumbum, Uthamapalayam and Andipatti.',
                <<<'HTML'
<h1>Hardware Delivery Across Theni District</h1>
<p>Seni's Stores is based at 1129A Periyakulam Main Road, Theni. Customers can shop online for eligible hardware, measuring products, wire, hooks, repair compounds and hand tools, with shipping calculated from the destination postcode and packed parcel dimensions.</p>
<h2>Areas we serve</h2>
<p>We accept orders from Theni and Allinagaram, Periyakulam, Bodinayakanur, Cumbum, Uthamapalayam and Andipatti, as well as surrounding villages where the selected courier provides service. Delivery price and availability are confirmed at checkout after entering the six-digit postcode.</p>
<h2>How local ordering works</h2><ol><li>Select the product and quantity.</li><li>Enter the destination postcode at checkout.</li><li>Review the live shipping option and total.</li><li>For bulk quantities or stock confirmation, call <a href="tel:+919443050753">+91 94430 50753</a>.</li></ol>
<h2>Frequently asked questions</h2><h3>Do you deliver throughout Theni district?</h3><p>We ship eligible products to serviceable postcodes. Availability and price are calculated during checkout.</p><h3>Can I place a bulk order?</h3><p>Yes. Call the store before ordering so we can confirm quantity, packed size and shipping options.</p><h3>Can I collect from the Theni shop?</h3><p>Contact the store first to confirm the item is ready and discuss collection.</p>
<p><a href="/hardware-products.html">Shop hardware</a> · <a href="/tools.html">Shop tools</a> · <a href="/wire-mesh.html">Shop wire</a></p>
HTML
            ],
            'hardware-shipping-india' => [
                'Hardware Shipping Across India',
                'Buy Hardware Online Across India | Seni\'s Stores',
                'Buy eligible hardware, measuring cups, hooks, wire and tools online from Seni\'s Stores in Theni, with postcode-based shipping across India.',
                <<<'HTML'
<h1>Buy Hardware Online with Shipping Across India</h1><p>Seni's Stores ships eligible products from Theni, Tamil Nadu to serviceable postcodes across India. The store focuses on practical hardware and tools that can be packed safely for courier delivery.</p>
<h2>What you can order online</h2><p>Browse <a href="/metric.html">measuring cups and weighing machines</a>, <a href="/hardware-products/hooks-chains.html">S-hooks and chains</a>, <a href="/wire-mesh/gi-wire-config.html">GI wire</a>, <a href="/hardware-products/sealants-adhesives.html">repair compounds</a> and <a href="/agri.html">garden and agricultural hand tools</a>.</p>
<h2>Shipping is calculated for the real parcel</h2><p>Checkout uses the destination postcode, product weight and packed dimensions. Packaging allowance and box weight are included so the displayed shipping quote better reflects the parcel handed to the courier.</p>
<h2>Frequently asked questions</h2><h3>Is every product available for nationwide delivery?</h3><p>Availability depends on stock, parcel dimensions and courier serviceability. Checkout shows the available option for the entered postcode.</p><h3>How can I reduce shipping cost?</h3><p>Combining compatible items may be more efficient than separate orders, but the final quote depends on packed size and chargeable weight.</p><h3>How do I confirm stock before a large order?</h3><p>Call <a href="tel:+919443050753">+91 94430 50753</a> with the product name, quantity and delivery postcode.</p>
HTML
            ],
        ];
        foreach ($pages as $identifier => [$title,$metaTitle,$metaDescription,$content]) {
            $page = $this->pageFactory->create()->load($identifier, 'identifier');
            $page->setIdentifier($identifier)->setTitle($title)->setMetaTitle($metaTitle)->setMetaKeywords('')->setMetaDescription($metaDescription)->setContentHeading('')->setContent($content)->setIsActive(true)->setPageLayout('1column')->setStores([1])->save();
        }
    }

    public static function getDependencies(): array { return [CorrectPhoneNumber::class]; }
    public function getAliases(): array { return []; }
}
