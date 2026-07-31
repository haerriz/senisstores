<?php
namespace Haerriz\GoogleShoppingFeed\Api\Data;

interface RowValidationResultInterface
{
    public function isValid(): bool;
    public function getErrors(): array;
}
