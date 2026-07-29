<?php
namespace Haerriz\SocialLogin\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Google extends Template
{
    protected $scopeConfig;

    public function __construct(Context $context, array $data = [])
    {
        $this->scopeConfig = $context->getScopeConfig();
        parent::__construct($context, $data);
    }

    public function isEnabled()
    {
        return $this->scopeConfig->isSetFlag('haerriz_sociallogin/google/enable', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getLoginUrl()
    {
        return $this->getUrl('sociallogin/google/redirect');
    }
}
