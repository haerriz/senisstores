<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Store;

use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Magento\Framework\Exception\LocalizedException;

/** Read-only Magento directory metadata for checkout/address UIs and headless clients. */
class DirectoryService
{
    public function __construct(private CountryInformationAcquirerInterface $countries) {}

    public function countries(int $limit = 300): array
    {
        $items = [];
        foreach ($this->countries->getCountriesInfo() as $country) {
            $items[] = $this->presentCountry($country, false);
            if (count($items) >= max(1, min(300, $limit))) {
                break;
            }
        }
        return $items;
    }

    public function country(string $countryId): array
    {
        $countryId = strtoupper(trim($countryId));
        if (!preg_match('/^[A-Z]{2}$/', $countryId)) {
            throw new LocalizedException(__('Enter a valid two-letter country code.'));
        }
        return $this->presentCountry($this->countries->getCountryInfo($countryId), true);
    }

    private function presentCountry($country, bool $includeRegions): array
    {
        $regions = [];
        if ($includeRegions) {
            foreach ((array)($country->getAvailableRegions() ?? []) as $region) {
                $regions[] = [
                    'id' => (int)$region->getId(),
                    'code' => (string)$region->getCode(),
                    'name' => (string)$region->getName(),
                ];
            }
        }
        return [
            'id' => (string)$country->getId(),
            'two_letter_abbreviation' => (string)$country->getTwoLetterAbbreviation(),
            'three_letter_abbreviation' => (string)$country->getThreeLetterAbbreviation(),
            'full_name_locale' => (string)$country->getFullNameLocale(),
            'full_name_english' => (string)$country->getFullNameEnglish(),
            'regions' => $regions,
        ];
    }
}
