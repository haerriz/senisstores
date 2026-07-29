<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Store\Model\StoreManagerInterface;
use Haerriz\GoogleShoppingFeed\Model\Modifier\Pool as ModifierPool;

use Haerriz\GoogleShoppingFeed\Model\Storage\AdapterPool;

use Haerriz\GoogleShoppingFeed\Model\RuleFactory;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedJob as JobResource;

class FeedGenerator
{
    const BATCH_SIZE = 500;

    /**
     * @var ProductCollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ModifierPool
     */
    protected $modifierPool;

    /**
     * @var AdapterPool
     */
    protected $adapterPool;

    /**
     * @var RuleFactory
     */
    protected $ruleFactory;

    /**
     * @param ProductCollectionFactory $productCollectionFactory
     * @param Filesystem $filesystem
     * @param StoreManagerInterface $storeManager
     * @param ModifierPool $modifierPool
     * @param AdapterPool $adapterPool
     * @param RuleFactory $ruleFactory
     */
    /**
     * @var FeedJobFactory
     */
    protected $jobFactory;

    /**
     * @var JobResource
     */
    protected $jobResource;

    /**
     * @param ProductCollectionFactory $productCollectionFactory
     * @param Filesystem $filesystem
     * @param StoreManagerInterface $storeManager
     * @param ModifierPool $modifierPool
     * @param AdapterPool $adapterPool
     * @param RuleFactory $ruleFactory
     * @param FeedJobFactory $jobFactory
     * @param JobResource $jobResource
     */
    /**
     * @var \Haerriz\GoogleShoppingFeed\Model\Url\UtmBuilder
     */
    protected $utmBuilder;

    /**
     * @param ProductCollectionFactory $productCollectionFactory
     * @param Filesystem $filesystem
     * @param StoreManagerInterface $storeManager
     * @param ModifierPool $modifierPool
     * @param AdapterPool $adapterPool
     * @param RuleFactory $ruleFactory
     * @param FeedJobFactory $jobFactory
     * @param JobResource $jobResource
     * @param \Haerriz\GoogleShoppingFeed\Model\Url\UtmBuilder $utmBuilder
     */
    public function __construct(
        ProductCollectionFactory $productCollectionFactory,
        Filesystem $filesystem,
        StoreManagerInterface $storeManager,
        ModifierPool $modifierPool,
        AdapterPool $adapterPool,
        RuleFactory $ruleFactory,
        FeedJobFactory $jobFactory,
        JobResource $jobResource,
        \Haerriz\GoogleShoppingFeed\Model\Url\UtmBuilder $utmBuilder
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->filesystem = $filesystem;
        $this->storeManager = $storeManager;
        $this->modifierPool = $modifierPool;
        $this->adapterPool = $adapterPool;
        $this->ruleFactory = $ruleFactory;
        $this->jobFactory = $jobFactory;
        $this->jobResource = $jobResource;
        $this->utmBuilder = $utmBuilder;
    }

    public function generate(FeedProfileInterface $profile, $triggerSource = 'cron')
    {
        $startTime = microtime(true);
        $storeId = $profile->getStoreId();
        $this->storeManager->setCurrentStore($storeId);

        $type = $profile->getFeedType();
        $filename = $profile->getFilename();

        // Instantiate job tracing entry
        $job = $this->jobFactory->create();
        $job->setProfileId($profile->getId());
        $job->setStatus('running');
        $job->setTriggerSource($triggerSource);
        $job->setStartedAt(date('Y-m-d H:i:s'));
        $this->jobResource->save($job);

        if ($type === 'csv') {
            $result = $this->generateCsv($profile, $filename, $job);
        } else {
            $result = $this->generateXml($profile, $filename, $job);
        }

        $duration = round(microtime(true) - $startTime, 2);
        $job->setDuration($duration);
        $job->setPeakMemory(memory_get_peak_usage(true));

        if ($result) {
            $localPath = 'google_feed/' . $filename;
            
            // Check file properties
            $directory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
            if ($directory->isFile($localPath)) {
                $job->setFileSize($directory->stat($localPath)['size']);
                $job->setChecksum(md5($directory->readFile($localPath)));
            }

            $deliveryType = $profile->getDeliveryType() ?: 'local';
            try {
                $adapter = $this->adapterPool->get($deliveryType);
                $delivered = $adapter->upload($profile, $localPath);
                
                $job->setStatus($delivered ? 'success' : 'failed');
                $job->setDeliveryResult($delivered ? 'Delivered successfully' : 'Delivery failed');
            } catch (\Exception $e) {
                $job->setStatus('failed');
                $job->setDeliveryResult($e->getMessage());
                $result = false;
            }
        } else {
            $job->setStatus('failed');
        }

        $job->setFinishedAt(date('Y-m-d H:i:s'));
        $this->jobResource->save($job);

        return $result;
    }

