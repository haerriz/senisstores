<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Resolver;

use Haerriz\AgenticCommerce\Model\Config;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class ConfigResolver implements ResolverInterface
{
    public function __construct(private Config $config)
    {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null): array
    {
        return [
            'enabled' => $this->config->isEnabled(),
            'title' => $this->config->getTitle(),
            'welcome_message' => $this->config->getWelcomeMessage(),
            'page_size' => $this->config->getPageSize(),
            'provider' => $this->config->getAiProvider(),
            'accent_color' => $this->config->getAccentColor(),
            'auto_navigation' => $this->config->isAutoNavigationEnabled(),
        ];
    }
}
