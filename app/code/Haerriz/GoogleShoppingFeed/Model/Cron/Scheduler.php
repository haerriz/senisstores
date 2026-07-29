<?php
namespace Haerriz\GoogleShoppingFeed\Model\Cron;

use DateTime;
use DateTimeZone;

class Scheduler
{
    /**
     * Calculate next run datetime based on frequency, custom cron expression, and timezone
     *
     * @param string $frequency
     * @param string|null $cronExpr
     * @param string|null $timezoneStr
     * @param string|null $fromTimeStr Base time from which to calculate next run (defaults to now)
     * @return string Datetime in UTC format (Y-m-d H:i:s)
     */
    public function calculateNextRun($frequency, $cronExpr = null, $timezoneStr = null, $fromTimeStr = null)
    {
        $timezone = $timezoneStr ? new DateTimeZone($timezoneStr) : new DateTimeZone('UTC');
        $now = new DateTime($fromTimeStr ?: 'now', new DateTimeZone('UTC'));
        $now->setTimezone($timezone);

        switch ($frequency) {
            case 'hourly':
                $now->modify('+1 hour');
                $now->setTime((int)$now->format('H'), 0, 0); // start of next hour
                break;

            case 'daily':
                $now->modify('+1 day');
                $now->setTime(2, 0, 0); // standard daily run at 2 AM local time
                break;

            case 'weekly':
                $now->modify('+1 week');
                $now->setTime(2, 0, 0);
                break;

            case 'monthly':
                $now->modify('+1 month');
                $now->setTime(2, 0, 0);
                break;

            case 'custom':
            default:
                if (!empty($cronExpr)) {
                    $nextTime = $this->calculateCustomCron($cronExpr, $now->getTimestamp());
                    $now->setTimestamp($nextTime);
                } else {
                    $now->modify('+1 day'); // fallback
                }
                break;
        }

        $now->setTimezone(new DateTimeZone('UTC'));
        return $now->format('Y-m-d H:i:s');
    }

    /**
     * Lightweight custom cron parser/validator returning next timestamp matching expression
     *
     * @param string $expr
     * @param int $currentTimestamp
     * @return int
     */
    protected function calculateCustomCron($expr, $currentTimestamp)
    {
        // For basic custom expressions, match standard simple cron syntax, or calculate minimum step
        // To be lightweight and robust, let's step minute-by-minute up to a threshold (e.g. 1 year) to find match
        $parts = explode(' ', preg_replace('/\s+/', ' ', trim($expr)));
        if (count($parts) < 5) {
            return $currentTimestamp + 86400; // fallback daily increment
        }

        list($min, $hour, $day, $month, $wday) = $parts;
        $time = $currentTimestamp;
        $maxIterations = 525600; // Max minutes in a year to prevent infinite loops

        for ($i = 0; $i < $maxIterations; $i++) {
            $time += 60; // increment minute
            $d = getdate($time);

            if ($this->matchCronPart($min, $d['minutes']) &&
                $this->matchCronPart($hour, $d['hours']) &&
                $this->matchCronPart($day, $d['mday']) &&
                $this->matchCronPart($month, $d['mon']) &&
                $this->matchCronPart($wday, $d['wday'])
            ) {
                return $time;
            }
        }

        return $currentTimestamp + 86400;
    }

    /**
     * Check if a cron part matches current value
     */
    protected function matchCronPart($part, $value)
    {
        if ($part === '*') {
            return true;
        }
        
        // Match lists (e.g. 1,2,5)
        if (strpos($part, ',') !== false) {
            $items = explode(',', $part);
            return in_array($value, $items);
        }

        // Match intervals (e.g. */5)
        if (strpos($part, '*/') !== false) {
            $step = (int)str_replace('*/', '', $part);
            return $step > 0 && ($value % $step === 0);
        }

        // Exact match
        return (int)$part === (int)$value;
    }
}
