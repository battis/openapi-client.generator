<?php

namespace Battis\OpenAPI\Generator\CodeComponent;

use Battis\OpenAPI\Generator\Map\ObjectMap;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;

class ClassObject extends BaseComponent {
    
    public string $namespace;
    
    public ?string $description;
    
    public string $name;
    
    public string $baseType;
    
    /**
     * @var Property[] $properties   
     */
    public array $properties = [];
    
    /**
     * @var Method[]
     */
    public array $methods = [];
    
    public static function fromSchema(string $name, Schema $schema, ObjectMap $map) {
        $class = new ClassObject($map->logger);
        $class->name = $map->sanitize->clean($name);
        $class->namespace = $map->parseType();
        $class->baseType = $map->baseType;
        $class->description = $schema->description;
        
        $filePath = $map->parseFilePath($name);
        $map->map->registerType("$class->namespace\\$class->name", $filePath);
        $class->log("$class->namespace\\$class->name");
        
        $fields =[];
        foreach ($schema->properties as $name => $property) {
            $type = null;
            if ($property instanceof Reference) {
                $ref = $property->getReference();
                $property = $property->resolve();
                $type = $map->map->getTypeFromSchema($ref, true, true);
            }
            /** @var Schema $property (because we just resolved it)*/
        
            $fields[] = $name;
            $method = $property->type;
            $type ??= (string) $map->map->$method($property, true);
            // TODO handle enums
            $class->addProperty(Property::documentationOnly($name, $type, $property->description));
        }
        $fields = Property::protectedStatic('fields', 'array', null, json_encode($fields, JSON_PRETTY_PRINT));
        $fields->setDocType('string[]');
        $class->addProperty($fields);
        return $class;
    }
        
    public function addProperty(Property $property) {
        $this->properties[] = $property;
    }    
    
    public function __toString()
    {
        $classDoc = new PHPDoc($this->logger);
        if (!empty($this->description)) {
            $classDoc->addItem($this->description);
        }
        $properties = [];
        foreach($this->properties as $prop) {
            if ($prop->isDocumentationOnly()) {
                $classDoc->addItem($prop->asPHPDocProperty());
            } else {
                $properties[] = $prop->asDeclaration();
            }
        }
        $classDoc->addItem("@api");
            
        return "<?php" . PHP_EOL . PHP_EOL.
        "namespace " .$this->namespace.";".PHP_EOL.PHP_EOL.
        (empty($this->baseType) ? "" : "use $this->baseType;" . PHP_EOL . PHP_EOL) .
        $classDoc->asString(0).
        "class " .$this->name . (empty($this->baseType) ?"":" extends " . (substr($this->baseType, strrpos($this->baseType, "\\", -1) + 1))) . PHP_EOL .
        "{".PHP_EOL.
        join(PHP_EOL, $properties) . PHP_EOL .
        (empty($this->methods)? "" : PHP_EOL . join(PHP_EOL , $this->methods)) .
        "}" . PHP_EOL;
    }
}