<?php

namespace Battis\OpenAPI\Generator\CodeComponent;

use Battis\OpenAPI\Generator\TypeMap;

class Property extends BaseComponent
{
    /**
     * @var 'public'|'protected'|'private' $access
     */
    private string $access = 'public';

    private bool $static = false;

    private string $type;

    private ?string $docType = null;

    private string $name;

    private ?string $description = null;

    private ?string $defaultValue = null;

    private bool $documentationOnly = false;

    public function equals(Property $other): bool
    {
        return $this->name === $other->name && $this->type === $other->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDefaultValue(): ?string
    {
        return $this->defaultValue;
    }

    public function setDocType(string $docType)
    {
        $this->docType = $docType;
    }

    public function isDocumentationOnly(): bool
    {
        return $this->documentationOnly;
    }

    public static function documentationOnly(string $name, string $docType, ?string $description = null): Property
    {
        $property = new Property();
        $property->name = $name;
        $property->docType = $docType;
        $property->description = $description;
        $property->documentationOnly = true;
        return $property;
    }
    public static function private(string $name, string $type, ?string $description = null, ?string $defaultValue = null): Property
    {
        $property = new Property();
        $property->name = $name;
        $property->type = $type;
        $property->description = $description;
        $property->defaultValue = $defaultValue;
        $property->access = 'private';
        $property->static = true;
        return $property;
    }

    public static function protectedStatic(string $name, string $type, ?string $description = null, ?string $defaultValue = null): Property
    {
        $property = self::private($name, $type, $description, $defaultValue);
        $property->access = 'protected';
        $property->static = true;
        return $property;
    }

    public function asPHPDocProperty(): string
    {
        return trim("@property " . ($this->docType ?? $this->type) . " \$$this->name $this->description");
    }

    /**
     * @param array<string, string> $remap
     *
     * @return string
     */
    public function asDeclaration(array $remap = []): string
    {
        $doc = new PHPDoc();
        $doc->addItem(trim("@var " . TypeMap::parseType($this->docType ?? $this->type, true, true) . " \$$this->name $this->description"));
        return $doc->asString() . "$this->access " . ($this->static ? "static " : "") . ($remap[$this->type] ?? TypeMap::parseType($this->type, false)) . " \$$this->name" . (empty($this->defaultValue) ? "" : " = $this->defaultValue") . ";" . PHP_EOL;
    }
}
