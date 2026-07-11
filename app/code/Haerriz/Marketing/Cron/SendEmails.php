<?php
namespace Haerriz\Marketing\Cron;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Newsletter\Model\ResourceModel\Subscriber\CollectionFactory as SubscriberCollectionFactory;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Framework\App\State;

class SendEmails
{
    protected $scopeConfig;
    protected $subscriberCollectionFactory;
    protected $transportBuilder;
    protected $storeManager;
    protected $resourceConnection;
    protected $inlineTranslation;
    protected $appState;
    protected $logger;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        SubscriberCollectionFactory $subscriberCollectionFactory,
        TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManager,
        ResourceConnection $resourceConnection,
        StateInterface $inlineTranslation,
        State $appState,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->subscriberCollectionFactory = $subscriberCollectionFactory;
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;
        $this->resourceConnection = $resourceConnection;
        $this->inlineTranslation = $inlineTranslation;
        $this->appState = $appState;
        $this->logger = $logger;
    }

    public function execute()
    {
        if (!$this->scopeConfig->getValue('haerriz_marketing/general/enable', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)) {
            return;
        }
        
        try {
            $this->appState->setAreaCode(\Magento\Framework\App\Area::AREA_FRONTEND);
        } catch (\Exception $e) {
            // Area code is already set
        }

        $batchSize = (int)$this->scopeConfig->getValue('haerriz_marketing/general/batch_size', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if ($batchSize <= 0) $batchSize = 20;
        
        $subject = $this->scopeConfig->getValue('haerriz_marketing/general/email_subject', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if (!$subject) $subject = 'Check out our latest arrivals!';

        $connection = $this->resourceConnection->getConnection();
        $logTable = $this->resourceConnection->getTableName('haerriz_marketing_log');

        $subscribers = $this->subscriberCollectionFactory->create()
            ->addFieldToFilter('subscriber_status', \Magento\Newsletter\Model\Subscriber::STATUS_SUBSCRIBED);
        
        $subscribers->getSelect()->joinLeft(
            ['log' => $logTable],
            'main_table.subscriber_id = log.subscriber_id',
            []
        )->where('log.entity_id IS NULL')
         ->limit($batchSize);

        $sentCount = 0;
        $storeId = $this->storeManager->getStore()->getId();
        if (!$storeId) {
            $storeId = 1; 
        }

        foreach ($subscribers as $subscriber) {
            try {
                $this->inlineTranslation->suspend();
                $transport = $this->transportBuilder
                    ->setTemplateIdentifier('haerriz_marketing_template')
                    ->setTemplateOptions([
                        'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                        'store' => $storeId
                    ])
                    ->setTemplateVars(['subject' => $subject])
                    ->setFromByScope('general', $storeId)
                    ->addTo($subscriber->getSubscriberEmail())
                    ->getTransport();

                $transport->sendMessage();
                $this->inlineTranslation->resume();

                $connection->insert($logTable, [
                    'subscriber_id' => $subscriber->getSubscriberId(),
                    'sent_at' => date('Y-m-d H:i:s')
                ]);
                $sentCount++;
            } catch (\Exception $e) {
                $this->logger->error('Haerriz Marketing Email Error: ' . $e->getMessage());
                $this->inlineTranslation->resume();
            }
        }
        
        if ($sentCount > 0) {
            $this->logger->info("Haerriz Marketing Email: Sent $sentCount emails.");
        }
    }
}
