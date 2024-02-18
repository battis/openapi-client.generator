<?php

namespace Battis\OpenAPI\Generator\Map;

use Battis\DataUtilities\Path;
use Battis\Loggable\Loggable;
use Battis\OpenAPI\Generator\CodeComponent\Method;
use Battis\OpenAPI\Generator\CodeComponent\Method\ReturnType;
use Battis\OpenAPI\Generator\CodeComponent\Property;
use Battis\OpenAPI\Generator\TypeMap;

class RouterClass extends EndpointClass
{
    /**
     * @param string $namespace
     * @param EndpointClass[] $classes
     */
    public static function fromClassList(string $namespace, array $classes, EndpointMap $endpointMap): RouterClass
    {
        $class = new RouterClass();
        $class->baseType = $endpointMap->baseType;
        $class->description = "Routing class for the namespace $namespace";

        $namespaceParts = explode("\\", $namespace);
        $class->name = array_pop($namespaceParts);
        $class->namespace = join("\\", $namespaceParts);
        $namespaceParts = array_slice($namespaceParts, count(explode("\\", $endpointMap->baseNamespace)));
        $class->normalizedPath = Path::join($namespaceParts, ['..']);

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
