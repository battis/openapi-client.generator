<?php

namespace Battis\OpenAPI\Generator\CodeComponent\Method;

use Battis\OpenAPI\Generator\CodeComponent\BaseComponent;
use Battis\OpenAPI\Generator\TypeMap;

class ReturnType extends BaseComponent
{
    private string $type;
    
    private ?string $docType;

    private ?string $description;

    public static function from(string $type, ?string $description = null, ?string $docType = null): ReturnType
    {
        $returnType = new ReturnType();
        $returnType->type = $type;
        $returnType->description = $description;
        $returnType->docType = $docType;
        return $returnType;
    }

    public function getType(): string
    {
        return $this->type;
    }
    
    public function getDocTyoe(): ?string
    {
        return $this->docType;
    }

    public function asPHPDocReturn(): string
    {
        return "@return " . TypeMap::parseType($this->docType ?? $this->type, true, true) . ($this->description ? " $this->description" : "");
    }

    public function asPHPDocThrows(): string
    {
        return "@throws " . TypeMap::parseType($this->docType ?? $this->type, true, true) . ($this->description ? " $this->description" : "");
    }
}
