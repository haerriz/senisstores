<?php
namespace Haerriz\GoogleShoppingFeed\Model\Validation;

use Haerriz\GoogleShoppingFeed\Api\RowValidatorInterface;
use Haerriz\GoogleShoppingFeed\Api\Data\RowValidationResultInterface;

class GoogleShoppingRowValidator implements RowValidatorInterface
{
    public function validate(array $row): RowValidationResultInterface
    {
        $errors = [];
        if (empty($row['id'])) {
            $errors[] = 'Missing required field: id';
        }
        if (empty($row['title'])) {
            $errors[] = 'Missing required field: title';
        }
        return new RowValidationResult(empty($errors), $errors);
    }
}
