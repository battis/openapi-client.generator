<?php

namespace Battis\OpenAPI\Generator\Map;

use Battis\Loggable\Loggable;
use Battis\OpenAPI\Generator\CodeComponent\Method;
use Battis\OpenAPI\Generator\CodeComponent\Method\Parameter;
use Battis\OpenAPI\Generator\CodeComponent\PHPClass;
use Battis\OpenAPI\Generator\CodeComponent\Property;
use Battis\OpenAPI\Generator\Exceptions\GeneratorException;
use Battis\OpenAPI\Generator\Exceptions\SchemaException;
use Battis\OpenAPI\Generator\TypeMap;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;

class EndpointClass extends PHPClass
{
    public string $normalizedPath;

    public static function fromPathItem(string $path, PathItem $pathItem, EndpointMap $map, string $url): PHPClass
    {
        $class = new EndpointClass();
        $class->description = $pathItem->description;
        $class->baseType = $map->baseType;
        $class->addProperty(Property::protectedStatic('url', 'string', null, "\"$url\""));
        $class->normalizedPath = static::normalizePath($path);
        $class->name = $map->sanitize->clean(basename($class->normalizedPath));
        $dir = dirname($class->normalizedPath);
        if ($dir === "." || $dir === "/") {
            $dir = null;
        }
        $class->namespace = $map->parseType($dir);

        $uses = [BaseEndpoint::class];
        foreach ($map->supportedOperations() as $operation) {
            if ($pathItem->$operation) {
                $map->log(strtoupper($operation) . " " . $url);

                $instantiate = false;

                $op = $pathItem->$operation;
                assert(is_a($op, Operation::class), new GeneratorException());
                assert(
                    $op->responses !== null,
                    new SchemaException("$operation $url has no responses")
                );

                $parameters = self::methodParameters($map, $op);

                // return type
                $responses = $op->responses;
                /** @var ?\cebe\openapi\spec\Response $resp */
                $resp = is_array($responses)
                  ? $responses["200"] ?? $responses["201"]
                  : $responses->getResponse("200") ?? $responses->getResponse("201");
                assert(
                    $resp !== null,
                    new SchemaException("$operation $url has no OK response")
                );
                $content = $resp->content;
                $content = $content[$map->expectedContentType()] ?? null;
                $type = null;
                if ($content !== null) {
                    $schema = $content->schema;
                    if ($schema instanceof Reference) {
                        $ref = $schema->getReference();
                        $type = $map->map->getTypeFromSchema($ref);
                        $class->addUses($type);
                        if ($operation === "get" && $type !== null) {
                            $map->map->registerUrlGet($url, $type);
                        }
                        $instantiate = true;
                    } elseif ($schema instanceof Schema) {
                        $method = $schema->type;
                        $type = (string) $map->map->$method($schema);
                    }
                } else {
                    $type = "void";
                }
                assert(is_string($type), new GeneratorException('type undefined'));

                $pathArg = "[" . join("," . PHP_EOL, array_map(fn($p) => "\"{" . $p->getName() . "}\" => \$" . $p->getName(), $parameters["path"])) . "]";
                $queryArg = "[" . join("," . PHP_EOL, array_map(fn($p) => "\"" . $p->getName() . "\" => $" . $p->getName(), $parameters["query"])) . "]";

                $body = "return " . self::instantiate(
                    $instantiate,
                    $type,
                    "\$this->send(\"$operation\", $pathArg, $queryArg)"
                ) .
                ";";

                $class->addMethod(Method::public($operation, $type, $body, $op->description, array_merge($parameters['path'], $parameters['query'])));
            }
        }
        return $class;
    }

    protected static function instantiate(bool $instantiate, string $type, string $arg): string
    {
        if ($instantiate) {
            return "new " . TypeMap::parseType($type,false) . "(" . $arg . ")";
        } else {
            return $arg;
        }
    }

    /**
     * Parse parameter information from an operation
     *
     * @param \cebe\openapi\spec\Operation $operation
     * @param \Battis\OpenAPI\Generator\PHPDoc $doc
     *
     * @return array{method: string[], path: array<string, string>, query: array<string>string}
     */
    protected static function methodParameters(EndpointMap $map, Operation $operation): array
    {
        $parameters = [
            'path' => [],
            'query' => [],
        ];
        foreach($operation->parameters as $parameter) {
            $map->log($parameter->getSerializableData(), Loggable::DEBUG);
            if ($parameter->schema instanceof Reference) {
                $ref = $parameter->schema->getReference();
                $parameterType = $map->map->getTypeFromSchema($ref);
            } else {
                $method = $parameter->schema->type;
                $parameterType = $map->map->$method($parameter);
            }
            if ($parameter->in === 'path') {
                $parameters['path'][] = Parameter::from($parameter->name, $parameterType, ($parameter->required ? "" : "(Optional) ") . $parameter->description, !$parameter->required);
            } elseif ($parameter->in === 'query') {
                $parameters['query'][] = Parameter::from($parameter->name, $parameterType, ($parameter->required ? "" : "(Optional) ") . $parameter->description, !$parameter->required);
            }
        }
        return $parameters;
    }

    /**
     * @param string $path
     *
     * @return string
     */
    protected static function normalizePath(string $path): string
    {
        $parts = explode("/", $path);
        $namespaceParts = [];
        foreach ($parts as $part) {
            if (preg_match("/\{([^}]+)\}/", $part, $match)) {

            } else {
                $namespaceParts[] = $part;
            }
        }
        return (substr($path, 0, 1) === "/" ? "/" : "") .
          join("/", $namespaceParts);
    }
}
