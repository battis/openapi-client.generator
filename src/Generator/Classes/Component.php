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
                $type = $typeMap->getTypeFromSchema($ref, true, true);
            }
            /** @var Schema $property (because we just resolved it)*/

            $fields[] = $name;
            $method = $property->type;
            $type ??= (string) $typeMap->$method($property, true);
            // TODO handle enums
            $class->addProperty(Property::documentationOnly((string) $name, $type, $property->description));
        }
        $fields = Property::protectedStatic('fields', 'array', null, json_encode($fields, JSON_PRETTY_PRINT));
        $fields->setDocType('string[]');
        $class->addProperty($fields);
        return $class;
    }

}
