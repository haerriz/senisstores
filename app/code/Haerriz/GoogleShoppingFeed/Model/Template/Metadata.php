<?php
namespace Haerriz\GoogleShoppingFeed\Model\Template;

use Haerriz\GoogleShoppingFeed\Api\Data\TemplateMetadataInterface;

class Metadata implements TemplateMetadataInterface
{
    private $code;
    private $name;
    private $version;
    private $format;
    private $requiredFields;
    private $defaultMappings;
    private $isOfficial;

    public function __construct(
        string $code,
        string $name,
        string $version,
        string $format,
        array $requiredFields = [],
        array $defaultMappings = [],
        bool $isOfficial = true
    ) {
        $this->code = $code;
        $this->name = $name;
        $this->version = $version;
        $this->format = $format;
        $this->requiredFields = $requiredFields;
        $this->defaultMappings = $defaultMappings;
        $this->isOfficial = $isOfficial;
    }

    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function getVersion(): string { return $this->version; }
    public function getFormat(): string { return $this->format; }
    public function getRequiredFields(): array { return $this->requiredFields; }
    public function getDefaultMappings(): array { return $this->defaultMappings; }
    public function isOfficial(): bool { return $this->isOfficial; }
}
