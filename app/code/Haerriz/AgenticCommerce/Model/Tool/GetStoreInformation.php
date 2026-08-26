<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Store\StoreInformationService;

class GetStoreInformation implements ToolInterface
{
    public function __construct(private StoreInformationService $service)
    {
    }

    public function getName(): string
    {
        return 'get_store_information';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->getName(),
                'description' => 'Return authoritative public store identity, website and owner details, assistant capabilities, and configured contact information.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'topic' => ['type' => 'string'],
                    ],
                ],
            ],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        return [
            'assistant_message' => $this->service->message((string)($arguments['topic'] ?? 'contact')),
            'store_profile' => $this->service->get(),
        ];
    }
}
