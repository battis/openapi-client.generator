<?php

namespace Battis\OpenAPI\Generator\CodeComponent\Method;

use Battis\OpenAPI\Generator\CodeComponent\BaseComponent;
use Battis\OpenAPI\Generator\TypeMap;

class ReturnType extends BaseComponent
{
    private string $type;

    private ?string $description;

    public static function from(string $type, ?string $description = null): ReturnType
    {
        $returnType = new ReturnType();
        $returnType->type = $type;
        $returnType->description = $description;
        return $returnType;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function asPHPDocReturn(): string
    {
        return "@return " . TypeMap::parseType($this->type, true, true) . ($this->description ? " $this->description" : "");
    }

    public function asPHPDocThrows(): string
    {
        return "@throws " . TypeMap::parseType($this->type, true, true) . ($this->description ? " $this->description" : "");
    }
}
