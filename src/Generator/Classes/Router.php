<?php

namespace Battis\OpenAPI\Generator\Classes;

use Battis\DataUtilities\Path;
use Battis\OpenAPI\Generator\Mappers\EndpointMapper;
use Battis\PHPGenerator\Property;

class Router extends Writable
{
    /**
     * @param string $namespace
     * @param Endpoint[] $classes
     */
    public static function fromClassList(
        string $namespace,
        array $classes,
        EndpointMapper $mapper
    ): Router {
        $class = new Router();
        $class->baseType = $mapper->getBaseType();
        $class->description = "Routing class for the namespace $namespace";

        $namespaceParts = explode("\\", $namespace);
        $class->name = array_pop($namespaceParts);
        $class->namespace = join("\\", $namespaceParts);
        $baseNamespaceParts = explode("\\", $mapper->getBaseNamespace());
        if (count($baseNamespaceParts) > count($namespaceParts)) {
            $namespaceParts = "..";
            $class->name = $mapper->rootRouterName();
        } else {
            $namespaceParts = array_slice(
                $namespaceParts,
                count($baseNamespaceParts)
            );
        }
        $class->path = Path::join($namespaceParts, $class->name);

        $endpoints = Property::protected(
            "endpoints",
            "array",
            "Routing subpaths",
            "[" .
            PHP_EOL .
            "    " .
            join(
                "," . PHP_EOL . "    ",
                array_map(
                    fn(Writable $c) => "\"" .
                    lcfirst($c->getName()) .
                    "\" => \"" .
                    Property::typeAs($c->getType(), Property::TYPE_ABSOLUTE) .
                    "\"",
                    $classes
                )
            ) .
            PHP_EOL .
            "]"
        );
        $endpoints->setDocType("array<string, class-string<\Battis\OpenAPI\Client\BaseEndpoint>>" . Property::typeAs($mapper->getBaseType(), Property::TYPE_ABSOLUTE) . ">>");
        $class->addProperty($endpoints);

        foreach ($classes as $c) {
            $propName = "_" . lcfirst($c->getName());
            $class->addProperty(
                Property::protected(
                    $propName,
                    $c->getType(),
                    $c->getDescription(),
                    "null",
                    true
                )
            );
            $class->addProperty(
                Property::public(
                    lcfirst($c->getName()),
                    $c->getType(),
                    $c->getDescription(),
                    null,
                    false,
                    true
                )
            );
            $class->uses[] = $c->getType();
        }
        return $class;
    }
}
