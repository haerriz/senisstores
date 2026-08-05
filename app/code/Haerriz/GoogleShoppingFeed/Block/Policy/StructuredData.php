<?php
declare(strict_types=1);

namespace Haerriz\GoogleShoppingFeed\Block\Policy;

use Haerriz\GoogleShoppingFeed\Model\StructuredData\PolicySchemaBuilder;
use Magento\Framework\View\Element\Template;
use Psr\Log\LoggerInterface;

class StructuredData extends Template
{
    private PolicySchemaBuilder $schemaBuilder;

    private LoggerInterface $logger;

    public function __construct(
        Template\Context $context,
        PolicySchemaBuilder $schemaBuilder,
        LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->schemaBuilder = $schemaBuilder;
        $this->logger = $logger;
    }

    public function getJsonLd(): string
    {
        try {
            $schema = $this->schemaBuilder->build((string) $this->getData('policy_type'));
            if ($schema === []) {
                return '';
            }

            $json = json_encode(
                $schema,
                JSON_HEX_TAG
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
                | JSON_HEX_AMP
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            );

            return is_string($json) ? $json : '';
        } catch (\Throwable $exception) {
            $this->logger->warning(
                'Unable to render Google merchant policy structured data.',
                ['policy_type' => $this->getData('policy_type'), 'exception' => $exception]
            );

            return '';
        }
    }
}
