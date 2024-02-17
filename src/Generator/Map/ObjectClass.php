<?php

namespace Battis\OpenAPI\Generator\Map;

use Battis\OpenAPI\Generator\CodeComponent\PHPClass;
use Battis\OpenAPI\Generator\CodeComponent\Property;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;

class ObjectClass extends PHPClass
{
    private string $path;

    public function getPath(): string
    {
        return $this->path;
    }

    public static function fromSchema(string $name, Schema $schema, ObjectMap $map): ObjectClass
    {
        $class = new ObjectClass($map->logger);

        $nameParts = explode('.', $map->sanitize->clean($name));
        $class->path = join("/", $nameParts);
        $class->name = array_pop($nameParts);
        $class->namespace = $map->parseType(count($nameParts) > 0 ? join("/", $nameParts) : null);

        $class->baseType = $map->baseType;
        $class->description = $schema->description;

        $map->map->registerSchema($name, "$class->namespace\\$class->name");
        $class->log("$class->namespace\\$class->name");

        $fields = [];
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
            $class->addProperty(Property::documentationOnly((string) $name, $type, $property->description));
        }
        $fields = Property::protectedStatic('fields', 'array', null, json_encode($fields, JSON_PRETTY_PRINT));
        $fields->setDocType('string[]');
        $class->addProperty($fields);
        return $class;
    }

}
