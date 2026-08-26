<?php declare(strict_types=1);
namespace Haerriz\AgenticCommerce\Model\Config\Source;
use Magento\Framework\Data\OptionSourceInterface;
class ReasoningEffort implements OptionSourceInterface { public function toOptionArray(): array { return [
 ['value'=>'auto','label'=>__('Automatic / provider default')],['value'=>'minimal','label'=>__('Minimal')],['value'=>'low','label'=>__('Low')],['value'=>'medium','label'=>__('Medium')],['value'=>'high','label'=>__('High')]
];}}
