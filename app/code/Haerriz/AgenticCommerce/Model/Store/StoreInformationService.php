<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Store;

use Magento\Directory\Model\CountryFactory;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Haerriz\AgenticCommerce\Model\Knowledge\KnowledgeService;

class StoreInformationService
{
    private const CONFIG_PREFIX = 'agentic_commerce/general/';

    public function __construct(
        private ScopeConfigInterface $scopeConfig,
        private StoreManagerInterface $storeManager,
        private CountryFactory $countryFactory,
        private RegionFactory $regionFactory,
        private KnowledgeService $knowledgeService,
        private array $profileProviders = []
    ) {
    }

    public function get(): array
    {
        $store = $this->storeManager->getStore();
        $storeId = (int)$store->getId();
        $name = $this->value('general/store_information/name', $storeId) ?: (string)$store->getName();
        $phone = $this->value('general/store_information/phone', $storeId);
        $hours = $this->value('general/store_information/hours', $storeId);
        $email = $this->value('trans_email/ident_general/email', $storeId);
        $city = $this->value('general/store_information/city', $storeId);
        $postcode = $this->value('general/store_information/postcode', $storeId);
        $countryId = $this->value('general/store_information/country_id', $storeId);
        $regionId = $this->value('general/store_information/region_id', $storeId);
        $street = array_values(array_filter([
            $this->value('general/store_information/street_line1', $storeId),
            $this->value('general/store_information/street_line2', $storeId),
        ]));

        $country = '';
        if ($countryId !== '') {
            try {
                $country = (string)$this->countryFactory->create()->loadByCode($countryId)->getName();
            } catch (\Throwable) {
                $country = $countryId;
            }
        }

        $region = '';
        if ($regionId !== '' && ctype_digit($regionId)) {
            try {
                $region = (string)$this->regionFactory->create()->load((int)$regionId)->getName();
            } catch (\Throwable) {
                $region = '';
            }
        }

        $addressParts = array_merge($street, array_filter([$city, $region, $postcode, $country]));

        return [
            'assistant_name' => $this->value(self::CONFIG_PREFIX . 'assistant_name', $storeId)
                ?: $this->value(self::CONFIG_PREFIX . 'title', $storeId)
                ?: 'AI Shopping Assistant',
            'assistant_description' => $this->value(self::CONFIG_PREFIX . 'assistant_description', $storeId)
                ?: 'I help shoppers discover and compare products, answer store and product questions, and complete supported shopping actions.',
            'site_name' => $name,
            'organization_name' => $this->value(self::CONFIG_PREFIX . 'organization_name', $storeId),
            'website_name' => (string)$store->getWebsite()->getName(),
            'store_view_name' => (string)$store->getName(),
            'phone' => $phone,
            'email' => $email,
            'hours' => $hours,
            'address' => implode(', ', $addressParts),
            'base_url' => (string)$store->getBaseUrl(),
            'platform' => 'Magento / Adobe Commerce',
            'channels' => ['luma', 'hyva', 'rest', 'graphql', 'pwa', 'headless'],
            'extensions' => $this->extensions($storeId),
        ];
    }

    public function message(string $topic = 'contact'): string
    {
        $info = $this->get();
        $topic = mb_strtolower(trim($topic));
        $parts = [];

        if (preg_match('/\b(?:who\s+are\s+you|assistant|what\s+can\s+you\s+do|capabilit(?:y|ies)|how\s+can\s+you\s+help)\b/u', $topic)) {
            $parts[] = (string)__('%1 — %2', $info['assistant_name'], $info['assistant_description']);
        }
        if (preg_match('/\b(?:what\s+(?:website|site|store)|which\s+(?:website|site|store)|website\s+is\s+this|site\s+is\s+this|store\s+is\s+this)\b/u', $topic)) {
            $parts[] = (string)__('This is %1 (%2).', $info['site_name'], $info['base_url']);
        }
        if (preg_match('/\b(?:owner|owned|organization|organisation|who\s+(?:runs|operates))\b/u', $topic)) {
            $parts[] = $info['organization_name'] !== ''
                ? (string)__('%1 is operated by %2.', $info['site_name'], $info['organization_name'])
                : (string)__('The organization operating %1 has not been configured.', $info['site_name']);
        }

        if (preg_match('/\b(?:phone|number|call|contact|customer care|support)\b/u', $topic) && $info['phone'] !== '') {
            $parts[] = (string)__('Phone: %1', $info['phone']);
        }
        if (preg_match('/\b(?:email|mail|contact|support)\b/u', $topic) && $info['email'] !== '') {
            $parts[] = (string)__('Email: %1', $info['email']);
        }
        if (preg_match('/\b(?:address|location|where|contact)\b/u', $topic) && $info['address'] !== '') {
            $parts[] = (string)__('Address: %1', $info['address']);
        }
        if (preg_match('/\b(?:hours|timing|time|open|opening)\b/u', $topic) && $info['hours'] !== '') {
            $parts[] = (string)__('Hours: %1', $info['hours']);
        }

        $requested = [
            'phone' => (bool)preg_match('/\b(?:phone|number|call|customer care)\b/u', $topic),
            'email' => (bool)preg_match('/\b(?:email|mail)\b/u', $topic),
            'address' => (bool)preg_match('/\b(?:address|location|where)\b/u', $topic),
            'hours' => (bool)preg_match('/\b(?:hours|timing|time|open|opening)\b/u', $topic),
        ];
        foreach ($requested as $kind => $isRequested) {
            if (!$isRequested || $info[$kind] !== '') {
                continue;
            }
            try {
                $contentFact = $this->knowledgeService->publicFact($kind);
                if ($contentFact !== '') {
                    $label = ['phone' => 'Phone', 'email' => 'Email', 'address' => 'Address', 'hours' => 'Hours'][$kind];
                    $parts[] = (string)__('%1: %2', $label, $contentFact);
                }
            } catch (\Throwable) {
                // Configured contact fields remain the fallback when CMS content is unavailable.
            }
        }

        if ($parts === [] && !in_array(true, $requested, true)) {
            foreach ([
                'phone' => 'Phone: %1',
                'email' => 'Email: %1',
                'address' => 'Address: %1',
                'hours' => 'Hours: %1',
            ] as $key => $format) {
                if ($info[$key] !== '') {
                    $parts[] = (string)__($format, $info[$key]);
                }
            }
        }

        if ($parts === []) {
            return (string)__('Store contact information has not been configured yet.');
        }

        return implode("\n", $parts);
    }

    private function extensions(int $storeId): array
    {
        $result = [];
        foreach ($this->profileProviders as $provider) {
            if (!$provider instanceof \Haerriz\AgenticCommerce\Api\StoreProfileProviderInterface) {
                continue;
            }
            try {
                $result[] = ['code' => $provider->getCode(), 'data' => $provider->getProfile($storeId)];
            } catch (\Throwable) {
                // An optional extension must never break the core public profile.
            }
        }
        return $result;
    }

    private function value(string $path, int $storeId): string
    {
        return trim((string)$this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId));
    }
}
