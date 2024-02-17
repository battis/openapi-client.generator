<?php

namespace Battis\OpenAPI\Generator\Map;

use Battis\DataUtilities\Text;
use Battis\Loggable\Loggable;
use Battis\OpenAPI\Generator\CodeComponent\Method;
use Battis\OpenAPI\Generator\CodeComponent\Method\Parameter;
use Battis\OpenAPI\Generator\CodeComponent\Method\ReturnType;
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
    private string $normalizedPath;
    private string $url;

    public function getNormalizedPath(): string
    {
        return $this->normalizedPath;
    }

    public function mergeWith(EndpointClass $other)
    {
        // merge $url properties, taking longest one
        $thisUrlProps = array_filter($this->properties, fn(Property $prop) => $prop->getName() === 'url');
        assert(count($thisUrlProps) === 1, new GeneratorException("multiple URL properties"));
        $thisUrlProp = $thisUrlProps[0];

        $otherUrlProps = array_filter($other->properties, fn(Property $prop) => $prop->getName() === 'url');
        assert(count($otherUrlProps) === 1, new GeneratorException("multiple URL properties"));
        $otherUrlProp = $otherUrlProps[0];

        $base = $thisUrlProp->getDefaultValue();
        $base = substr($base, 1, strlen($base) - 2);
        $extension = $otherUrlProp->getDefaultValue();
        $extension = substr($extension, 1, strlen($extension) - 2);
        if ($base !== $extension) {
            if (strlen($base) > strlen($extension)) {
                $temp = $base;
                $base = $extension;
                $extension = $temp;
            }
            $this->log("Merging $base and $extension into one endpoint", Loggable::WARNING);

            $this->removeProperty($thisUrlProp);
            $other->removeProperty($otherUrlProp);
            $this->addProperty(Property::protectedStatic('url', 'string', null, "\"$extension\""));
        } else {
            $other->removeProperty($otherUrlProp);
        }

        // testing to make sure there are no other duplicate properties
        $thisProperties = array_map(fn(Property $p) => $p->getName(), $this->properties);
        $otherProperties = array_map(fn(Property $p) => $p->getName(), $other->properties);
        $duplicateProperties = array_intersect($thisProperties, $otherProperties);
        assert(count($duplicateProperties) === 0, new GeneratorException("Duplicate properties in merge: " . var_export($duplicateProperties, true)));


        $thisMethods = array_map(fn(Method $m) => $m->getName(), $this->methods);
        $otherMethods = array_map(fn(Method $m) => $m->getName(), $other->methods);
        $duplicateMethods = array_intersect($thisMethods, $otherMethods);
        assert(count($duplicateMethods) === 0, new GeneratorException("Duplicate methods in merge: " . var_export($duplicateMethods, true)));

        $this->uses = array_merge($this->uses, $other->uses);
        $this->properties = array_merge($this->properties, $other->properties);
        $this->methods = array_merge($this->methods, $other->methods);
    }

    public static function fromPathItem(string $path, PathItem $pathItem, EndpointMap $map, string $url): PHPClass
    {
        $class = new EndpointClass();
        $class->description = $pathItem->description;
        $class->baseType = $map->baseType;
        $class->addProperty(Property::protectedStatic('url', 'string', null, "\"$url\""));
        $class->url = $url;
        $class->normalizedPath = static::normalizePath($path);
        $class->name = $map->sanitize->clean(basename($class->normalizedPath));
        $dir = dirname($class->normalizedPath);
        if ($dir === "." || $dir === "/") {
            $dir = null;
        }
        $class->namespace = $map->parseType($dir);

        preg_match_all("/\{([^}]+)\}\//", $class->url, $match, PREG_PATTERN_ORDER);
        $operationSuffix = Text::snake_case_to_PascalCase((!empty($match[1]) ? "by_" : "") . join("_and_", array_map(fn(string $p) => str_replace("_id", "", $p), $match[1])));

        foreach ($map->supportedOperations() as $operation) {
            if ($pathItem->$operation) {
                $map->log(strtoupper($operation) . " " . $url);

                $instantiate = false;

                $op = $pathItem->$operation;
                assert(is_a($op, Operation::class), new GeneratorException());
                /** @var Operation $op */
                assert(
                    $op->responses !== null,
                    new SchemaException("$operation $url has no responses")
                );

                $parameters = self::methodParameters($map, $op);

                $requestBody = $op->requestBody;
                if ($requestBody !== null) {
                    $docType = null;
                    $schema = $requestBody->content[$map->expectedContentType()]->schema;
                    $type = null;
                    if ($schema instanceof Reference) {
                        $type = $map->map->getTypeFromSchema($schema->getReference());
                        $class->addUses($type);
                    } else { /** @var Schema $schema */
                        $method = $schema->type;
                        $type = $schema->type;
                        $docType = $map->map->$method($schema, true);
                    }
                    $requestBody = Parameter::from('requestBody', $type, $requestBody->description);
                    if ($docType !== null) {
                        $requestBody->setDocType($docType);
                    }
                }

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

                $pathArg = "[" . join("," . PHP_EOL, array_map(fn(Parameter $p) => "\"{" . $p->getName() . "}\" => \$" . $p->getName(), $parameters["path"])) . "]";
                $queryArg = "[" . join("," . PHP_EOL, array_map(fn(Parameter $p) => "\"" . $p->getName() . "\" => $" . $p->getName(), $parameters["query"])) . "]";

                $body = "return " . self::instantiate(
                    $instantiate,
                    $type,
                    "\$this->send(\"$operation\", $pathArg, $queryArg" . ($requestBody !== null ? ", $" . $requestBody->getName() : "") . ")"
                ) .
                ";";

                if ($operation === 'get') {
                    if (count($parameters['path']) === 0) {
                        if (count($parameters['query']) === 0) {
                            $operation .= 'All';
                        } else {
                            $operation = 'filterBy';
                        }
                    }
                }

                $params = array_merge($parameters['path'], $parameters['query']);
                if ($requestBody !== null) {
                    assert(!in_array($requestBody->getName(), array_map(fn(Parameter $p) => $p->getName(), $params)), new GeneratorException('requestBody already exists as path or query parameter'));
                    $params[] = $requestBody;
                }

                $class->addMethod(Method::public($operation . $operationSuffix, ReturnType::from($type, $resp->description), $body, $op->description, $params));
            }
        }
        return $class;
    }

    protected static function instantiate(bool $instantiate, string $type, string $arg): string
    {
        if ($instantiate) {
            return "new " . TypeMap::parseType($type, false) . "(" . $arg . ")";
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
        foreach($parts as $part) {
            if (preg_match("/\{([^}]+)\}/", $part, $match)) {

            } else {
                $namespaceParts[] = $part;
            }
        }
        return (substr($path, 0, 1) === "/" ? "/" : "") .
          join("/", $namespaceParts);
    }
}
