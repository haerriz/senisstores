<?php
namespace Haerriz\GoogleShoppingFeed\Model\Cron;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;

class Scheduler
{
    /**
     * Check if a feed profile is due for generation based on its cron expression.
     *
     * @param FeedProfileInterface $profile
     * @param \DateTime|null $now
     * @return bool
     */
    public function isDue(FeedProfileInterface $profile, \DateTime $now = null): bool
    {
        if (!(bool)$profile->getStatus()) {
            return false;
        }

        $cronExpr = (string)$profile->getCronExpr();
        if (empty($cronExpr)) {
            return false;
        }

        if ($now === null) {
            $now = new \DateTime();
        }

        try {
            return $this->matchesCronExpression($cronExpr, $now);
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Lightweight cron expression parser.
     * Supports: * (wildcard), n (exact), n-m (range), n/s (step), a,b,c (list)
     */
    private function matchesCronExpression(string $expr, \DateTime $now): bool
    {
        $parts = preg_split('/\s+/', trim($expr));
        if (count($parts) !== 5) {
            throw new \InvalidArgumentException("Invalid cron expression: {$expr}");
        }

        [$minute, $hour, $dayOfMonth, $month, $dayOfWeek] = $parts;

        return $this->matchesCronField($minute,      (int)$now->format('i'), 0, 59)
            && $this->matchesCronField($hour,        (int)$now->format('G'), 0, 23)
            && $this->matchesCronField($dayOfMonth,  (int)$now->format('j'), 1, 31)
            && $this->matchesCronField($month,       (int)$now->format('n'), 1, 12)
            && $this->matchesCronField($dayOfWeek,   (int)$now->format('w'), 0, 6);
    }

    private function matchesCronField(string $field, int $value, int $min, int $max): bool
    {
        // Handle comma-separated lists
        if (strpos($field, ',') !== false) {
            foreach (explode(',', $field) as $part) {
                if ($this->matchesCronField(trim($part), $value, $min, $max)) {
                    return true;
                }
            }
            return false;
        }

        // Handle step values: */2 or 1-5/2
        if (strpos($field, '/') !== false) {
            [$rangeStr, $stepStr] = explode('/', $field, 2);
            $step = (int)$stepStr;
            if ($step <= 0) {
                return false;
            }
            if ($rangeStr === '*') {
                return ($value - $min) % $step === 0;
            }
            // Range with step: 1-10/2
            if (strpos($rangeStr, '-') !== false) {
                [$start, $end] = array_map('intval', explode('-', $rangeStr, 2));
                if ($value < $start || $value > $end) {
                    return false;
                }
                return ($value - $start) % $step === 0;
            }
            return false;
        }

        // Handle wildcard
        if ($field === '*') {
            return true;
        }

        // Handle range: 1-5
        if (strpos($field, '-') !== false) {
            [$start, $end] = array_map('intval', explode('-', $field, 2));
            return $value >= $start && $value <= $end;
        }

        // Exact value
        return (int)$field === $value;
    }
}
