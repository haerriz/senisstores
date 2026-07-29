<?php
namespace Haerriz\GoogleShoppingFeed\Model\Rule;

use Magento\Catalog\Model\Product;

class Condition
{
    /**
     * Apply condition-based attribute overwrites
     *
     * @param Product $product
     * @param array $conditions
     * @return array Modified product data
     */
    public function applyConditions(Product $product, array $conditions = [])
    {
        $modifiedData = $product->getData();

        if (empty($conditions)) {
            return $modifiedData;
        }

        // Structure of $conditions expected:
        // [
        //     ['attribute' => 'price', 'operator' => '>', 'value' => 50, 'action_attribute' => 'custom_label_0', 'action_value' => 'Free Shipping']
        // ]

        foreach ($conditions as $rule) {
            $currentValue = $product->getData($rule['attribute'] ?? '');
            $operator = $rule['operator'] ?? '==';
            $targetValue = $rule['value'] ?? '';

            if ($this->validate($currentValue, $operator, $targetValue)) {
                $actionAttr = $rule['action_attribute'] ?? '';
                $actionVal = $rule['action_value'] ?? '';
                if ($actionAttr) {
                    $modifiedData[$actionAttr] = $actionVal;
                }
            }
        }

        return $modifiedData;
    }

    protected function validate($currentValue, $operator, $targetValue)
    {
        switch ($operator) {
            case '==': return $currentValue == $targetValue;
            case '!=': return $currentValue != $targetValue;
            case '>':  return (float)$currentValue > (float)$targetValue;
            case '<':  return (float)$currentValue < (float)$targetValue;
            case '>=': return (float)$currentValue >= (float)$targetValue;
            case '<=': return (float)$currentValue <= (float)$targetValue;
            case '{}': return stripos($currentValue, $targetValue) !== false; // contains
            case '!{}': return stripos($currentValue, $targetValue) === false; // does not contain
        }
        return false;
    }
}
