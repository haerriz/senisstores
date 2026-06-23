<?php
/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 */

namespace Haerriz\GoogleFeed\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class ShippingWeightFormatter
{
    private const XML_PATH_WEIGHT_UNIT = 'general/locale/weight_unit';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param float|null $weight
     * @param int|null $storeId
     * @return string|null
     */
    public function format($weight, $storeId = null)
    {
        if ($weight === null || $weight <= 0) {
            return null;
        }

        $unit = strtolower((string) $this->scopeConfig->getValue(
            self::XML_PATH_WEIGHT_UNIT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));

        if (in_array($unit, ['kgs', 'kg'], true)) {
            $googleUnit = 'kg';
        } elseif (in_array($unit, ['lbs', 'lb'], true)) {
            $googleUnit = 'lb';
        } elseif ($unit === 'oz') {
            $googleUnit = 'oz';
        } elseif ($unit === 'g') {
            $googleUnit = 'g';
        } else {
            $googleUnit = 'kg';
        }

        return rtrim(rtrim(number_format((float) $weight, 3, '.', ''), '0'), '.') . ' ' . $googleUnit;
    }
}
