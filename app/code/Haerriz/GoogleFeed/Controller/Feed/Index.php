<?php
/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 */

namespace Haerriz\GoogleFeed\Controller\Feed;

use Haerriz\GoogleFeed\Model\Feed\Generator;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Store\Model\StoreManagerInterface;

class Index extends Action
{
    /**
     * @var Generator
     */
    private $feedGenerator;

    /**
     * @var RawFactory
     */
    private $resultRawFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param Context $context
     * @param Generator $feedGenerator
     * @param RawFactory $resultRawFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        Generator $feedGenerator,
        RawFactory $resultRawFactory,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->feedGenerator = $feedGenerator;
        $this->resultRawFactory = $resultRawFactory;
        $this->storeManager = $storeManager;
    }

    /**
     * @return Raw
     */
    public function execute()
    {
        $result = $this->resultRawFactory->create();

        if (!$this->feedGenerator->isEnabled((int) $this->storeManager->getStore()->getId())) {
            $result->setHttpResponseCode(404);
            $result->setContents('Feed disabled');

            return $result;
        }

        try {
            $xml = $this->feedGenerator->generate((int) $this->storeManager->getStore()->getId());
        } catch (\Exception $exception) {
            $result->setHttpResponseCode(500);
            $result->setContents('Unable to generate feed');

            return $result;
        }

        $result->setHeader('Content-Type', 'application/xml; charset=UTF-8', true);
        $result->setContents($xml);

        return $result;
    }
}
