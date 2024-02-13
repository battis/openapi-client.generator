<?php

namespace Battis\OpenAPI\Generator;

use Battis\Loggable\Loggable;
use Battis\OpenAPI\Generator\Exceptions\SchemaException;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;
use Psr\Log\LoggerInterface;

class TypeMap extends Loggable
{
    /** @var array<string, string> */
    private array $schemaToType = [];

    /** @var array<string, string> */
    private array $urlToPath = [];

    /** @var array<string, string> */
    private array $pathToType = [];

    /** @var array<string, string> */
    private array $urlToType = [];

    public function __construct(LoggerInterface $logger = null)
    {
        parent::__construct($logger);
    }

    /**
     * @param array{ref?: string, type?: string, path?: string, url?: string} $registration
     *
     * @return void
     &
     * @psalm-suppress RiskyTruthyFalsyComparison
     */
    public function register(array $registration): void
    {
        if (!empty($registration['ref']) && !empty($registration['type'])) {
            $this->registerSchema($registration['ref'], $registration['type']);
        }
        if (!empty($registration['url']) && !empty($registration['path'])) {
            $this->registerUrl($registration['url'], $registration['path']);
        }
        if (!empty($registration['type']) && !empty($registration['path'])) {
            $this->registerType($registration['type'], $registration['path']);
        }
    }

    public function registerSchema(string $ref, string $type): void
    {
        $this->schemaToType[$ref] = $type;

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
        $type = $this->schemaToType[$ref] ?? null;
        if ($type !== null) {
            $type = $this->parseType($type, $fqn, $absolute);
        }
        return $type;
    }

    public function registerUrl(string $url, string $filePath): void
    {
        $this->urlToPath[$url] = $filePath;
        $this->log([
          "url" => $url,
          "path" => $filePath,
        ], Loggable::DEBUG, false);

    }

    public function getFilepathFromUrl(string $url): ?string
    {
        return $this->urlToPath[$url] ?? null;
    }

    public function registerType(string $type, string $filePath): void
    {
        $this->pathToType[$type] = $filePath;
        $this->log([
          "type" => $type,
          "path" => $filePath,
        ], Loggable::DEBUG, false);

    }

    public function getFilePathFromType(string $type): ?string
    {
        return $this->pathToType[$type] ?? null;
    }

    public function registerUrlGet(string $url, string $type): void
    {
        $this->urlToType[$url] = $type;
        $this->log([
          "url" => $url,
          "type" => $type,
        ], Loggable::DEBUG, false);

    }

    public function getTypeFromUrl(string $url, bool $fqn = true, bool $absolute = false): ?string
    {
        $type = $this->urlToType[$url] ?? null;
        if ($type !== null) {
            $type = $this->parseType($type, $fqn, $absolute);
        }
        return $type;
    }

    public function getFilePathFromUrlGet(string $url): ?string
    {
        $type = $this->getTypeFromUrl($url);
        if ($type !== null) {
            return $this->getFilePathFromType($type);
        }
        return $type;
    }

    private function parseType(string $type, bool $fqn = true, bool $absolute = false): string
    {
        if ($fqn) {
            if ($absolute) {
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
        return empty($elt->format) ? "scalar" : $elt->format;
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
            return (string) $this->getTypeFromSchema($elt->items->getReference(), $absolute) .
              "[]";
        }
        $method = $elt->items->type;
        return (string) $this->$method($elt->items) . "[]";
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
        $this->log([
          "ref" => $ref,
          "arguments" => $arguments,
        ], Loggable::DEBUG);

        $class = $this->getTypeFromSchema($ref, true, $absolute);
        assert($class !== null, new SchemaException("$ref not defined"));
        return $class;
    }
}
