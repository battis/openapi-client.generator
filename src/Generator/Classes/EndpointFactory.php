<?php

namespace Battis\OpenAPI\Generator\Classes;

use Battis\DataUtilities\Path;
use Battis\DataUtilities\Text;
use Battis\OpenAPI\Client\Exceptions\ArgumentException;
use Battis\OpenAPI\Generator\Classes\Method\Parameter;
use Battis\OpenAPI\Generator\Classes\Method\ReturnType;
use Battis\OpenAPI\Generator\Classes\Property;
use Battis\OpenAPI\Generator\Exceptions\GeneratorException;
use Battis\OpenAPI\Generator\Exceptions\SchemaException;
use Battis\OpenAPI\Generator\Mappers\EndpointMapper;
use Battis\OpenAPI\Generator\TypeMap;
use Battis\PHPGenerator\Access;
use Battis\PHPGenerator\Type;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Parameter as SpecParameter;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\RequestBody;
use cebe\openapi\spec\Response;
use Psr\Log\LoggerInterface;

class EndpointFactory
{
    private ?LoggerInterface $logger = null;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param string $path Relative path to class from `BaseMapper->getBasePath()`
     * @param string $namespace
     * @param null|string|\Battis\PHPGenerator\Type $baseType
     * @param ?string $description
     */
    public function create(
        string $path,
        string $namespace,
        $baseType = null,
        ?string $description = null
    ): Endpoint {
        return new Endpoint(
            $this->logger,
            $path,
            $namespace,
            $baseType,
            $description
        );
    }

    public function fromPathItem(
        string $path,
        PathItem $pathItem,
        EndpointMapper $mapper,
        string $url
    ): Endpoint {
        $typeMap = TypeMap::getInstance();

        $normalizedPath = $this->normalizePath($path);
        $dir = dirname($normalizedPath);
        if ($dir === '.' || $dir === '/') {
            $dir = null;
        }

        $class = $this->create(
            $normalizedPath,
            Path::join('\\', [
                $mapper->getBaseNamespace(),
                $dir === null ? [] : explode('/', $dir),
            ]),
            $mapper->getBaseType(),
            $pathItem->description
        );

        $class->addProperty(
            new Property(
                Access::Protected,
                'url',
                'string',
                'Endpoint URL pattern',
                "\"$url\""
            )
        );

        foreach ($mapper->supportedOperations() as $operation) {
            if ($pathItem->$operation) {
                $this->logger->info(strtoupper($operation) . ' ' . $url);

                $instantiate = false;

                /** @var \cebe\openapi\spec\Operation $op */
                $op = $pathItem->$operation;
                assert(
                    $op->responses !== null,
                    new SchemaException("$operation $url has no responses")
                );

                $parameters = $this->methodParameters($op);

                $requestBody = $op->requestBody;
                // FIXME handle schema ref for request body
                assert(
                    $requestBody === null ||
                        $requestBody instanceof RequestBody,
                    new GeneratorException(
                        'Not ready to handle schema ref for request body'
                    )
                );
                if ($requestBody !== null) {
                    $schema =
                        $requestBody->content[$mapper->expectedContentType()]
                            ->schema;
                    assert(
                        $schema !== null,
                        new SchemaException('Missing schema for response')
                    );
                    $fqn = $typeMap->getFQNFromSchema($schema);
                    $requestBody = new Parameter(
                        'requestBody',
                        $fqn,
                        null,
                        $requestBody->description
                    );
                }

                // return type
                $responses = $op->responses;
                $resp = is_array($responses)
                    ? $responses['200'] ?? $responses['201']
                    : $responses->getResponse('200') ??
                        $responses->getResponse('201');
                assert(
                    $resp instanceof Response,
                    new SchemaException("$operation $url has no OK response")
                );
                $content = $resp->content;
                $content = $content[$mapper->expectedContentType()] ?? null;

                $fqn = 'mixed';
                $type = new Type($fqn);

                if ($content !== null) {
                    $schema = $content->schema;
                    assert(
                        $schema !== null,
                        new SchemaException(
                            'Content schema not defined: ' .
                                json_encode(
                                    $content->getSerializableData(),
                                    JSON_PRETTY_PRINT
                                )
                        )
                    );
                    $fqn = $typeMap->getFQNFromSchema($schema);
                    $type = new Type($fqn);
                    if ($schema instanceof Reference) {
                        $class->addUses($type);
                        $instantiate = true;
                    } elseif ($type->isArray()) {
                        $eltType = $type->getArrayElementType();
                        if ($eltType !== null) {
                            $instantiate = true;
                            $class->addUses($eltType);
                        }
                    }
                }

                $pathArg =
                    '[' .
                    join(
                        ',' . PHP_EOL,
                        array_map(
                            fn(Parameter $p) => "\"" .
                                $p->getName() .
                                "\" => \$params['" .
                                $p->getName() .
                                "']",
                            $parameters['path']
                        )
                    ) .
                    ']';
                $queryArg =
                    '[' .
                    join(
                        ',' . PHP_EOL,
                        array_map(
                            fn(Parameter $p) => "\"" .
                                $p->getName() .
                                "\" => \$params['" .
                                $p->getName() .
                                "']",
                            $parameters['query']
                        )
                    ) .
                    ']';

                $body =
                    'return ' .
                    $this->instantiate(
                        $instantiate,
                        $type,
                        "\$this->send(\"$operation\", $pathArg, $queryArg" .
                            ($requestBody !== null
                                ? ", $" . $requestBody->getName()
                                : '') .
                            ')'
                    ) .
                    ';';

                $params = array_merge(
                    $parameters['path'],
                    $parameters['query']
                );
                if ($requestBody !== null) {
                    assert(
                        !in_array(
                            $requestBody->getName(),
                            array_map(
                                fn(Parameter $p) => $p->getName(),
                                $params
                            )
                        ),
                        new GeneratorException(
                            'requestBody already exists as path or query parameter'
                        )
                    );
                    $params[] = $requestBody;
                }
                $assertions = [];
                foreach ($params as $param) {
                    if ($param->isOptional() === false) {
                        $assertions[] =
                            "assert(isset(\$params['" .
                            $param->getName() .
                            "']), new ArgumentException(\"Parameter `" .
                            $param->getName() .
                            "` is required\"));" .
                            PHP_EOL;
                        $class->addUses(ArgumentException::class);
                    }
                }
                $assertions = join($assertions);
                /** @var ReturnType[] $throws */
                $throws = [];
                if (!empty($assertions)) {
                    $body = $assertions . PHP_EOL . $body;
                    $throws[] = new ReturnType(
                        ArgumentException::class,
                        'if required parameters are not defined'
                    );
                }

                $method = new JSStyleMethod(
                    Access::Public,
                    $this->getMethodNameForOperation(
                        $operation,
                        $op,
                        $url,
                        $parameters,
                        $type
                    ),
                    $params,
                    new ReturnType($type, $resp->description),
                    $body,
                    $op->description,
                    $throws
                );
                $class->addMethod($method);
            }
        }
        return $class;
    }

