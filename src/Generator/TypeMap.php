<?php

namespace Battis\OpenAPI\Generator;

use Battis\OpenAPI\Generator\Classes\Writable;
use Battis\OpenAPI\Generator\Exceptions\SchemaException;
use Battis\PHPGenerator\Type;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;

class TypeMap
{
    protected static ?TypeMap $instance = null;

    /**
     * @var array<string, \Battis\PHPGenerator\Type>
     */
    private array $refToType = [];

    /**
     * @var array<string, \Battis\OpenAPI\Generator\Classes\Writable>
     */
    private array $fqnToClass = [];

    /**
     * @var array<string, callable(): string | callable(\cebe\openapi\spec\Schema): string>
     */
    private array $openAPIToPHP;

    private function __construct()
    {
        $this->openAPIToPHP = [
            'string' => function (Schema $schema): string {
                if ($schema->enum !== null) {
                    return join(
                        '|',
                        array_map(
                            fn(string $value) => "\"$value\"",
                            $schema->enum
                        )
                    );
                }
                return 'string';
            },
            'number' => fn(): string => 'float',
            'integer' => fn(): string => 'int',
            'boolean' => fn(): string => 'bool',
            'array' => function (Schema $schema): string {
                assert(
                    $schema->items !== null,
                    new SchemaException("{$schema->title}->items not defined")
                );
                return $this->getFQNFromSchema($schema->items) . '[]';
            },
            'object' => function (Schema $schema): string {
                $properties = [];
                foreach ($schema->properties as $name => $property) {
                    $propertyType = new Type(
                        $this->getFQNFromSchema($property)
                    );
                    $properties[] =
                        "$name: " . $propertyType->as(Type::ABSOLUTE);
                }

                if (
                    $schema->additionalProperties !== true &&
                    $schema->additionalProperties !== false
                ) {
                    $properties[] =
                        '...<string, ' .
                        $this->getFQNFromSchema($schema->additionalProperties) .
                        '>';
                }

                return 'array{' . join(', ', $properties) . '}';
            },
        ];
    }

    public static function getInstance(): TypeMap
    {
        if (self::$instance === null) {
            self::$instance = new TypeMap();
        }
        return self::$instance;
    }

    public function registerReference(string $ref, Type $type): void
    {
        $this->refToType[$ref] = $type;
    }

    public function registerClass(Writable $class): void
    {
        $this->fqnToClass[$class->getType()->as(Type::FQN)] = $class;
    }

    public function getTypeFromReference(string $ref): ?Type
    {
        return $this->refToType[$ref] ?? null;
    }

    /**
     * @param string $fqn
     *
     * @return \Battis\OpenAPI\Generator\Classes\Writable|null
     *
     * @api
     */
    public function getClassFromFQN(string $fqn): ?Writable
    {
        return $this->fqnToClass[$fqn] ?? null;
    }

    /**
     * @param \cebe\openapi\spec\Reference $schema
     *
     * @return string
     */
    public function getFQNFromSchema($schema): string
    {
        if ($schema instanceof Reference) {
            $type = $this->getTypeFromReference($schema->getReference());
            assert(
                $type !== null,
                new SchemaException(
                    'Reference `' . $schema->getReference() . '` not registered'
                )
            );
            return $type->as(Type::FQN);
        }
        assert(
            array_key_exists($schema->type, $this->openAPIToPHP),
            new SchemaException("Unknown schema type `$schema->type`")
        );
        return $this->openAPIToPHP[$schema->type]($schema);
    }
}
