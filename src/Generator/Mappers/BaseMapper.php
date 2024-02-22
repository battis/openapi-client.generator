<?php

namespace Battis\OpenAPI\Generator\Mappers;

use Battis\DataUtilities\Path;
use Battis\OpenAPI\CLI\Logger;
use Battis\OpenAPI\Client\Mappable;
use Battis\OpenAPI\Generator\Classes\NamespaceCollection;
use Battis\OpenAPI\Generator\Exceptions\ConfigurationException;
use Battis\OpenAPI\Generator\Exceptions\GeneratorException;
use cebe\openapi\spec\OpenApi;

abstract class BaseMapper
{
    public const SPEC = "spec";
    public const BASE_PATH = "basePath";
    public const BASE_NAMESPACE = "baseNamespace";
    public const BASE_TYPE = "baseType";

    private OpenApi $spec;

    /**
     * @var class-string<\Battis\OpenAPI\Client\Mappable> $baseType
     */
    private string $baseType;
    private string $basePath;
    private string $baseNamespace;

    /**
     * @api
     */
    abstract public function simpleNamespace(): string;

    public function getSpec(): OpenApi
    {
        return $this->spec;
    }

    /**
     * @return class-string<\Battis\OpenAPI\Client\Mappable>
     * @api
     */
    public function getBaseType(): string
    {
        return $this->baseType;
    }

    /**
     * @api
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * @api
     */
    public function getBaseNamespace(): string
    {
        return $this->baseNamespace;
    }

    protected NamespaceCollection $classes;

    /**
     * @param array{
     *     spec: \cebe\openapi\spec\OpenApi,
     *     basePath: string,
     *     baseNamespace: string,
     *     baseType: class-string<\Battis\OpenAPI\Client\Mappable>,
     *   } $config
     */
    public function __construct(array $config)
    {
        $this->spec = $config[self::SPEC];

        $this->baseType = $config[self::BASE_TYPE];
        assert(
            is_a($this->baseType, Mappable::class, true),
            new ConfigurationException(
                "`" . self::BASE_TYPE . "` must be instance of " . Mappable::class
            )
        );

        $this->basePath = Path::canonicalize($config[self::BASE_PATH], getcwd());
        @mkdir($this->basePath, 0744, true);

        assert(
            !empty($config[self::BASE_NAMESPACE]),
            new ConfigurationException(
                "`" . self::BASE_NAMESPACE . "` must be defined"
            )
        );
        $this->baseNamespace = trim($config[self::BASE_NAMESPACE], "\\");

        $this->classes = new NamespaceCollection($this->baseNamespace);
    }

    /**
     * @api
     */
    abstract public function generate(): void;

    public function writeFiles(): void
    {
        foreach ($this->classes->getClasses(true) as $class) {
            $filePath = Path::join($this->basePath, $class->getPath() . ".php");
            @mkdir(dirname($filePath), 0744, true);
            assert(
                !file_exists($filePath),
                new GeneratorException("$filePath exists and cannot be overwritten")
            );
            file_put_contents($filePath, (string) $class);
            Logger::log("Wrote " . $class->getType() . " to $filePath");
        }
    }
}
