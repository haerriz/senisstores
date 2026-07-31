<?php
namespace Haerriz\GoogleShoppingFeed\Api;

interface RowValidatorInterface
{
    public function validate(array $row): \Haerriz\GoogleShoppingFeed\Api\Data\RowValidationResultInterface;
}
