<?php
namespace Haerriz\SocialLogin\Controller\Facebook;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Session;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Math\Random;

class Callback extends Action
{
    protected $scopeConfig;
    protected $customerFactory;
    protected $customerSession;
    protected $storeManager;
    protected $mathRandom;

    protected $encryptor;

    public function __construct(
        Context $context, ScopeConfigInterface $scopeConfig, CustomerFactory $customerFactory,
        Session $customerSession, StoreManagerInterface $storeManager, Random $mathRandom,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig; $this->customerFactory = $customerFactory;
        $this->customerSession = $customerSession; $this->storeManager = $storeManager;
        $this->mathRandom = $mathRandom; $this->encryptor = $encryptor; parent::__construct($context);
    }

    public function execute() {
        $code = $this->getRequest()->getParam('code');
        if (!$code) {
            $this->messageManager->addErrorMessage(__('Facebook Login failed.'));
            return $this->_redirect('customer/account/login');
        }

        $appId = $this->scopeConfig->getValue('haerriz_sociallogin/facebook/app_id', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $appSecretEnc = $this->scopeConfig->getValue('haerriz_sociallogin/facebook/app_secret', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $appSecret = $this->encryptor->decrypt($appSecretEnc);
        $redirectUri = rtrim($this->_url->getUrl('sociallogin/facebook/callback'), '/');

        // Get Access Token
        $tokenUrl = "https://graph.facebook.com/v19.0/oauth/access_token?" . http_build_query([
            'client_id' => $appId, 'redirect_uri' => $redirectUri,
            'client_secret' => $appSecret, 'code' => $code
        ]);
        
        $tokenResponse = file_get_contents($tokenUrl);
        $tokenData = json_decode($tokenResponse, true);
        if (!isset($tokenData['access_token'])) {
            $this->messageManager->addErrorMessage(__('Failed to get access token from Facebook.'));
            return $this->_redirect('customer/account/login');
        }

        // Get User Info
        $userInfoUrl = "https://graph.facebook.com/me?fields=email,first_name,last_name&access_token=" . $tokenData['access_token'];
        $userInfoResponse = file_get_contents($userInfoUrl);
        $userInfo = json_decode($userInfoResponse, true);

        if (!isset($userInfo['email'])) {
            $this->messageManager->addErrorMessage(__('Failed to get email from Facebook. Please ensure your Facebook account has an email address.'));
            return $this->_redirect('customer/account/login');
        }

        try {
            $store = $this->storeManager->getStore();
            $customer = $this->customerFactory->create();
            $customer->setWebsiteId($store->getWebsiteId())->loadByEmail($userInfo['email']);

            if (!$customer->getId()) {
                $customer->setEmail($userInfo['email']);
                $customer->setFirstname($userInfo['first_name'] ?? 'Facebook');
                $customer->setLastname($userInfo['last_name'] ?? 'User');
                $customer->setStoreId($store->getId());
                $customer->setPassword($this->mathRandom->getRandomString(10));
                $customer->save();
            }

            $this->customerSession->setCustomerAsLoggedIn($customer);
            $this->messageManager->addSuccessMessage(__('You are now logged in.'));
            return $this->_redirect('customer/account/');
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error authenticating: %1', $e->getMessage()));
            return $this->_redirect('customer/account/login');
        }
    }
}
