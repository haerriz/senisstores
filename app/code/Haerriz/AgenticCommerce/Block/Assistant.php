<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Block;

use Haerriz\AgenticCommerce\Model\Config;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\StoreManagerInterface;

class Assistant extends Template
{
    public function __construct(
        Template\Context $context,
        private Config $config,
        private FormKey $formKey,
        private StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        if (!$this->config->isEnabled()) return false;
        return $this->getRequest()->getFullActionName() === 'cms_index_index'
            ? $this->config->isHomepageEnabled()
            : $this->config->isAllPagesEnabled();
    }
    public function getAssistantTitle(): string { return $this->config->getTitle(); }
    public function getWelcomeMessage(): string { return $this->config->getWelcomeMessage(); }
    public function getMaxMessageLength(): int { return $this->config->getMaxMessageLength(); }
    public function getPlacementSelector(): string
    {
        return $this->getRequest()->getFullActionName() === 'cms_index_index'
            ? $this->config->getPlacementSelector()
            : '';
    }
    public function getAccentColor(): string { return $this->config->getAccentColor(); }
    public function isAutoNavigationEnabled(): bool { return $this->config->isAutoNavigationEnabled(); }
    public function getChatUrl(): string { return $this->getUrl('agenticcommerce/chat/message'); }
    public function getActionUrl(): string { return $this->getUrl('agenticcommerce/action/execute'); }
    public function getHistoryUrl(): string { return $this->getUrl('agenticcommerce/conversation/history'); }
    public function getStartUrl(): string { return $this->getUrl('agenticcommerce/conversation/start'); }
    public function getCloseUrl(): string { return $this->getUrl('agenticcommerce/conversation/close'); }
    public function getFormKeyValue(): string { return $this->formKey->getFormKey(); }
    public function getStoreCode(): string { return (string)$this->storeManager->getStore()->getCode(); }
}
