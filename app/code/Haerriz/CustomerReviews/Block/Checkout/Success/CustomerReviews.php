<?php
namespace Haerriz\CustomerReviews\Block\Checkout\Success;

class CustomerReviews extends \Magento\Framework\View\Element\Template
{
    private $checkoutSession;
    private $scopeConfig;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        array $data = []
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->scopeConfig = $context->getScopeConfig();
        parent::__construct($context, $data);
    }

    public function getOrder()
    {
        return $this->checkoutSession->getLastRealOrder();
    }

    public function getMerchantId()
    {
        return '5327375994'; // Seni's Stores Merchant ID
    }

    public function getEstimatedDeliveryDate()
    {
        // Estimated delivery: e.g. 5 days from today in YYYY-MM-DD format
        return date('Y-m-d', strtotime('+5 days'));
    }
}
