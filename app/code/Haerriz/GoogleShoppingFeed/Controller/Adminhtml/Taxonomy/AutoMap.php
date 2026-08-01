<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Taxonomy;

use Haerriz\GoogleShoppingFeed\Model\Taxonomy\AutoMapper;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

class AutoMap extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::feed_management';

    private AutoMapper $autoMapper;
    private JsonFactory $jsonFactory;

    public function __construct(
        Context $context,
        AutoMapper $autoMapper,
        JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
        $this->autoMapper = $autoMapper;
        $this->jsonFactory = $jsonFactory;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            $rootCategoryId = (int)$this->getRequest()->getParam('root_category_id', 0);
            $stats = $this->autoMapper->map($rootCategoryId > 0 ? $rootCategoryId : null);

            return $result->setData([
                'success' => true,
                'mapped' => $stats['mapped'],
                'skipped' => $stats['skipped'],
                'message' => __(
                    'Auto-mapped %1 categories (%2 skipped).',
                    $stats['mapped'],
                    $stats['skipped']
                )->render(),
            ]);
        } catch (\Throwable $e) {
            return $result->setHttpResponseCode(500)->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