    /**
     * Get base product collection with filters
     *
     * @param int $storeId
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    protected function getProductCollection($storeId, $rule = null)
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('*');
        $collection->addStoreFilter($storeId);
        $collection->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
        $collection->addAttributeToFilter('visibility', ['neq' => \Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE]);
        
        if ($rule) {
            $rule->getConditions()->collectValidatedAttributes($collection);
        }
        
        return $collection;
    }

    /**
     * Generate CSV feed with batching and streaming
     *
     * @param FeedProfileInterface $profile
     * @param string $filename
     * @param \Haerriz\GoogleShoppingFeed\Model\FeedJob|null $job
     * @return bool
     */
    protected function generateCsv(FeedProfileInterface $profile, $filename, $job = null)
    {
        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $stream = $directory->openFile('google_feed/' . $filename, 'w+');
        $stream->lock();

        $mapping = json_decode($profile->getAttributesMappingSerialized(), true) ?? [];
        
        // Write CSV Headers
        $headers = [];
        foreach ($mapping as $map) {
            $headers[] = $map['google_attribute'];
        }
        $stream->writeCsv($headers);

        // Load rules if set
        $rule = null;
        $serializedConditions = $profile->getConditionsSerialized();
        if ($serializedConditions) {
            $conditions = json_decode($serializedConditions, true);
            if (!empty($conditions)) {
                $rule = $this->ruleFactory->create();
                $rule->getConditions()->loadArray($conditions);
            }
        }

        // Paginate and process products
        $lastEntityId = 0;
        $storeId = $profile->getStoreId();
        
        $selected = 0;
        $processed = 0;
        $exported = 0;
        $skipped = 0;

        // Get total catalog count for selected
        $totalCollection = $this->getProductCollection($storeId, $rule);
        $selected = $totalCollection->getSize();
        if ($job) {
            $job->setSelectedCount($selected);
            $job->setTotalProducts($selected);
        }

        while (true) {
            $collection = $this->getProductCollection($storeId, $rule);
            $collection->addFieldToFilter('entity_id', ['gt' => $lastEntityId]);
            $collection->setOrder('entity_id', 'ASC');
            $collection->setPageSize(self::BATCH_SIZE);
            
            if ($collection->count() === 0) {
                break;
            }

            foreach ($collection as $product) {
                $lastEntityId = (int)$product->getId();
                $processed++;
                if ($rule && !$rule->getConditions()->validate($product)) {
                    $skipped++;
                    continue;
                }

                $row = [];
                foreach ($mapping as $map) {
                    $value = $this->resolveFeedValue($map, $product, $profile);
                    $value = $this->applyModifier($value, $map['modifier'] ?? '', $product);
                    $row[] = $value;
                }
                $stream->writeCsv($row);
                $exported++;
            }

            if ($job) {
                $job->setProcessedProducts($processed);
                $job->setExportedCount($exported);
                $job->setSkippedCount($skipped);
                $this->jobResource->save($job);
            }

            $collection->clear();
        }

        $stream->unlock();
        $stream->close();
        return true;
    }

