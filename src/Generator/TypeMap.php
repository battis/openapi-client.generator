<?php

namespace Battis\OpenAPI\Generator;

use Battis\Loggable\Loggable;
use Battis\OpenAPI\Exceptions\SchemaException;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;
use Psr\Log\LoggerInterface;

class TypeMap extends Loggable
{
    /** @var array<string, string> $chemas */
    private array $schemas = [];

    /** @var array<string, string> $urls */
    private array $urls = [];

    /** @var array<string, string> $paths */
    private array $paths = [];

    public function __construct(LoggerInterface $logger = null)
    {
        parent::__construct($logger);
    }

    /**
     * @param array{ref?: string, type?: string, path?: string, url?: string} $registration
     *
     * @return void
     */
    public function register(array $registration): void
    {
        if (!empty($registration['ref']) && !empty($registration('type'))) {
            $this->registerSchema($registration['ref'], $registration['type']);
        }
        if (!empty($registration['url']) && !empty($registration['path'])) {
            $this->registerUrl($registration['url'], $registration['path']);
        }
        if (!empty($registration['type']) && !empty($registration['path'])) {
            $this->registerClass($registration['type'], $registration['path']);
        }
    }

    public function registerSchema(string $ref, string $type): void
    {
        $this->schemas[$ref] = $type;

        $this->log([
          "ref" => $ref,
          "type" => $type,
        ], Loggable::DEBUG, false);
    }

    public function getTypeFromSchema(
        string $ref,
        bool $fqn = true,
        bool $absolute = false
    ): ?string {
        if (array_key_exists($ref, $this->schemas)) {
            $obj = $this->schemas[$ref];
            if ($fqn) {
                if ($absolute) {
                    $obj = "\\" . $obj;
                }
            } else {
                $obj = preg_replace("/^.+\\\\([^\\\\]+)$/", "$1", $obj, -1, $count);
            }
            return $obj;
        }
        return null;
    }

    public function registerUrl(string $url, string $filePath): void
    {
        $this->urls[$url] = $filePath;
        $this->log([
          "url" => $url,
          "path" => $filePath,
        ], Loggable::DEBUG, false);

    }

    public function getFilepathFromUrl(string $url): ?string
    {
        return $this->urls[$url] ?? null;
    }

    public function registerClass(string $type, string $filePath): void
    {
        $this->paths[$type] = $filePath;
        $this->log([
          "type" => $type,
          "path" => $filePath,
        ], Loggable::DEBUG, false);

    }

    public function getFilePathFromType(string $type): ?string
    {
        return $this->paths[$type] ?? null;
    }

    public function boolean(): string
    {
        return "bool";
    }

    public function integer(): string
    {
        return "int";
    }

    public function number(Schema $elt): string
    {
        return empty($elt->format) ? "scalar" /* FIXME WAG */ : $elt->format;
    }

    public function string(): string
    {
        return "string";
    }

    public function array(Schema $elt, bool $absolute = false): string
    {
        assert(
            $elt->items !== null,
            new SchemaException("array spec $elt->title missing items spec")
        );
        if ($elt->items instanceof Reference) {
            return (string) $this->getTypeFromSchema($elt->items->getReference(), $absolute) .
              "[]";
        }
        $method = $elt->items->type;
        return (string) $this->$method($elt->items) . "[]";
    }

    public function object(Schema $elt): string
    {
        assert(
            $elt->additionalProperties instanceof Schema,
            new SchemaException(var_export($elt->getSerializableData(), true))
        );
        return $elt->additionalProperties->type . "[]";
    }

    public function __call(string $ref, array $arguments)
    {
        $absolute = $arguments[1] ?? false;
        $this->log([
          "ref" => $ref,
          "arguments" => $arguments,
        ], Loggable::DEBUG);

        $class = $this->getTypeFromSchema($ref, true, $absolute);
        assert($class, new SchemaException("$ref not defined"));
        return $class;
    }
}
