<?php

namespace Battis\OpenAPI\Generator\Mappers;

use Battis\DataUtilities\Path;
use Battis\Loggable\Loggable;
use Battis\OpenAPI\Client\Mappable;
use Battis\OpenAPI\Generator\Exceptions\ConfigurationException;
use Battis\OpenAPI\Generator\Exceptions\GeneratorException;
use cebe\openapi\spec\OpenApi;

abstract class BaseMapper extends Loggable
{
    public const SPEC = 'spec';
    public const BASE_PATH = 'basePath';
    public const BASE_NAMESPACE = 'baseNamespace';
    public const BASE_TYPE = 'baseType';

    public OpenApi $spec;
    public string $baseType;
    public string $basePath;
    public string $baseNamespace;

    /**
     * @api
     */
    public abstract function simpleNamespace(): string;

    /**
     * @var array<string, \Battis\OpenAPI\Generator\Classes\Writable> $classes `['type' => Writable]`
     */
    protected array $classes = [];

    /**
     * @param array{
     *     spec: \cebe\openapi\spec\OpenApi,
     *     basePath: string,
     *     baseNamespace: string,
     *     baseType: string,
     *   } $config
     */
    public function __construct(array $config)
    {
        parent::__construct();

        $this->spec = $config[self::SPEC];

        $this->baseType = $config[self::BASE_TYPE];
        assert(
            is_a($this->baseType, Mappable::class, true),
            new ConfigurationException("`" . self::BASE_TYPE . "` must be instance of " . Mappable::class)
        );

        $this->basePath = Path::canonicalize($config[self::BASE_PATH], getcwd());
        @mkdir($this->basePath, 0744, true);

        assert(
            !empty($config[self::BASE_NAMESPACE]),
            new ConfigurationException("`" . self::BASE_NAMESPACE . "` must be defined")
        );
        $this->baseNamespace = trim($config[self::BASE_NAMESPACE], "\\");
    }

    /**
     * @api
     */
    abstract public function generate(): void;

    public function parseFilePath(string $path): string
    {
        return Path::join($this->basePath, "$path.php");
    }

    public function parseType(string $path = null): string
    {
        $parts = [$this->baseNamespace];
        if ($path !== null) {
            $parts[ ] = str_replace("/", "\\", $path);
        }
        return Path::join("\\", $parts);
    }

    public function writeFiles(): void
    {
        foreach($this->classes as $class) {
            $filePath = Path::join($this->basePath, $class->getPath(), $class->getName() . ".php");
            @mkdir(dirname($filePath), 0744, true);
            assert(!file_exists($filePath), new GeneratorException("$filePath exists and cannot be overwritten"));
            file_put_contents($filePath, $class);
            $this->log("Wrote " . $class->getType() . " to $filePath");
        }
    }
}
