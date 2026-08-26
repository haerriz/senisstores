<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Config\Source;

use Haerriz\AgenticCommerce\Model\Search\SearchAdapterRegistry;
use Magento\Framework\Data\OptionSourceInterface;

class SearchProvider implements OptionSourceInterface
{
    public function __construct(private SearchAdapterRegistry $registry){}
    public function toOptionArray(): array { return $this->registry->getOptions(); }
}
