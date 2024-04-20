<?php

namespace Battis\OpenAPI\Generator\Classes;

use Battis\DataUtilities\Path;
use Battis\OpenAPI\Generator\Classes\Property;
use Battis\OpenAPI\Generator\Mappers\EndpointMapper;
use Battis\PHPGenerator\Access;
use Battis\PHPGenerator\Type;

class Router extends Writable
{
    /**
     * @param string $namespace
     * @param Writable[] $classes
     */
    public static function fromClassList(
        string $namespace,
        array $classes,
        EndpointMapper $mapper
    ): Router {
        $namespaceParts = explode('\\', $namespace);
        $name = array_pop($namespaceParts);
        $description = "Routing class for the subnamespace `$name`";
        $namespace = join('\\', $namespaceParts);
        $baseNamespaceParts = explode('\\', $mapper->getBaseNamespace());
        if (count($baseNamespaceParts) > count($namespaceParts)) {
            $namespaceParts = '..';
            $name = $mapper->rootRouterName();
            $description =
                'Routing class for ' .
                ucfirst(basename(dirname($mapper->getBasePath())));
        } else {
            $namespaceParts = array_slice(
                $namespaceParts,
                count($baseNamespaceParts)
            );
        }
        $path = Path::join($namespaceParts, $name);

        $class = new Router(
            $path,
            $namespace,
            $mapper->getBaseType(),
            $description
        );

        $class->addProperty(
            new Property(
                Access::Protected,
                'endpoints',
                'array<string, class-string<' .
                    $mapper->getBaseType()->as(Type::ABSOLUTE) .
                    '>>',
                'Routing subpaths',
                '[' .
                    PHP_EOL .
                    '    ' .
                    join(
                        ',' . PHP_EOL . '    ',
                        array_map(
                            fn(Writable $c) => "\"" .
                                lcfirst($c->getName()) .
                                "\" => \"" .
                                $c->getType()->as(Type::ABSOLUTE) .
                                "\"",
                            $classes
                        )
                    ) .
                    PHP_EOL .
                    ']'
            )
        );

        foreach ($classes as $c) {
            $propName = '_' . lcfirst($c->getName());
            $class->addProperty(
                new Property(
                    Access::Protected,
                    $propName,
                    $c->getType()->nullable(),
                    $c->getDescription(),
                    'null'
                )
            );
            $class->addProperty(
                new Property(
                    Access::Public,
                    lcfirst($c->getName()),
                    $c->getType(),
                    $c->getDescription(),
                    null,
                    Property::DOCUMENTATION_ONLY
                )
            );
            $class->addUses($c->getType());
        }
        return $class;
    }
}
