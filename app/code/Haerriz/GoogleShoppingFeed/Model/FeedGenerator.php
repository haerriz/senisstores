<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Store\Model\StoreManagerInterface;

class FeedGenerator
{
    protected $productCollectionFactory;
    protected $filesystem;
    protected $storeManager;

    public function __construct(
        ProductCollectionFactory $productCollectionFactory,
        Filesystem $filesystem,
        StoreManagerInterface $storeManager
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->filesystem = $filesystem;
        $this->storeManager = $storeManager;
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
                $value = $this->applyModifier($value, $map['modifier'] ?? '');
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
        // XML Generation Logic
        return true;
    }

    protected function applyModifier($value, $modifier)
    {
        if (!$value) return '';
        switch ($modifier) {
            case 'strip_tags':
                return strip_tags($value);
            case 'round_price':
                return round((float)$value, 2);
            case 'uppercase':
                return strtoupper($value);
            default:
                return $value;
        }
    }
}
