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

        if (!isset($this->modifiers[$modifierCode])) {
            return $value; // or throw exception depending on strictness
        }

        return $this->modifiers[$modifierCode]->modify($value, $product);
    }
}
