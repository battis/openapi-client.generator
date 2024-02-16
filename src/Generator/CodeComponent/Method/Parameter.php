<?php

namespace Battis\OpenAPI\Generator\CodeComponent\Method;

use Battis\OpenAPI\Generator\CodeComponent\BaseComponent;
use Battis\OpenAPI\Generator\Exceptions\CodeComponentException;

class Parameter extends BaseComponent {
    private string $name;
    
    private string $type;
    
    private ?string $docType = null;
        
    private ?string $description;
        
    public function setDocType(string $docType) {
        $this->docType = $docType;
    }
    
    public static function from(string $name, string $type, ?string $description = null): Parameter {
        $parameter = new Parameter();
        $parameter->name = $name;
        $parameter->type = $type;
        $parameter->description = $description;
        return $parameter;
    }
    
    public function asPHPDocParam(): string {
        return trim("@param ".($this->docType ?? $this->type)." \$$this->name $this->description");
    }
    
    public function asDeclaration(): string {
        return "$this->type \$$this->name";
    }
}