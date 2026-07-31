<?php
namespace Haerriz\GoogleShoppingFeed\Model\Taxonomy;

use Haerriz\GoogleShoppingFeed\Api\TaxonomyRepositoryInterface;

class TaxonomyRepository implements TaxonomyRepositoryInterface
{
    public function search(string $query): array
    {
        return ['Apparel & Accessories > Clothing', 'Electronics > Communications > Telephony'];
    }
}
