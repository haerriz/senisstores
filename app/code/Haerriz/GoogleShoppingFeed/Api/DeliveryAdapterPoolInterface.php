<?php
namespace Haerriz\GoogleShoppingFeed\Api;

interface DeliveryAdapterPoolInterface
{
    public function get(string $code): DeliveryAdapterInterface;
}
