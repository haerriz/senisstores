<?php
namespace Haerriz\SocialLogin\Controller\Github;

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

    public function execute() {
        $code = $this->getRequest()->getParam('code');
        if (!$code) {
            $this->messageManager->addErrorMessage(__('GitHub Login failed.'));
            return $this->_redirect('customer/account/login');
        }

        $clientId = $this->scopeConfig->getValue('haerriz_sociallogin/github/client_id', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $clientSecretEnc = $this->scopeConfig->getValue('haerriz_sociallogin/github/client_secret', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $clientSecret = (strpos($clientSecretEnc, '0:2:') === 0 || strpos($clientSecretEnc, '0:3:') === 0) ? $this->encryptor->decrypt($clientSecretEnc) : $clientSecretEnc;
        $redirectUri = rtrim($this->_url->getUrl('sociallogin/github/callback'), '/');

        // Get Access Token
        $tokenUrl = 'https://github.com/login/oauth/access_token';
        $postData = ['client_id' => $clientId, 'client_secret' => $clientSecret, 'code' => $code, 'redirect_uri' => $redirectUri];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        $tokenResponse = curl_exec($ch);
        curl_close($ch);

        $tokenData = json_decode($tokenResponse, true);
        if (!isset($tokenData['access_token'])) {
            $err_msg = 'Failed to get access token from GitHub.';
            if (isset($tokenData['error_description'])) {
                $err_msg .= ' Error: ' . $tokenData['error_description'];
            } else {
                $err_msg .= ' Response: ' . $tokenResponse;
            }
            $this->messageManager->addErrorMessage(__($err_msg));
            return $this->_redirect('customer/account/login');
        }

        // Get User Profile
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.github.com/user');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: token ' . $tokenData['access_token'], 'User-Agent: Magento2-Haerriz-SocialLogin']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $userInfoResponse = curl_exec($ch);
        curl_close($ch);
        $userInfo = json_decode($userInfoResponse, true);

        // Get Emails (GitHub sometimes hides email in the main profile)
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.github.com/user/emails');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: token ' . $tokenData['access_token'], 'User-Agent: Magento2-Haerriz-SocialLogin', 'Accept: application/vnd.github.v3+json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $emailsResponse = curl_exec($ch);
        curl_close($ch);
        $emailsData = json_decode($emailsResponse, true);

        $primaryEmail = null;
        if (is_array($emailsData)) {
            foreach ($emailsData as $emailObj) {
                if ($emailObj['primary'] && $emailObj['verified']) {
                    $primaryEmail = $emailObj['email'];
                    break;
                }
            }
        }

        if (!$primaryEmail) {
            $this->messageManager->addErrorMessage(__('Failed to get a verified primary email from GitHub.'));
            return $this->_redirect('customer/account/login');
        }

        try {
            $store = $this->storeManager->getStore();
            $customer = $this->customerFactory->create();
            $customer->setWebsiteId($store->getWebsiteId())->loadByEmail($primaryEmail);

            if (!$customer->getId()) {
                $customer->setEmail($primaryEmail);
                $nameParts = explode(' ', $userInfo['name'] ?? 'GitHub User', 2);
                $customer->setFirstname($nameParts[0]);
                $customer->setLastname($nameParts[1] ?? 'User');
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
