<?php

declare(strict_types=1);
namespace Haerriz\AgenticCommerce\Model\Resolver;
use Haerriz\AgenticCommerce\Model\Store\DirectoryService;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
class CountryResolver implements ResolverInterface
{
    public function __construct(private DirectoryService $directory) {}
    public function resolve(Field $field,$context,ResolveInfo $info,array $value=null,array $args=null){return $this->directory->country((string)($args['id']??''));}
}
