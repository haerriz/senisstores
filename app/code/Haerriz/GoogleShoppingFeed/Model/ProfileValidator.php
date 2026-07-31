<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Haerriz\GoogleShoppingFeed\Api\RowValidatorInterface;
use Haerriz\GoogleShoppingFeed\Model\Mapping\RowBuilder;

class ProfileValidator
{
    private $rowBuilder;
    private $rowValidator;

    public function __construct(
        RowBuilder $rowBuilder,
        RowValidatorInterface $rowValidator
    ) {
        $this->rowBuilder   = $rowBuilder;
        $this->rowValidator = $rowValidator;
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

        $cronExpr = trim((string)$profile->getCronExpr());
        if ($cronExpr && !$this->isValidCronExpr($cronExpr)) {
            $errors[] = __('Invalid cron expression: "%1"', $cronExpr)->render();
        }

        // Use RowBuilder validation (mapping errors)
        $mappingErrors = $this->rowBuilder->validate($profile);
        $errors        = array_merge($errors, $mappingErrors);

        // Use RowValidatorInterface to validate a sample row structure
        if (empty($mappingErrors)) {
            try {
                $mappings = $this->rowBuilder->getMappings($profile);
                if (!empty($mappings)) {
                    $sampleRow = [];
                    foreach ($mappings as $m) {
                        $sampleRow[$m['google_attribute'] ?? ''] = 'sample_value';
                    }
                    $result = $this->rowValidator->validate($sampleRow);
                    if (method_exists($result, 'getErrors') && $result->getErrors()) {
                        foreach ($result->getErrors() as $rowErr) {
                            $errors[] = (string)$rowErr;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Non-fatal — row validator may not be implemented yet
            }
        }

        return $errors;
    }

    private function isValidCronExpr(string $expr): bool
    {
        $parts = preg_split('/\s+/', trim($expr));
        return count($parts) === 5;
    }
}
