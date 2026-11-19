<?php
declare(strict_types=1);
namespace Haerriz\Seo\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Page\Config;

class NoindexEmptyCategory implements ObserverInterface
{
    public function __construct(private readonly Registry $registry, private readonly Config $pageConfig) {}
    public function execute(Observer $observer): void
    {
        $category = $this->registry->registry('current_category');
        if ($category && $category->getProductCollection()->addAttributeToFilter('status', 1)->getSize() === 0) {
            $this->pageConfig->setRobots('NOINDEX,FOLLOW');
        }
    }
}
