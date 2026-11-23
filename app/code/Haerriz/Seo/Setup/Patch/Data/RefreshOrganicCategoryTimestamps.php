<?php

declare(strict_types=1);

namespace Haerriz\Seo\Setup\Patch\Data;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/** Keeps sitemap last-modified values aligned with this patch's real content edits. */
class RefreshOrganicCategoryTimestamps implements DataPatchInterface
{
    private const CHANGED_CATEGORY_IDS = [9, 24, 26, 32, 33, 34, 35];

    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    public function apply(): self
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->update(
            $this->resourceConnection->getTableName('catalog_category_entity'),
            ['updated_at' => gmdate('Y-m-d H:i:s')],
            ['entity_id IN (?)' => self::CHANGED_CATEGORY_IDS]
        );

        return $this;
    }

    public static function getDependencies(): array
    {
        return [StrengthenOrganicMerchandising::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
