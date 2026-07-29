<?php
namespace Haerriz\GoogleShoppingFeed\Model\Modifier;

use Magento\Framework\Exception\LocalizedException;

class Pool
{
    /**
     * @var ModifierInterface[]
     */
    protected $modifiers;

    public function __construct(
        array $modifiers = []
    ) {
        $this->modifiers = $modifiers;
    }

    /**
     * @param string $value
     * @param string $modifierCode
     * @param \Magento\Catalog\Model\Product $product
     * @return string
     * @throws LocalizedException
     */
    public function apply($value, $modifierCode, $product)
    {
        if (empty($modifierCode)) {
            return $value;
        }

        $code = $modifierCode;
        $argument = null;

        // Matches modifier_name(argument_value)
        if (preg_match('/^([a-zA-Z0-9_-]+)(?:\((.*)\))?$/', $modifierCode, $matches)) {
            $code = $matches[1];
            $argument = isset($matches[2]) ? $matches[2] : null;
        }

        if (!isset($this->modifiers[$code])) {
            return $value;
        }

        return $this->modifiers[$code]->modify($value, $product, $argument);
    }
}
