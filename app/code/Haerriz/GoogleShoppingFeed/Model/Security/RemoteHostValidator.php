<?php
namespace Haerriz\GoogleShoppingFeed\Model\Security;

class RemoteHostValidator
{
    public function isValid(string $host): bool
    {
        return !empty($host);
    }
}
