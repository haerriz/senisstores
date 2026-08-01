<?php
namespace Haerriz\GoogleShoppingFeed\Model\Conflict;

use Magento\Framework\Module\ModuleListInterface;

class LegacyGoogleFeedDetector
{
    public const CONFLICT_MODULE = 'Haerriz_GoogleFeed';

    private ModuleListInterface $moduleList;

    public function __construct(ModuleListInterface $moduleList)
    {
        $this->moduleList = $moduleList;
    }

    public function isConflictDetected(): bool
    {
        return $this->moduleList->has(self::CONFLICT_MODULE);
    }

    public function getConflictModuleName(): string
    {
        return self::CONFLICT_MODULE;
    }

    public function getWarningMessage(): string
    {
        return sprintf(
            'Legacy module %s is enabled and may conflict with Haerriz_GoogleShoppingFeed (duplicate feeds, cron, or admin routes). Disable one of the modules.',
            self::CONFLICT_MODULE
        );
    }
}
