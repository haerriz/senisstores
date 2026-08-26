<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Agent;

use Haerriz\AgenticCommerce\Api\ToolAuthorizationProviderInterface;
use Haerriz\AgenticCommerce\Model\Config;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\LocalizedException;

/**
 * Metadata-driven, default-deny policy for every agentic storefront tool.
 *
 * Core metadata is declared in DI so Adobe Commerce/B2B/third-party modules may merge or override
 * classifications without forking PHP. Unknown tools remain denied. Additional enterprise policy
 * providers can enforce catalog permissions, company roles, geo/fraud rules and merchant policy.
 */
class ToolPolicy
{
    /** @param array<string,array<string,mixed>> $toolMetadata @param ToolAuthorizationProviderInterface[] $authorizationProviders */
    public function __construct(
        private Config $config,
        private array $toolMetadata = [],
        private array $authorizationProviders = []
    ) {}

    public function filterDefinitions(array $definitions,array $context=[]):array
    {
        $identity=is_array($context['identity']??null)?$context['identity']:[];
        return array_values(array_filter($definitions,function(array $definition)use($identity):bool{
            $name=(string)($definition['function']['name']??'');if($name===''||!$this->isKnown($name))return false;
            $meta=$this->metadata($name,(int)($identity['store_id']??0));
            if(!$meta['enabled']||!$meta['planner_visible']||!$this->isIdentityAllowed($meta,$identity))return false;
            try{$this->assertExtensionPolicies($name,$identity,$meta);}catch(\Throwable){return false;}
            return true;
        }));
    }

    public function assertAllowed(string $toolName,array $identity,bool $confirmationSatisfied=false):void
    {
        if(!$this->isKnown($toolName))throw new LocalizedException(__('Unknown shopping capability.'));
        $meta=$this->metadata($toolName,(int)($identity['store_id']??0));
        if(!$meta['enabled'])throw new LocalizedException(__('This shopping capability is disabled by the store administrator.'));
        if(!$this->isIdentityAllowed($meta,$identity))throw new AuthorizationException(__('Please sign in to use this shopping capability.'));
        if(!empty($meta['requires_confirmation'])&&!$confirmationSatisfied&&empty($meta['confirmation_executor']))throw new AuthorizationException(__('This shopping capability requires a server-side confirmation workflow.'));
        $this->assertExtensionPolicies($toolName,$identity,$meta);
    }

    public function mutatesState(string $toolName):bool{return (bool)($this->metadata($toolName)['mutates_state']??true);}
    public function isDeterministicLocked(string $toolName):bool{return (bool)($this->metadata($toolName)['deterministic_locked']??false);}
    public function isIdempotent(string $toolName):bool{return (bool)($this->metadata($toolName)['idempotent']??false);}
    public function isKnown(string $toolName):bool{return isset($this->toolMetadata[$toolName])&&is_array($this->toolMetadata[$toolName]);}

    public function metadata(string $toolName,?int $storeId=null):array
    {
        $raw=$this->isKnown($toolName)?(array)$this->toolMetadata[$toolName]:[];
        $mutation=(bool)($raw['mutates_state']??true);
        $feature=trim((string)($raw['feature']??''));
        $enabled=(bool)($raw['enabled']??false);
        if($enabled&&$feature!=='')$enabled=$this->config->isFeatureEnabled($feature,$storeId);
        return [
            'enabled'=>$enabled,
            'category'=>(string)($raw['category']??'unclassified'),
            'risk_level'=>(string)($raw['risk_level']??($mutation?'write':'read')),
            'mutates_state'=>$mutation,
            'requires_customer'=>(bool)($raw['requires_customer']??false),
            'requires_confirmation'=>(bool)($raw['requires_confirmation']??false),
            'confirmation_executor'=>(bool)($raw['confirmation_executor']??false),
            'planner_visible'=>(bool)($raw['planner_visible']??false),
            'deterministic_locked'=>(bool)($raw['deterministic_locked']??false),
            'idempotent'=>(bool)($raw['idempotent']??false),
            'feature'=>$feature,
        ];
    }

    private function isIdentityAllowed(array $meta,array $identity):bool
    {
        return !(bool)$meta['requires_customer']||(int)($identity['customer_id']??0)>0;
    }

    private function assertExtensionPolicies(string $toolName,array $identity,array $meta):void
    {
        foreach($this->authorizationProviders as $provider){
            if($provider instanceof ToolAuthorizationProviderInterface)$provider->assertAllowed($toolName,$identity,$meta);
        }
    }
}
