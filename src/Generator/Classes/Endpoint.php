<?php

namespace Battis\OpenAPI\Generator\Classes;

use Battis\DataUtilities\Path;
use Battis\DataUtilities\Text;
use Battis\Loggable\Loggable;
use Battis\OpenAPI\Client\Exceptions\ArgumentException;
use Battis\OpenAPI\Generator\Exceptions\GeneratorException;
use Battis\OpenAPI\Generator\Exceptions\SchemaException;
use Battis\OpenAPI\Generator\Mappers\EndpointMapper;
use Battis\OpenAPI\Generator\Sanitize;
use Battis\OpenAPI\Generator\TypeMap;
use Battis\PHPGenerator\Method;
use Battis\PHPGenerator\Method\Parameter;
use Battis\PHPGenerator\Method\ReturnType;
use Battis\PHPGenerator\Property as PHPGeneratorProperty;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;

class Endpoint extends Writable
{
    protected string $url;

    public static function fromPathItem(string $path, PathItem $pathItem, EndpointMapper $mapper, string $url): Endpoint
    {
        $typeMap = TypeMap::getInstance();
        $sanitize = Sanitize::getInstance();

        $class = new Endpoint();
        $class->description = $pathItem->description;
        $class->baseType = $mapper->baseType;
        $class->addProperty(PHPGeneratorProperty::protectedStatic('url', 'string', null, "\"$url\""));
        $class->url = $url;
        $class->path = static::normalizePath($path);
        $class->name = $sanitize->clean(basename($class->path));
        $dir = dirname($class->path);
        if ($dir === "." || $dir === "/") {
            $dir = null;
        }
        $class->namespace = Path::join("\\", [$mapper->baseNamespace, $dir === null ? [] : explode("/", $dir)]);

        preg_match_all("/\{([^}]+)\}\//", $class->url, $match, PREG_PATTERN_ORDER);
        $operationSuffix = Text::snake_case_to_PascalCase((!empty($match[1]) ? "by_" : "") . join("_and_", array_map(fn(string $p) => str_replace("_id", "", $p), $match[1])));

        foreach ($mapper->supportedOperations() as $operation) {
            if ($pathItem->$operation) {
                Loggable::staticLog(strtoupper($operation) . " " . $url);

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
                    $schema = $requestBody->content[$mapper->expectedContentType()]->schema;
                    $type = null;
                    if ($schema instanceof Reference) {
                        $type = $typeMap->getTypeFromSchema($schema->getReference());
                        $class->addUses($type);
                    } else { /** @var Schema $schema */
                        $method = $schema->type;
                        $type = $schema->type;
                        $docType = $typeMap->$method($schema, true);
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
                $content = $content[$mapper->expectedContentType()] ?? null;
                $type = null;
                if ($content !== null) {
                    $schema = $content->schema;
                    if ($schema instanceof Reference) {
                        $ref = $schema->getReference();
                        $type = $typeMap->getTypeFromSchema($ref);
                        $class->addUses($type);
                        $instantiate = true;
                    } elseif ($schema instanceof Schema) {
                        $method = $schema->type;
                        $type = (string) $typeMap->$method($schema);
                        $t = substr($type, 0, -2);
                        $instantiate = substr($type, -2) === '[]' && $typeMap->getClassFromType($t) !== null;
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
                $applicableClass = $typeMap->getClassFromType($method->getReturnType()->getType());
                if ($applicableClass !== null) {
                    Loggable::staticLog("Potential to map " . $class->getType() . "::" . $method->getName() . "() as a static getter for " . $applicableClass->getType(), Loggable::NOTICE);
                }
            }
        }
        return $class;
    }

    protected static function instantiate(bool $instantiate, string $type, string $arg): string
    {
        if ($instantiate) {
            if (substr($type, -2) === '[]') {
                return "array_map(fn(\$a) => new " . TypeMap::parseType(substr($type, 0, -2), false) . "(\$a), {$arg})";
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
        $typeMap = TypeMap::getInstance();
        $parameters = [
            'path' => [],
            'query' => [],
        ];
        foreach($operation->parameters as $parameter) {
            if ($parameter->schema instanceof Reference) {
                $ref = $parameter->schema->getReference();
                $parameterType = $typeMap->getTypeFromSchema($ref);
            } else {
                $method = $parameter->schema->type;
                $parameterType = $typeMap->$method($parameter);
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
