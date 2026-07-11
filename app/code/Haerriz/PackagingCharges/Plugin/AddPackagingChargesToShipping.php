<?php
namespace Haerriz\PackagingCharges\Plugin;

class AddPackagingChargesToShipping
{
    private $scopeConfig;
    private $logger;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    public function afterCollectRates(
        \Magento\Shipping\Model\Shipping $subject,
        $result,
        \Magento\Quote\Model\Quote\Address\RateRequest $request
    ) {
        // Check if packaging charges module is active
        $active = $this->scopeConfig->getValue(
            'packaging_charges/general/active',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if (!$active) {
            return $subject;
        }

        $rateResult = $subject->getResult();
        if (!$rateResult) {
            return $subject;
        }

        // Calculate total billable weight (actual vs volumetric)
        $totalBillableWeight = 0;

        // Fallback default dimensions if not set on product
        $defaultLength = 10.0;
        $defaultWidth = 10.0;
        $defaultHeight = 10.0;

        if ($request->getAllItems()) {
            foreach ($request->getAllItems() as $item) {
                if ($item->getProductType() == 'virtual' || $item->getParentItem()) {
                    continue;
                }

                $product = $item->getProduct();
                if (!$product) {
                    continue;
                }

                $length = (float)$product->getData('shipping_length');
                $width = (float)$product->getData('shipping_width');
                $height = (float)$product->getData('shipping_height');

                if ($length <= 0) $length = $defaultLength;
                if ($width <= 0) $width = $defaultWidth;
                if ($height <= 0) $height = $defaultHeight;

                $volumetricWeight = ($length * $width * $height) / 5000.0;
                $actualWeight = (float)$item->getWeight();

                $billableWeight = max($actualWeight, $volumetricWeight);
                $qty = $item->getQty();
                $totalBillableWeight += ($billableWeight * $qty);
            }
        }

        // Determine fee based on total billable weight
        $smallLimit = (float)$this->scopeConfig->getValue('packaging_charges/general/small_limit', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) ?: 2.0;
        $mediumLimit = (float)$this->scopeConfig->getValue('packaging_charges/general/medium_limit', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) ?: 10.0;

        $smallFee = (float)$this->scopeConfig->getValue('packaging_charges/general/small_fee', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) ?: 20.0;
        $mediumFee = (float)$this->scopeConfig->getValue('packaging_charges/general/medium_fee', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) ?: 50.0;
        $largeFee = (float)$this->scopeConfig->getValue('packaging_charges/general/large_fee', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) ?: 100.0;

        if ($totalBillableWeight <= $smallLimit) {
            $fee = $smallFee;
        } elseif ($totalBillableWeight <= $mediumLimit) {
            $fee = $mediumFee;
        } else {
            $fee = $largeFee;
        }

        // Apply packaging charge to all returned shipping rates
        $rates = $rateResult->getAllRates();
        if ($rates && $fee > 0) {
            foreach ($rates as $rate) {
                if (!$rate->getData('packaging_fee_applied')) {
                    $originalPrice = $rate->getPrice();
                    $rate->setPrice($originalPrice + $fee);
                    if (method_exists($rate, 'setCost')) {
                        $rate->setCost($rate->getCost() + $fee);
                    }
                    $rate->setData('packaging_fee_applied', true);
                }
            }
        }

        return $subject;
    }
}
