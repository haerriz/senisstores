<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Search;

/** DI-mergeable catalog-search provider registry. */
class SearchAdapterRegistry
{
    /** @param array<string,SearchAdapterInterface> $adapters @param array<string,string> $labels */
    public function __construct(private array $adapters = [], private array $labels = []) {}

    public function get(string $code): ?SearchAdapterInterface
    {
        $adapter=$this->adapters[$code]??null;
        return $adapter instanceof SearchAdapterInterface ? $adapter : null;
    }
    public function has(string $code): bool { return $this->get($code) instanceof SearchAdapterInterface; }
    public function getOptions(): array
    {
        $out=[];
        foreach($this->adapters as $code=>$adapter){
            if(!$adapter instanceof SearchAdapterInterface) continue;
            $label=trim((string)($this->labels[$code]??''))?:ucwords(str_replace('_',' ',(string)$code));
            $out[]=['value'=>(string)$code,'label'=>$label];
        }
        return $out;
    }
}
