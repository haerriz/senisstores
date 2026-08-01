<?php
namespace Haerriz\GoogleShoppingFeed\Model\Validation;

use Haerriz\GoogleShoppingFeed\Api\RowValidatorInterface;
use Haerriz\GoogleShoppingFeed\Api\Data\RowValidationResultInterface;

class GoogleShoppingRowValidator implements RowValidatorInterface
{
    public function validate(array $row): RowValidationResultInterface
    {
        $normalized = $this->normalize($row);
        $errors = [];
        if ($this->isBlank($normalized['id'] ?? null)) {
            $errors[] = 'Missing required field: id';
        }
        if ($this->isBlank($normalized['title'] ?? null)) {
            $errors[] = 'Missing required field: title';
        }
        return new RowValidationResult(empty($errors), $errors);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalize(array $row): array
    {
        $normalized = $row;
        foreach ($row as $key => $value) {
            $plain = strtolower(str_replace(['g:', ' '], ['', '_'], (string)$key));
            if (!array_key_exists($plain, $normalized)) {
                $normalized[$plain] = $value;
            }
        }
        return $normalized;
    }

    private function isBlank($value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value)) {
            return trim($value) === '';
        }
        return $value === false;
    }
}
