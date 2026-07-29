<?php
namespace Haerriz\SocialLogin\Controller\Github;

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
        $clientId = $this->scopeConfig->getValue('haerriz_sociallogin/github/client_id', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $redirectUri = $this->_url->getUrl('sociallogin/github/callback');
        
        if (!$clientId) {
            $this->messageManager->addErrorMessage(__('GitHub Login is not configured.'));
            return $this->_redirect('customer/account/login');
        }

        $authUrl = "https://github.com/login/oauth/authorize?" . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => 'user:email'
        ]);

        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setUrl($authUrl);
        return $resultRedirect;
    }
}
