<?php
namespace Haerriz\SocialLogin\Controller\Github;

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

    public function __construct(Context $context, ScopeConfigInterface $scopeConfig, Session $customerSession, Random $mathRandom) {
        $this->scopeConfig = $scopeConfig;
        $this->customerSession = $customerSession;
        $this->mathRandom = $mathRandom;
        parent::__construct($context);
    }

    public function execute() {
        $clientId = $this->scopeConfig->getValue('haerriz_sociallogin/github/client_id', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $redirectUri = $this->_url->getUrl('sociallogin/github/callback');
        
        if (!$clientId) {
            $this->messageManager->addErrorMessage(__('GitHub Login is not configured.'));
            return $this->_redirect('customer/account/login');
        }

        $state = $this->mathRandom->getRandomString(64);
        $this->customerSession->setData('sociallogin_github_state', $state);

        $authUrl = "https://github.com/login/oauth/authorize?" . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => 'user:email',
            'state' => $state
        ]);

        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setUrl($authUrl);
        return $resultRedirect;
    }
}
