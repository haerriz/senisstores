<?php
namespace Haerriz\GoogleShoppingFeed\Model\Security;

class CredentialFieldRegistry
{
    public function getSensitiveFields(): array
    {
        return [
            'password',
            'ftp_password',
            'sftp_password',
            'delivery_password',
            'delivery_private_key',
            'delivery_key_passphrase',
            'merchant_json_key',
            'service_account_json',
        ];
    }
}
