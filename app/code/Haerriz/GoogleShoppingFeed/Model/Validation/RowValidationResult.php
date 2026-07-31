<?php
namespace Haerriz\GoogleShoppingFeed\Model\Validation;

use Haerriz\GoogleShoppingFeed\Api\Data\RowValidationResultInterface;

class RowValidationResult implements RowValidationResultInterface
{
    private bool $valid;
    private array $errors;

    public function __construct(bool $valid = true, array $errors = [])
    {
        $this->valid = $valid;
        $this->errors = $errors;
    }

    public function isValid(): bool { return $this->valid; }
    public function getErrors(): array { return $this->errors; }
}
