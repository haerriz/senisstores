<?php
namespace Haerriz\SocialLogin\Controller\Google;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Math\Random;

class Redirect extends Action
{
    protected $scopeConfig;
    protected $customerSession;
    protected $mathRandom;

    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        Session $customerSession,
        Random $mathRandom
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->customerSession = $customerSession;
        $this->mathRandom = $mathRandom;
        parent::__construct($context);
    }

    public function execute()
    {
        $clientId = $this->scopeConfig->getValue('haerriz_sociallogin/google/client_id', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $redirectUri = $this->_url->getUrl('sociallogin/google/callback');
        
        if (!$clientId) {
            $this->messageManager->addErrorMessage(__('Google Social Login is not configured.'));
            return $this->_redirect('customer/account/login');
        }

        $state = $this->mathRandom->getRandomString(64);
        $this->customerSession->setData('sociallogin_google_state', $state);

        $authUrl = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'email profile',
            'access_type' => 'online',
            'prompt' => 'select_account',
            'state' => $state
        ]);

        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setUrl($authUrl);
        return $resultRedirect;
    }
}
