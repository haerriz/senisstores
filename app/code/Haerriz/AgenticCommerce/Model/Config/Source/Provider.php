<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Config\Source;

use Haerriz\AgenticCommerce\Model\Ai\ProviderRegistry;
use Magento\Framework\Data\OptionSourceInterface;

class Provider implements OptionSourceInterface
{
    public function __construct(private ProviderRegistry $registry){}
    public function toOptionArray(): array { return $this->registry->getOptions(true); }
}
