<?php
namespace Haerriz\GoogleShoppingFeed\Api;

interface CredentialProviderInterface
{
    /**
     * Encrypt a secret for persistence.
     *
     * @param string $secret
     * @return string
     */
    public function encrypt($secret);

    /**
     * Decrypt a persisted secret at its point of use.
     *
     * @param string|null $encryptedSecret
     * @return string
     */
    public function decrypt($encryptedSecret);

    /**
     * Read and decrypt an encrypted Magento configuration value.
     *
     * @param string $path
     * @param string $scopeType
     * @param int|string|null $scopeCode
     * @return string
     */
    public function getConfigSecret($path, $scopeType = 'default', $scopeCode = null);
}
