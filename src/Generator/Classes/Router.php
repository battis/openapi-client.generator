<?php

namespace Battis\OpenAPI\Generator\Classes;

use Battis\DataUtilities\Path;
use Battis\OpenAPI\Generator\Mappers\EndpointMapper;
use Battis\OpenAPI\Generator\TypeMap;
use Battis\PHPGenerator\Method;
use Battis\PHPGenerator\Method\ReturnType;
use Battis\PHPGenerator\Property;

class Router extends Writable
{
    /**
     * @param string $namespace
     * @param EndpointClass[] $classes
     */
    public static function fromClassList(string $namespace, array $classes, EndpointMapper $endpointMap): Router
    {
        $class = new Router();
        $class->baseType = $endpointMap->baseType;
        $class->description = "Routing class for the namespace $namespace";

        $namespaceParts = explode("\\", $namespace);
        $class->name = array_pop($namespaceParts);
        $class->namespace = join("\\", $namespaceParts);
        $namespaceParts = array_slice($namespaceParts, count(explode("\\", $endpointMap->baseNamespace)));
        $class->path = Path::join($namespaceParts, ['..', $class->name]);

        foreach($classes as $c) {
            $propName = "_" . lcfirst($c->getName());
            $class->addProperty(Property::private($propName, $c->getType(), $c->getDescription()));
            $class->addMethod(
                Method::public(
                    lcfirst($c->getName()),
                    ReturnType::from($c->getType()),
                    "if (\$this->$propName === null) {" . PHP_EOL .
                    "\$this->$propName = new " . TypeMap::parseType($c->getType(), false) . "(\$this->api);" . PHP_EOL .
                    "}" . PHP_EOL .
                    "return \$this->$propName;"
                )
            );
            $class->uses[] = $c->getType();
        }
        return $class;
    }
}
