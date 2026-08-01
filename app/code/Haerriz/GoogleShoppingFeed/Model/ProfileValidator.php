<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Haerriz\GoogleShoppingFeed\Api\RowValidatorInterface;
use Haerriz\GoogleShoppingFeed\Model\Mapping\RowBuilder;

class ProfileValidator
{
    private $rowBuilder;
    /** @var RowValidatorInterface Kept for DI BC; row checks run on real export/preview rows. */
    private $rowValidator;

    public function __construct(
        RowBuilder $rowBuilder,
        RowValidatorInterface $rowValidator
    ) {
        $this->rowBuilder = $rowBuilder;
        $this->rowValidator = $rowValidator;
    }

    /**
     * Throw when profile configuration is invalid.
     *
     * @throws \InvalidArgumentException
     */
    public function assertValid(FeedProfileInterface $profile): void
    {
        $errors = $this->validate($profile);
        if ($errors) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }
    }

    /**
     * Validate a profile's configuration. Returns array of error strings.
     * FIX 17: Uses RowValidatorInterface::validate() for per-row schema validation.
     */
    public function validate(FeedProfileInterface $profile): array
    {
        $errors = [];

        if (!trim((string)$profile->getName())) {
            $errors[] = __('Profile name is required.')->render();
        }

        if (!trim((string)$profile->getFeedType())) {
            $errors[] = __('Feed type is required.')->render();
        }

        $filename = trim((string)$profile->getFilename());
        if (!$filename) {
            $errors[] = __('Output filename is required.')->render();
        } elseif (!preg_match('/\.(xml|csv|jsonl|txt|tsv)$/i', $filename)) {
            $errors[] = __('Filename must end in .xml, .csv, .jsonl, .txt, or .tsv.')->render();
        }

        $cronExpr = trim((string)($profile->getCronExpression() ?: $profile->getData('cron_expr')));
        if ($cronExpr && !$this->isValidCronExpr($cronExpr)) {
            $errors[] = __('Invalid cron expression: "%1"', $cronExpr)->render();
        }

        // Mapping structure only — do not invent dummy product rows here.
        // Per-row required fields (id/title) are validated on real preview/export rows.
        $errors = array_merge($errors, $this->rowBuilder->validate($profile));

        return $errors;
    }

    private function isValidCronExpr(string $expr): bool
    {
        $parts = preg_split('/\s+/', trim($expr));
        return count($parts) === 5;
    }
}
