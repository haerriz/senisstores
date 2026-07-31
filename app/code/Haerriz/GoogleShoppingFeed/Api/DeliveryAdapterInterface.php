<?php
namespace Haerriz\GoogleShoppingFeed\Api;

interface DeliveryAdapterInterface
{
    public function deliver(array $files, array $config): bool;
}
