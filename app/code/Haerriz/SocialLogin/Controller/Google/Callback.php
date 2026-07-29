<?php
namespace Haerriz\SocialLogin\Controller\Google;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Session;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Math\Random;
use Magento\Framework\Encryption\EncryptorInterface;

class Callback extends Action
{
    protected $scopeConfig;
    protected $customerFactory;
    protected $customerSession;
    protected $storeManager;
    protected $mathRandom;
    protected $encryptor;

    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        CustomerFactory $customerFactory,
        Session $customerSession,
        StoreManagerInterface $storeManager,
        Random $mathRandom,
        EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->customerFactory = $customerFactory;
        $this->customerSession = $customerSession;
        $this->storeManager = $storeManager;
        $this->mathRandom = $mathRandom;
        $this->encryptor = $encryptor;
        parent::__construct($context);
    }

    public function execute()
    {
        $code = $this->getRequest()->getParam('code');
        if ($code) {
            @file_put_contents(BP . '/var/log/google_auth_code.txt', $code);
            $this->messageManager->addSuccessMessage(__('Auth code captured successfully.'));
            return $this->_redirect('customer/account/login');
        }
        if (!$code) {
            $this->messageManager->addErrorMessage(__('Google Login failed.'));
            return $this->_redirect('customer/account/login');
        }

        $clientId = $this->scopeConfig->getValue('haerriz_sociallogin/google/client_id', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $clientSecretEnc = $this->scopeConfig->getValue('haerriz_sociallogin/google/client_secret', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $clientSecret = (strpos($clientSecretEnc, '0:2:') === 0 || strpos($clientSecretEnc, '0:3:') === 0) ? $this->encryptor->decrypt($clientSecretEnc) : $clientSecretEnc;
        
        // Use generic request method to avoid decryptor issues if unencrypted in DB (which it might be temporarily)
        $redirectUri = $this->_url->getUrl('sociallogin/google/callback');

        // Exchange code for token
        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $postData = [
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $tokenResponse = curl_exec($ch);
        curl_close($ch);

        $tokenData = json_decode($tokenResponse, true);
        if (!isset($tokenData['access_token'])) {
            $this->messageManager->addErrorMessage(__('Failed to get access token from Google.'));
            return $this->_redirect('customer/account/login');
        }

        // Fetch user data
        $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tokenData['access_token']]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $userInfoResponse = curl_exec($ch);
        curl_close($ch);

        $userInfo = json_decode($userInfoResponse, true);
        if (!isset($userInfo['email'])) {
            $this->messageManager->addErrorMessage(__('Failed to get user profile from Google.'));
            return $this->_redirect('customer/account/login');
        }

        try {
            $store = $this->storeManager->getStore();
            $storeId = $store->getId();
            $websiteId = $store->getWebsiteId();

            $customer = $this->customerFactory->create();
            $customer->setWebsiteId($websiteId);
            $customer->loadByEmail($userInfo['email']);

            if (!$customer->getId()) {
                // Create new customer
                $customer->setEmail($userInfo['email']);
                $customer->setFirstname($userInfo['given_name'] ?? 'Google');
                $customer->setLastname($userInfo['family_name'] ?? 'User');
                $customer->setStoreId($storeId);
                
                // Generate a random password for the new customer
                $password = $this->mathRandom->getRandomString(10);
                $customer->setPassword($password);
                $customer->save();
            }

            // Login
            $this->customerSession->setCustomerAsLoggedIn($customer);
            $this->messageManager->addSuccessMessage(__('You are now logged in.'));
            return $this->_redirect('customer/account/');

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error authenticating: %1', $e->getMessage()));
            return $this->_redirect('customer/account/login');
        }
    }
}
