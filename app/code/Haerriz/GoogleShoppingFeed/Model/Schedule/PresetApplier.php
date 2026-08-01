<?php
namespace Haerriz\GoogleShoppingFeed\Model\Schedule;

class PresetApplier
{
    /**
     * @var array<string, string>
     */
    private const PRESETS = [
        'google_daily' => '0 2 * * *',
        'meta_hourly' => '15 * * * *',
        'bing_daily' => '30 3 * * *',
        'weekly' => '0 4 * * 0',
    ];

    /**
     * Return cron expression for a schedule preset code, or null if unknown.
     */
    public function getCronExpression(string $presetCode): ?string
    {
        $code = strtolower(trim($presetCode));
        return self::PRESETS[$code] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function getAll(): array
    {
        return self::PRESETS;
    }
}
