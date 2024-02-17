<?php

namespace Battis\OpenAPI\Generator\CodeComponent\Method;

use Battis\OpenAPI\Generator\CodeComponent\BaseComponent;

class Parameter extends BaseComponent
{
    private string $name;

    private string $type;

    private ?string $docType = null;

    private ?string $description;

    private bool $optional = false;

    public function isOptional(): bool
    {
        return $this->optional;
    }

    public function setDocType(string $docType)
    {
        $this->docType = $docType;
    }

    public static function from(string $name, string $type, ?string $description = null, bool $optional = false): Parameter
    {
        $parameter = new Parameter();
        $parameter->name = $name;
        $parameter->type = $type;
        $parameter->description = $description;
        $parameter->optional = $optional;
        return $parameter;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function asPHPDocParam(): string
    {
        return trim("@param " . ($this->optional ? "?" : "") . ($this->docType ?? $this->type) . " \$$this->name $this->description");
    }

    public function asDeclaration(): string
    {
        return ($this->optional ? "?" : "") . "$this->type \$$this->name" . ($this->optional ? " = null" : "");
    }
}
