<?php

declare(strict_types=1);

namespace Haerriz\Seo\Setup\Patch\Data;

use Magento\Catalog\Model\CategoryFactory;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Adds useful buying guidance to the strongest live catalogue categories.
 *
 * Empty catalogue branches remain hidden/noindex; this patch deliberately
 * avoids manufacturing doorway copy for ranges that cannot currently be bought.
 */
class StrengthenOrganicMerchandising implements DataPatchInterface
{
    public function __construct(private readonly CategoryFactory $categoryFactory)
    {
    }

    public function apply(): self
    {
        $categories = [
            9 => [
                'Hand Tools for Construction, Repair & Garden Work',
                'Compare practical hand tools for masonry, carpentry, repairs and garden work. Buy online or contact Seni\'s Stores in Theni for stock and bulk guidance.',
                <<<'HTML'
<p>Choose practical hand tools for construction, maintenance, fitting and garden work. The live range includes screwdrivers, steel karni and selected agricultural tools. Each product page shows the current price, stock status, photographs and the available size or handle information.</p>
<h2>How to choose the right hand tool</h2>
<p>Match the working end, size and grip to the job rather than choosing by price alone. For masonry finishing, check the karni shape and handle. For fastening, confirm that the screwdriver tip fits the screw. Clean and dry steel tools after use and store cutting edges safely.</p>
<p>For several small tools, placing compatible items in one order may reduce shipping cost per item compared with separate parcels. The final courier rate is calculated from the packed weight, dimensions and delivery postcode at checkout.</p>
<p><a href="/tools/masonry-tools.html">Browse masonry tools</a> · <a href="/tools/common-tools.html">Browse common tools</a> · <a href="/hardware-shipping-india">Read India shipping guidance</a></p>
HTML,
            ],
            24 => [
                'GI Wire & PVC Coated Wire by Gauge and Quantity',
                'Compare GI wire gauges and PVC coated wire variants for tying, binding and general utility work. Order from Seni\'s Stores in Theni across India.',
                <<<'HTML'
<p>Browse galvanised iron wire and PVC coated wire for suitable tying, binding, fencing and general utility work. Wire gauge, coating and ordered quantity affect handling and suitability, so confirm the intended application before buying.</p>
<h2>Understanding wire choice</h2>
<p>Lower gauge numbers generally indicate thicker wire. A thicker wire is not automatically the correct choice: bendability, corrosion exposure, fastening method and the required finished result also matter. PVC coated variants add an outer protective layer, while GI wire uses a zinc coating.</p>
<p>The product page is the source of truth for the current sale unit, price, stock and shipping weight. For project quantities, call <a href="tel:+919443050753">+91 94430 50753</a> with the gauge, quantity and delivery postcode so the Theni store can confirm availability before dispatch.</p>
<p><a href="/wire-mesh/gi-wire-config.html">Compare GI wire gauges</a> · <a href="/wire-mesh/pvc-coated-wire.html">Shop PVC coated wire</a></p>
HTML,
            ],
            26 => [
                'GI Wire by Gauge: 8G to 24G Options',
                'Shop available galvanised iron wire gauges for suitable tying, binding, fencing and utility work. Current prices and delivery from Seni\'s Stores, Theni.',
                <<<'HTML'
<p>Compare the available galvanised iron wire gauges from Seni's Stores. The online range includes multiple options between 8 gauge and 24 gauge, plus quantity variants where shown. Current price, stock and sale-unit details appear on each product page.</p>
<h2>Gauge selection matters</h2>
<p>Gauge describes wire thickness; a lower gauge number is generally thicker. Select according to the actual tying, binding or fencing task, the required flexibility and the environment. Do not infer a safe load from gauge alone, and obtain appropriate technical advice for structural or safety-critical work.</p>
<p>Wire can become expensive to ship when ordered as isolated small quantities. Combining the required quantity into one correctly packed order can be more efficient, but the final charge depends on courier volumetric or actual weight and postcode serviceability.</p>
HTML,
            ],
            32 => [
                'Aluminium Measuring Cups & Electronic Weighing Machines',
                'Compare aluminium measuring cups from 50ml to 1 litre, a five-piece set and electronic weighing machines. Buy online from Seni\'s Stores, Theni.',
                <<<'HTML'
<p>Compare aluminium measuring cups and electronic weighing machines for suitable kitchen, retail, workshop and mixing tasks. Individual cup capacities include 50ml, 100ml, 200ml, 500ml and 1 litre; a five-piece set is available when several capacities are needed together.</p>
<h2>Choose capacity and load carefully</h2>
<p>For measuring cups, select the capacity that avoids repeated fills while remaining easy to handle. For weighing machines, compare the maximum stated load and platform size. Product photographs and stored dimensions help with selection, but physically verify critical measurements or calibration requirements before professional use.</p>
<p>The five-piece cup set usually provides better parcel efficiency than ordering each capacity separately. Checkout calculates delivery using the packed product weight, carton allowance and destination postcode.</p>
<p><a href="/measuring-cup-set-of-5-combo.html">View the five-piece cup set</a> · <a href="/hardware-shipping-india">How shipping is calculated</a></p>
HTML,
            ],
            33 => [
                'Agricultural & Garden Hand Tools from Theni',
                'Shop spades, crowbars and garden scissors for suitable farm, garden and outdoor work. Online ordering from Seni\'s Stores in Theni, Tamil Nadu.',
                <<<'HTML'
<p>Find hand tools for soil preparation, gardening and general outdoor work, including spades, handled spades, crowbars and garden scissors. Product pages show the available length, handle configuration, price and stock so you can compare before ordering.</p>
<h2>Before choosing an agricultural tool</h2>
<p>Consider the user's height, the working position, the ground condition and the task duration. A longer or heavier tool is not automatically better. Wear appropriate footwear and eye protection, keep bystanders clear and inspect handles and working ends before use.</p>
<p>Long tools can attract dimensional shipping charges. The checkout quote uses packed dimensions and the destination postcode; for several tools or a local Theni district requirement, call <a href="tel:+919443050753">+91 94430 50753</a> before ordering so stock and packing can be confirmed.</p>
HTML,
            ],
            34 => [
                'Repair Sealants, M-Seal & Adhesive Compounds',
                'Compare M-Seal Regular, M-Seal Phataphat and Ezy Seal for compatible small repair work. Current prices from Seni\'s Stores in Theni.',
                <<<'HTML'
<p>Shop compact repair compounds and sealants for compatible household, plumbing and workshop tasks. The current range includes M-Seal Regular 90g, M-Seal Phataphat 25g and Ezy Seal. Product pages show the selling price, availability and package photographs.</p>
<h2>Preparation and curing are important</h2>
<p>Identify the surface material and repair type before choosing a compound. Clean and dry the area, follow the manufacturer instructions printed on the pack, and allow the stated curing time. These products are not substitutes for structural, pressure-rated or safety-critical repairs.</p>
<p>Small packs may be more economical to ship with other compatible hardware in one order. Review the complete checkout total—including delivery—before payment.</p>
HTML,
            ],
            35 => [
                'S-Hooks & Jhola Chains in Multiple Sizes',
                'Compare 2.5-inch, 4-inch and 6-inch S-hooks plus Mini Jhola Chain variants. Buy online from Seni\'s Stores in Theni.',
                <<<'HTML'
<p>Compare S-hooks and Mini Jhola Chain variants for suitable organisation and general utility hanging. Available hook listings include compact 2.5-inch, 4-inch and 6-inch sizes; chain listings include regular and heavy variants.</p>
<h2>Measure both attachment points</h2>
<p>Check the opening, overall size and shape against both attachment points before ordering. Product size alone does not establish a safe working load. Do not use an item for lifting people, overhead safety applications or beyond a confirmed load rating.</p>
<p>For a row of hooks or repeated shop and home organisation, ordering the required quantity together can reduce shipping cost per piece. Current tier pricing, where offered, appears below the product price; the final packed delivery rate is shown at checkout.</p>
<p><a href="/s-hook-2-5-inch-small.html">2.5-inch S-hook</a> · <a href="/mini-jhola-chain-regular.html">Regular Mini Jhola Chain</a></p>
HTML,
            ],
        ];

        foreach ($categories as $categoryId => [$title, $metaDescription, $description]) {
            $category = $this->categoryFactory->create()->setStoreId(1)->load($categoryId);
            if (!$category->getId()) {
                continue;
            }
            $category->setMetaTitle($title)
                ->setMetaDescription($metaDescription)
                ->setDescription($description);
            foreach (['meta_title', 'meta_description', 'description'] as $attribute) {
                $category->getResource()->saveAttribute($category, $attribute);
            }
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [ApplySearchConsoleInsights::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
