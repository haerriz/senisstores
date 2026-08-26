<?php declare(strict_types=1);
namespace Haerriz\AgenticCommerce\Model\Config\Source;
use Magento\Framework\Data\OptionSourceInterface;
class DefaultReasoningMode implements OptionSourceInterface { public function toOptionArray(): array { return [
 ['value'=>'fast','label'=>__('Fast deterministic routing')],['value'=>'balanced','label'=>__('Balanced connected commerce reasoning')],['value'=>'deep','label'=>__('Deep multi-step commerce reasoning')]
];}}
