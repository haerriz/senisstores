<?php declare(strict_types=1);
namespace Haerriz\AgenticCommerce\Model\Config\Source;
use Magento\Framework\Data\OptionSourceInterface;
class ExternalDataScope implements OptionSourceInterface { public function toOptionArray(): array { return [
 ['value'=>'catalog_only','label'=>__('Catalog only (recommended)')],['value'=>'commerce_without_pii','label'=>__('Catalog + non-PII cart/store facts')],['value'=>'disabled','label'=>__('Planner only; no response synthesis')]
];}}
