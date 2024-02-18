<?php

namespace Battis\OpenAPI\Generator;

use Battis\Loggable\Loggable;
use Battis\OpenAPI\Generator\CodeComponent\PHPClass;
use Battis\OpenAPI\Generator\Exceptions\SchemaException;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;
use Psr\Log\LoggerInterface;

class TypeMap extends Loggable
{
    /**
     * @var array<string, string>
     */
    private array $schemaToType = [];

    /**
     * @var array<string, PHPClass>
     */
    private array $typeToClass = [];

    public function __construct(LoggerInterface $logger = null)
    {
        parent::__construct($logger);
    }

    public function registerSchema(string $ref, string $type): void
    {
        $this->schemaToType[$ref] = $type;
    }

    public function registerClass(PHPClass $class): void
    {
        $this->typeToClass[$class->getType()] = $class;
    }

    public function getTypeFromSchema(
        string $ref,
        bool $fqn = true,
        bool $absolute = false
    ): ?string {
        $type = $this->schemaToType[$ref] ?? null;
        if ($type !== null) {
            $type = self::parseType($type, $fqn, $absolute);
        }
        return $type;
    }

    public function getClassFromType(string $type): ?PHPClass
    {
        return $this->typeToClass[$type] ?? null;
    }

    public static function parseType(string $type, bool $fqn = true, bool $absolute = false): string
    {
        if ($fqn) {
            if ($absolute && !in_array($type, ['void', 'null','bool','int','float','string','array','object','callable','resource'])) {
                $type = "\\" . $type;
            }
        } else {
            $type = preg_replace("/^.+\\\\([^\\\\]+)$/", "$1", $type);
        }
        return $type;
    }

    /**
     * @return string
     *
     * @api
     */
    public function boolean(): string
    {
        return "bool";
    }

    /**
     * @return string
     *
     * @api
     */
    public function integer(): string
    {
        return "int";
    }

    /**
     * @param Schema $elt
     *
     * @return string
     *
     * @api
     */
    public function number(Schema $elt): string
    {
        return empty($elt->format) ? "scalar" /* FIXME WAG */ : $elt->format;
    }

    /**
     * @return string
     *
     * @api
     */
    public function string(): string
    {
        return "string";
    }

    /**
     * @param Schema $elt
     * @param bool $absolute
     *
     * @return string
     *
     * @api
     */
    public function array(Schema $elt, bool $absolute = false): string
    {
        assert(
            $elt->items !== null,
            new SchemaException("array spec $elt->title missing items spec")
        );
        if ($elt->items instanceof Reference) {
            return (string) $this->getTypeFromSchema($elt->items->getReference(), true, $absolute) .
              "[]";
        }
        $method = $elt->items->type;
        return (string) $this->$method($elt->items, $absolute) . "[]";
    }

    /**
     * @param Schema $elt
     *
     * @return string
     *
     * @api
     */
    public function object(Schema $elt): string
    {
        assert(
            $elt->additionalProperties instanceof Schema,
            new SchemaException(var_export($elt->getSerializableData(), true))
        );
        return $elt->additionalProperties->type . "[]";
    }

    /**
     * @param string $ref
     * @param array $arguments
     *
     * @return string
     *
     * @api
     */
    public function __call(string $ref, array $arguments)
    {
        /** @var bool $absolute */
        $absolute = $arguments[1] ?? false;

        $class = $this->getTypeFromSchema($ref, true, $absolute);
        assert($class !== null, new SchemaException("$ref not defined"));
        return $class;
    }
}
