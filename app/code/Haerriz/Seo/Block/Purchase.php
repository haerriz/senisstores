<?php
declare(strict_types=1);

namespace Haerriz\Seo\Block;

use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\Data\OrderInterface;

class Purchase extends Template
{
    public function __construct(Context $context, private readonly Session $checkoutSession, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function getOrder(): ?OrderInterface
    {
        $order = $this->checkoutSession->getLastRealOrder();
        return $order && $order->getEntityId() ? $order : null;
    }
}
