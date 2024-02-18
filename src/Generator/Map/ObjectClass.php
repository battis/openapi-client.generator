<?php

namespace Battis\OpenAPI\Generator\Map;

use Battis\DataUtilities\Path;
use Battis\OpenAPI\Generator\CodeComponent\PHPClass;
use Battis\OpenAPI\Generator\CodeComponent\Property;
use Battis\OpenAPI\Generator\TypeMap;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;

class ObjectClass extends PHPClass
{
    private string $path;

    public function getPath(): string
    {
        return $this->path;
    }

    public static function fromSchema(string $ref, Schema $schema, ObjectMap $objectMap): ObjectClass
    {
        $map = TypeMap::getInstance();

        $class = new ObjectClass();

        $type = $map->getTypeFromSchema($ref);
        $nameParts = explode("\\", $type);
        $class->name = array_pop($nameParts);
        $class->namespace = Path::join("\\", $nameParts);
        $nameParts = array_slice($nameParts, count(explode("\\", $objectMap->baseNamespace)));
        $class->path = join("/", $nameParts);

        $class->baseType = $objectMap->baseType;
        $class->description = $schema->description;

        $class->log("Building $class->namespace\\$class->name");

        $fields = [];
        foreach ($schema->properties as $name => $property) {
            $type = null;
            if ($property instanceof Reference) {
                $ref = $property->getReference();
                $property = $property->resolve();
                $type = $map->getTypeFromSchema($ref, true, true);
            }
            /** @var Schema $property (because we just resolved it)*/

            $fields[] = $name;
            $method = $property->type;
            $type ??= (string) $map->$method($property, true);
            // TODO handle enums
            $class->addProperty(Property::documentationOnly((string) $name, $type, $property->description));
        }
        $fields = Property::protectedStatic('fields', 'array', null, json_encode($fields, JSON_PRETTY_PRINT));
        $fields->setDocType('string[]');
        $class->addProperty($fields);
        return $class;
    }

}
