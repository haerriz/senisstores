<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Store\Model\StoreManagerInterface;
use Haerriz\GoogleShoppingFeed\Model\Modifier\Pool as ModifierPool;

class FeedGenerator
{
    protected $productCollectionFactory;
    protected $filesystem;
    protected $storeManager;
    protected $modifierPool;

    public function __construct(
        ProductCollectionFactory $productCollectionFactory,
        Filesystem $filesystem,
        StoreManagerInterface $storeManager,
        ModifierPool $modifierPool
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->filesystem = $filesystem;
        $this->storeManager = $storeManager;
        $this->modifierPool = $modifierPool;
    }

    public function generate(FeedProfileInterface $profile)
    {
        $storeId = $profile->getStoreId();
        $this->storeManager->setCurrentStore($storeId);

        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('*');
        $collection->addStoreFilter($storeId);

        // Apply filters based on profile conditions
        $this->applyFilters($collection, $profile);

        $type = $profile->getFeedType();
        $filename = $profile->getFilename();

        if ($type === 'csv') {
            return $this->generateCsv($collection, $profile, $filename);
        } else {
            return $this->generateXml($collection, $profile, $filename);
        }
    }

    protected function applyFilters($collection, $profile)
    {
        // Add basic filters (in stock, enabled, visible)
        $collection->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
        $collection->addAttributeToFilter('visibility', ['neq' => \Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE]);
        
        // Custom rule processing would go here
    }

    protected function generateCsv($collection, $profile, $filename)
    {
        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $stream = $directory->openFile('google_feed/' . $filename, 'w+');
        $stream->lock();

        $mapping = json_decode($profile->getAttributesMappingSerialized(), true) ?? [];
        
        // Headers
        $headers = [];
        foreach ($mapping as $map) {
            $headers[] = $map['google_attribute'];
        }
        $stream->writeCsv($headers);

        // Rows
        foreach ($collection as $product) {
            $row = [];
            foreach ($mapping as $map) {
                $value = $product->getData($map['magento_attribute']);
                $value = $this->applyModifier($value, $map['modifier'] ?? '', $product);
                $row[] = $value;
            }
            $stream->writeCsv($row);
        }

        $stream->unlock();
        $stream->close();
        return true;
    }

    protected function generateXml($collection, $profile, $filename)
    {
        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        
        $xmlContent = '<?xml version="1.0"?>' . "\n";
        $xmlContent .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
        $xmlContent .= '<channel>' . "\n";
        $xmlContent .= '<title><![CDATA[' . $profile->getName() . ']]></title>' . "\n";
        $xmlContent .= '<link><![CDATA[' . $this->storeManager->getStore()->getBaseUrl() . ']]></link>' . "\n";
        
        $mapping = json_decode($profile->getAttributesMappingSerialized(), true) ?? [];
        
        foreach ($collection as $product) {
            $xmlContent .= "  <item>\n";
            foreach ($mapping as $map) {
                $googleTag = $map['google_attribute'];
                $value = $product->getData($map['magento_attribute']);
                $value = $this->applyModifier($value, $map['modifier'] ?? '', $product);
                
                // Skip empty values unless required
                if ($value !== null && $value !== '') {
                    $xmlContent .= "    <{$googleTag}><![CDATA[{$value}]]></{$googleTag}>\n";
                }
            }
            $xmlContent .= "  </item>\n";
        }
        
        $xmlContent .= '</channel>' . "\n";
        $xmlContent .= '</rss>';

        $directory->writeFile('google_feed/' . $filename, $xmlContent);
        return true;
    }

    protected function applyModifier($value, $modifierCode, $product)
    {
        return $this->modifierPool->apply($value, $modifierCode, $product);
    }
}
