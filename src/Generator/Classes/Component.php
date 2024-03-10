<?php

namespace Battis\OpenAPI\Generator\Classes;

use Battis\DataUtilities\Path;
use Battis\OpenAPI\Generator\Exceptions\GeneratorException;
use Battis\OpenAPI\Generator\Exceptions\SchemaException;
use Battis\OpenAPI\Generator\Mappers\ComponentMapper;
use Battis\OpenAPI\Generator\TypeMap;
use Battis\PHPGenerator\Access;
use Battis\PHPGenerator\Type;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;

class Component extends Writable
{
    public static function fromSchema(
        string $ref,
        Schema $schema,
        ComponentMapper $mapper
    ): Component {
        $typeMap = TypeMap::getInstance();

        $type = $typeMap->getTypeFromReference($ref);
        assert($type !== null, new GeneratorException("Unknown scheam `$ref`"));
        $nameParts = explode(
            "\\",
            str_replace($mapper->getBaseNamespace() . "\\", "", $type->as(Type::FQN))
        );
        $name = array_pop($nameParts);
        $path = Path::join($nameParts, $name);

        $class = new Component(
            $path,
            Path::join("\\", [$mapper->getBaseNamespace(), $nameParts]),
            $mapper->getBaseType(),
            $schema->description
        );

        $fields = [];
        foreach ($schema->properties as $name => $property) {
            $fqn = $typeMap->getFQNFromSchema($property);
            if ($property instanceof Reference) {
                $property = $property->resolve();
                // FIXME deal with objects as properties -- may already be handled in TypeMap?
                assert(
                    $property instanceof Schema,
                    new SchemaException("Unexpected object")
                );
            }

            $class->addProperty(
                new Property(
                    Access::Public,
                    (string) $name,
                    $fqn,
                    $property->description,
                    null,
                    Property::DOCUMENTATION_ONLY | ($property->nullable === true ? Property::NULLABLE : Property::NONE)
                )
            );
            $type = new Type($fqn);
            $fields[] =
              "\"$name\" => \"" . ($type->isMixed() ? $type->as(Type::PHP) : $type->as(Type::ABSOLUTE)) . "\"";
        }
        $fields = new Property(
            Access::Protected,
            "fields",
            "string[]",
            null,
            "[" .
            PHP_EOL .
            str_repeat(" ", 4) . join("," . PHP_EOL . str_repeat(" ", 4), $fields) . PHP_EOL .
            "]",
            Property::STATIC
        );
        $class->addProperty($fields);
        return $class;
    }
}
