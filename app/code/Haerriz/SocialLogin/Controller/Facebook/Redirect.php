<?php
namespace Haerriz\SocialLogin\Controller\Facebook;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Redirect extends Action
{
    protected $scopeConfig;

    public function __construct(Context $context, ScopeConfigInterface $scopeConfig) {
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context);
    }

    public function execute() {
        $appId = $this->scopeConfig->getValue('haerriz_sociallogin/facebook/app_id', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $redirectUri = $this->_url->getUrl('sociallogin/facebook/callback');
        
        if (!$appId) {
            $this->messageManager->addErrorMessage(__('Facebook Login is not configured.'));
            return $this->_redirect('customer/account/login');
        }

        $authUrl = "https://www.facebook.com/v19.0/dialog/oauth?" . http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'email,public_profile'
        ]);

        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setUrl($authUrl);
        return $resultRedirect;
    }
}
