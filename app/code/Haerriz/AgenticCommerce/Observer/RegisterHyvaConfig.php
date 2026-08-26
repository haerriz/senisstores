<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Observer;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class RegisterHyvaConfig implements ObserverInterface
{
    public function __construct(private ComponentRegistrar $componentRegistrar)
    {
    }

    public function execute(Observer $observer): void
    {
        $config = $observer->getData('config');
        if (!is_object($config) || !method_exists($config, 'getData') || !method_exists($config, 'setData')) {
            return;
        }
        $path = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, 'Haerriz_AgenticCommerce');
        if (!$path || !defined('BP')) {
            return;
        }
        $relative = substr($path, strlen(BP) + 1);
        $extensions = $config->hasData('extensions') ? (array)$config->getData('extensions') : [];
        foreach ($extensions as $extension) {
            if (($extension['src'] ?? '') === $relative) {
                return;
            }
        }
        $extensions[] = ['src' => $relative];
        $config->setData('extensions', $extensions);
    }
}
