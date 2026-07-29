<?php
namespace Haerriz\SocialLogin\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Github extends Template
{
    protected $scopeConfig;

    public function __construct(Context $context, array $data = [])
    {
        $this->scopeConfig = $context->getScopeConfig();
        parent::__construct($context, $data);
    }

    public function isEnabled()
    {
        return $this->scopeConfig->isSetFlag('haerriz_sociallogin/github/enable', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getLoginUrl()
    {
        return $this->getUrl('sociallogin/github/redirect');
    }
}