    /**
     * Generate XML feed with batching and streaming
     *
     * @param FeedProfileInterface $profile
     * @param string $filename
     * @param \Haerriz\GoogleShoppingFeed\Model\FeedJob|null $job
     * @return bool
     */
    protected function generateXml(FeedProfileInterface $profile, $filename, $job = null)
    {
        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $stream = $directory->openFile('google_feed/' . $filename, 'w+');
        $stream->lock();

        // Write XML Header
        $xmlHeader = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xmlHeader .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
        $xmlHeader .= '<channel>' . "\n";
        $xmlHeader .= '<title><![CDATA[' . $profile->getName() . ']]></title>' . "\n";
        $xmlHeader .= '<link><![CDATA[' . $this->storeManager->getStore()->getBaseUrl() . ']]></link>' . "\n";
        $stream->write($xmlHeader);

        $mapping = json_decode($profile->getAttributesMappingSerialized(), true) ?? [];
        
        // Load rules if set
        $rule = null;
        $serializedConditions = $profile->getConditionsSerialized();
        if ($serializedConditions) {
            $conditions = json_decode($serializedConditions, true);
            if (!empty($conditions)) {
                $rule = $this->ruleFactory->create();
                $rule->getConditions()->loadArray($conditions);
            }
        }

        $lastEntityId = 0;
        $storeId = $profile->getStoreId();

        $selected = 0;
        $processed = 0;
        $exported = 0;
        $skipped = 0;

        // Get total catalog count for selected
        $totalCollection = $this->getProductCollection($storeId, $rule);
        $selected = $totalCollection->getSize();
        if ($job) {
            $job->setSelectedCount($selected);
            $job->setTotalProducts($selected);
        }

        while (true) {
            $collection = $this->getProductCollection($storeId, $rule);
            $collection->addFieldToFilter('entity_id', ['gt' => $lastEntityId]);
            $collection->setOrder('entity_id', 'ASC');
            $collection->setPageSize(self::BATCH_SIZE);

            if ($collection->count() === 0) {
                break;
            }

            foreach ($collection as $product) {
                $lastEntityId = (int)$product->getId();
                $processed++;
                if ($rule && !$rule->getConditions()->validate($product)) {
                    $skipped++;
                    continue;
                }

                $xmlItem = "  <item>\n";
                foreach ($mapping as $map) {
                    $googleTag = $map['google_attribute'];
                    $value = $this->resolveFeedValue($map, $product, $profile);
                    $value = $this->applyModifier($value, $map['modifier'] ?? '', $product);

                    if ($value !== null && $value !== '') {
                        $safeValue = str_replace(']]>', ']]]]><![CDATA[>', (string)$value);
                        $xmlItem .= "    <{$googleTag}><![CDATA[{$safeValue}]]></{$googleTag}>\n";
                    }
                }
                $xmlItem .= "  </item>\n";
                $stream->write($xmlItem);
                $exported++;
            }

            if ($job) {
                $job->setProcessedProducts($processed);
                $job->setExportedCount($exported);
                $job->setSkippedCount($skipped);
                $this->jobResource->save($job);
            }

            $collection->clear();
        }

        // Write XML Footer
        $xmlFooter = '</channel>' . "\n";
        $xmlFooter .= '</rss>';
        $stream->write($xmlFooter);

        $stream->unlock();
        $stream->close();
        return true;
    }

    /**
     * Resolve values that Google expects in a normalized form.
     *
     * @param array $map
     * @param \Magento\Catalog\Model\Product $product
     * @param FeedProfileInterface $profile
     * @return mixed
     */
    protected function resolveFeedValue(array $map, $product, FeedProfileInterface $profile)
    {
        $googleAttribute = $map['google_attribute'] ?? '';
        $magentoAttribute = $map['magento_attribute'] ?? '';

        switch ($googleAttribute) {
            case 'g:link':
            case 'link':
                return $this->utmBuilder->buildUrl($product->getProductUrl(), $profile, $product);

            case 'g:image_link':
            case 'image_link':
                $image = (string)$product->getData($magentoAttribute ?: 'image');
                if ($image === '' || $image === 'no_selection') {
                    return '';
                }
                return $this->storeManager->getStore()->getBaseUrl(
                    \Magento\Framework\UrlInterface::URL_TYPE_MEDIA
                ) . 'catalog/product' . $image;

            case 'g:price':
            case 'price':
                $price = (float)$product->getFinalPrice();
                return number_format($price, 2, '.', '') . ' ' . $profile->getCurrency();

            case 'g:availability':
            case 'availability':
                return $product->isSalable() ? 'in_stock' : 'out_of_stock';

            case 'g:condition':
            case 'condition':
                return 'new';

            case 'g:brand':
            case 'brand':
                $label = $product->getAttributeText($magentoAttribute ?: 'manufacturer');
                return is_array($label) ? implode(', ', $label) : $label;
        }

        if ($magentoAttribute === 'url') {
            return $this->utmBuilder->buildUrl($product->getProductUrl(), $profile, $product);
        }

        return $product->getData($magentoAttribute);
    }

    /**
     * Apply modifiers
     *
     * @param string $value
     * @param string $modifierCode
     * @param \Magento\Catalog\Model\Product $product
     * @return string
     */
    protected function applyModifier($value, $modifierCode, $product)
    {
        return $this->modifierPool->apply($value, $modifierCode, $product);
    }
}
