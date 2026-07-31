<?php
namespace Haerriz\GoogleShoppingFeed\Model\Security;

class CredentialFieldRegistry
{
    public function getSensitiveFields(): array
    {
        return ['password', 'ftp_password', 'sftp_password', 'merchant_json_key'];
    }
}
