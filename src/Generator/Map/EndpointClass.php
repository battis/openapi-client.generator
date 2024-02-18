<?php

namespace Battis\OpenAPI\Generator\Map;

use Battis\DataUtilities\Path;
use Battis\DataUtilities\Text;
use Battis\Loggable\Loggable;
use Battis\OpenAPI\Client\Exceptions\ArgumentException;
use Battis\OpenAPI\Generator\CodeComponent\Method;
use Battis\OpenAPI\Generator\CodeComponent\Method\Parameter;
use Battis\OpenAPI\Generator\CodeComponent\Method\ReturnType;
use Battis\OpenAPI\Generator\CodeComponent\PHPClass;
use Battis\OpenAPI\Generator\CodeComponent\Property;
use Battis\OpenAPI\Generator\Exceptions\GeneratorException;
use Battis\OpenAPI\Generator\Exceptions\SchemaException;
use Battis\OpenAPI\Generator\Sanitize;
use Battis\OpenAPI\Generator\TypeMap;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;

class EndpointClass extends PHPClass
{
    protected string $normalizedPath;
    protected string $url;

    public function getNormalizedPath(): string
    {
        return $this->normalizedPath;
    }

    public function mergeWith(EndpointClass $other)
    {
        // merge $url properties, taking longest one
        $thisUrlProps = array_filter($this->properties, fn(Property $prop) => $prop->getName() === 'url');
        $thisUrlProp = $thisUrlProps[0] ?? null;

        $otherUrlProps = array_filter($other->properties, fn(Property $prop) => $prop->getName() === 'url');
        $otherUrlProp = $otherUrlProps[0] ?? null;

        if ($thisUrlProp && $otherUrlProp) {
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

    public static function fromPathItem(string $path, PathItem $pathItem, EndpointMap $endpointMap, string $url): PHPClass
    {
        $map = TypeMap::getInstance();
        $sanitize = Sanitize::getInstance();

        $class = new EndpointClass();
        $class->description = $pathItem->description;
        $class->baseType = $endpointMap->baseType;
        $class->addProperty(Property::protectedStatic('url', 'string', null, "\"$url\""));
        $class->url = $url;
        $class->normalizedPath = static::normalizePath($path);
        $class->name = $sanitize->clean(basename($class->normalizedPath));
        $dir = dirname($class->normalizedPath);
        if ($dir === "." || $dir === "/") {
            $dir = null;
        }
        $class->namespace = Path::join("\\", [$endpointMap->baseNamespace, $dir === null ? [] : explode("/", $dir)]);

        preg_match_all("/\{([^}]+)\}\//", $class->url, $match, PREG_PATTERN_ORDER);
        $operationSuffix = Text::snake_case_to_PascalCase((!empty($match[1]) ? "by_" : "") . join("_and_", array_map(fn(string $p) => str_replace("_id", "", $p), $match[1])));

        foreach ($endpointMap->supportedOperations() as $operation) {
            if ($pathItem->$operation) {
                self::staticLog(strtoupper($operation) . " " . $url);

                $instantiate = false;

                $op = $pathItem->$operation;
                assert(is_a($op, Operation::class), new GeneratorException());
                /** @var Operation $op */
                assert(
                    $op->responses !== null,
                    new SchemaException("$operation $url has no responses")
                );

                $parameters = self::methodParameters($op);

                $requestBody = $op->requestBody;
                if ($requestBody !== null) {
                    $docType = null;
                    $schema = $requestBody->content[$endpointMap->expectedContentType()]->schema;
                    $type = null;
                    if ($schema instanceof Reference) {
                        $type = $map->getTypeFromSchema($schema->getReference());
                        $class->addUses($type);
                    } else { /** @var Schema $schema */
                        $method = $schema->type;
                        $type = $schema->type;
                        $docType = $map->$method($schema, true);
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
                $content = $content[$endpointMap->expectedContentType()] ?? null;
                $type = null;
                if ($content !== null) {
                    $schema = $content->schema;
                    if ($schema instanceof Reference) {
                        $ref = $schema->getReference();
                        $type = $map->getTypeFromSchema($ref);
                        $class->addUses($type);
                        $instantiate = true;
                    } elseif ($schema instanceof Schema) {
                        $method = $schema->type;
                        $type = (string) $map->$method($schema);
                        $t = substr($type,0,-2);
                        $instantiate = substr($type,-2) === '[]' && $map->getClassFromType($t) !== null;
                        if ($instantiate) {
                            $class->addUses($t);
                        }
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

                /** @var Parameter[] $params */
                $params = array_merge($parameters['path'], $parameters['query']);
                if ($requestBody !== null) {
                    assert(!in_array($requestBody->getName(), array_map(fn(Parameter $p) => $p->getName(), $params)), new GeneratorException('requestBody already exists as path or query parameter'));
                    $params[] = $requestBody;
                }
                $assertions = [];
                foreach($params as $param) {
                    if (!$param->isOptional()) {
                        $assertions[] = "assert(\$" . $param->getName() . " !== null, new ArgumentException(\"Parameter `" . $param->getName() . "` is required\"));" . PHP_EOL;
                        $class->uses[] = ArgumentException::class;
                    }
                }
                $assertions = join($assertions);
                $throws = [];
                if (!empty($assertions)) {
                    $body = $assertions . PHP_EOL . $body;
                    $throws[] = ReturnType::from(ArgumentException::class, "if required parameters are not defined");
                }

                $docType = null;
                if (substr($type, -2) === '[]') {
                    $docType = $type;
                    $type = 'array';
                }
                $returnType  = ReturnType::from($type, $resp->description, $docType);

                $method = Method::public(
                    $operation . $operationSuffix,
                    $returnType,
                    $body,
                    $op->description,
                    $params,
                    $throws
                );
                $class->addMethod($method);
                $applicableClass = $map->getClassFromType($method->getReturnType()->getType());
                if ($applicableClass !== null) {
                    self::staticLog("Potential to map " . $class->getType() . "::" . $method->getName() . "() as a static getter for " . $applicableClass->getType(), Loggable::NOTICE);
                }
            }
        }
        return $class;
    }

    protected static function instantiate(bool $instantiate, string $type, string $arg): string
    {
        if ($instantiate) {
            if (substr($type, -2) === '[]') { 
                return "array_map(fn(\$a) => new ". TypeMap::parseType(substr($type, 0, -2), false) ."(\$a), {$arg})";
            } else {
            return "new " . TypeMap::parseType($type, false) . "(" . $arg . ")";
}
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
     * @return array{method: string[], path: string[], query: string[]}
     */
    protected static function methodParameters(Operation $operation): array
    {
        $map = TypeMap::getInstance();
        $parameters = [
            'path' => [],
            'query' => [],
        ];
        foreach($operation->parameters as $parameter) {
            if ($parameter->schema instanceof Reference) {
                $ref = $parameter->schema->getReference();
                $parameterType = $map->getTypeFromSchema($ref);
            } else {
                $method = $parameter->schema->type;
                $parameterType = $map->$method($parameter);
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
     * Calculate a "normalized" path to the directory containing the class
     * based on its URL
     *
     * The process removes all path parameters from the url:
     * `/foo/{foo_id}/bar/{bar_id}/{baz}` would normalize to `/foo` for a
     * class named `Bar`.
     *
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
                $namespaceParts[] = Text::snake_case_to_PascalCase(Text::camelCase_to_snake_case($part));
            }
        }
        return (substr($path, 0, 1) === "/" ? "/" : "") .
          join("/", $namespaceParts);
    }
}
