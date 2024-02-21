<?php

namespace Battis\OpenAPI\Generator\Classes;

use Battis\DataUtilities\Path;
use Battis\OpenAPI\Generator\Mappers\ComponentMapper;
use Battis\OpenAPI\Generator\TypeMap;
use Battis\PHPGenerator\Property;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;

class Component extends Writable
{
    public static function fromSchema(string $ref, Schema $schema, ComponentMapper $mapper): Component
    {
        $typeMap = TypeMap::getInstance();

        $class = new Component();

        $type = $typeMap->getTypeFromSchema($ref);
        $nameParts = explode("\\", $type);
        $class->name = array_pop($nameParts);
        $class->namespace = Path::join("\\", $nameParts);
        $nameParts = array_slice($nameParts, count(explode("\\", $mapper->getBaseNamespace())));
        $class->path = Path::join($nameParts, $class->name);

        $class->baseType = $mapper->getBaseType();
        $class->description = $schema->description;

        $fields = [];
        foreach ($schema->properties as $name => $property) {
            $type = null;
            if ($property instanceof Reference) {
                $ref = $property->getReference();
                $property = $property->resolve();
                $type = $typeMap->getTypeFromSchema($ref);
            }
            /** @var Schema $property (because we just resolved it)*/

            $method = $property->type;
            $type ??= (string) $typeMap->$method($property);
            // TODO handle enums
            $class->addProperty(Property::public((string) $name, $type, $property->description, null, $property->nullable, true));
            $fields[] = "\"$name\" => \"" . TypeMap::parseType($type, true, true) . "\"";
        }
        $fields = Property::protectedStatic('fields', 'array', null, "[" . PHP_EOL . "    " . join("," . PHP_EOL . "    ", $fields) . PHP_EOL . "]");
        $fields->setDocType('string[]');
        $class->addProperty($fields);
        return $class;
    }

}