    protected function instantiate(
        bool $instantiate,
        Type $type,
        string $arg
    ): string {
        if ($instantiate) {
            if ($type->isArray()) {
                $eltType = $type->getArrayElementType();
                assert(
                    $eltType !== null,
                    new GeneratorException(
                        'Attempting to instantiate unclear array definition: ' .
                            $type->as(Type::FQN)
                    )
                );
                return "array_map(fn(\$a) => new " .
                    $eltType->as(Type::SHORT) .
                    "(\$a), {$arg})";
            } else {
                return 'new ' . $type->as(Type::SHORT) . '(' . $arg . ')';
            }
        } else {
            return $arg;
        }
    }

    /**
     * Parse parameter information from an operation
     *
     * @param \cebe\openapi\spec\Operation $operation
     *
     * @return array{path: Parameter[], query: Parameter[]}
     */
    protected function methodParameters(Operation $operation): array
    {
        $typeMap = TypeMap::getInstance();
        $parameters = [
            'path' => [],
            'query' => [],
        ];
        foreach ($operation->parameters as $parameter) {
            // FIXME deal with parameters that are schema refs
            assert(
                $parameter instanceof SpecParameter,
                new GeneratorException('Unexpected parameter reference')
            );
            assert(
                $parameter->schema !== null,
                new SchemaException('Undefined schema for parameter')
            );
            $fqn = $typeMap->getFQNFromSchema($parameter->schema);
            if ($parameter->in === 'path') {
                $parameters['path'][] = new Parameter(
                    $parameter->name,
                    $fqn,
                    $parameter->schema->default,
                    $parameter->description,
                    !$parameter->required
                        ? Parameter::NULLABLE
                        : Parameter::NONE
                );
            } elseif ($parameter->in === 'query') {
                $parameters['query'][] = new Parameter(
                    $parameter->name,
                    $fqn,
                    $parameter->schema->default,
                    $parameter->description,
                    !$parameter->required
                        ? Parameter::NULLABLE
                        : Parameter::NONE
                );
            }
        }
        return $parameters;
    }

    /**
     * @param string $operation
     * @param Operation $operationDescription
     * @param string $url
     * @param array{path: Parameter[], query: Parameter[]} $parameters
     * @param \Battis\PHPGenerator\Type $returnType
     *
     * @return string
     */
    protected function getMethodNameForOperation(
        string $operation,
        Operation $operationDescription,
        string $url,
        array $parameters,
        Type $returnType
    ): string {
        $delimiter = '';
        switch ($operation) {
            case 'post':
                $delimiter = '_to_';
                break;
            case 'patch':
                $delimiter = '_on_';
                break;
            default:
                $delimiter = '_by_';
        }
        return $operation .
            Text::snake_case_to_PascalCase(
                (count($parameters['path']) > 0 ? $delimiter : '') .
                    join(
                        '_and_',
                        array_map(
                            fn(Parameter $p) => $p->getName(),
                            $parameters['path']
                        )
                    )
            );
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
    protected function normalizePath(string $path): string
    {
        $parts = explode('/', $path);
        $namespaceParts = [];
        foreach ($parts as $part) {
            if (preg_match('/\{([^}]+)\}/', $part, $match)) {
            } else {
                $namespaceParts[] = Text::snake_case_to_PascalCase(
                    Text::camelCase_to_snake_case($part)
                );
            }
        }
        return (substr($path, 0, 1) === '/' ? '/' : '') .
            join('/', $namespaceParts);
    }
}
