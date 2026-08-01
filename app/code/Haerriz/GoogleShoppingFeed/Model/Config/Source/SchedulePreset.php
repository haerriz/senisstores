<?php
namespace Haerriz\GoogleShoppingFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Channel-oriented schedule presets for feed profiles.
 *
 * Preset codes (applied by Schedule\PresetApplier):
 * - google_daily  => 0 2 * * *   (daily 02:00)
 * - meta_hourly   => 15 * * * *  (hourly at :15)
 * - bing_daily    => 30 3 * * *  (daily 03:30)
 * - weekly        => 0 4 * * 0   (Sundays 04:00)
 */
class SchedulePreset implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'google_daily', 'label' => __('Google Daily (02:00)')],
            ['value' => 'meta_hourly', 'label' => __('Meta Hourly (:15)')],
            ['value' => 'bing_daily', 'label' => __('Bing Daily (03:30)')],
            ['value' => 'weekly', 'label' => __('Weekly (Sunday 04:00)')],
        ];
    }
}
