<?php

namespace Battis\OpenAPI\Generator\Map;

use Battis\DataUtilities\Path;
use Battis\OpenAPI\Client\BaseEndpoint;
use Battis\OpenAPI\Generator\Exceptions\ConfigurationException;
use Battis\OpenAPI\Generator\Exceptions\GeneratorException;

/**
 * @api
 */
class EndpointMap extends BaseMap
{
    /**
     * @var array<string, EndpointClass> $objects
     */
    protected array $classes = [];

    /**
     * @return string[]
     */
    public function supportedOperations(): array
    {
        return ["get", "put", "post", "delete", "options", "head", "patch", "trace"];
    }

    public function expectedContentType(): string
    {
        return "application/json";
    }

    public function simpleNamespace(): string
    {
        return "Endpoints";
    }

    /**
     * @param array{
     *     spec: \cebe\openapi\spec\OpenApi,
     *     basePath: string,
     *     baseNamespace: string,
     *     baseType?: string,
     *   } $config
     */
    public function __construct(array $config)
    {
        $config[self::BASE_TYPE] ??= BaseEndpoint::class;
        parent::__construct($config);
        assert(
            is_a($this->baseType, BaseEndpoint::class, true),
            new ConfigurationException(
                "`" . self::BASE_TYPE . "` must be instance of " . BaseEndpoint::class
            )
        );
        $this->basePath = Path::join($this->basePath, $this->simpleNamespace());
        $this->baseNamespace = Path::join("\\", [$this->baseNamespace, $this->simpleNamespace()]);
    }

    public function generate(): void
    {
        $namespaces = [];
        foreach ($this->spec->paths as $path => $pathItem) {
            $path = (string) $path;
            $url = Path::join($this->spec->servers[0]->url, $path);
            $class = EndpointClass::fromPathItem($path, $pathItem, $this, $url);
            if (array_key_exists($class->getType(), $this->classes)) {
                $this->classes[$class->getType()]->mergeWith($class);
                $this->log("Merged into " . $class->getType());
            } else {
                $this->classes[$class->getType()] = $class;
                $namespaces[$class->getNamespace()][] = $class;
                $this->log("Generated " . $class->getType());
            }
        }

        foreach($namespaces as $namespace => $classes) {
            $class = RouterClass::fromClassList($namespace, $classes, $this);
            if (array_key_exists($class->getType(), $this->classes)) {
                $this->classes[$class->getType()]->mergeWith($class);
            } else {
                $this->classes[$class->getType()] = $class;
            }
        }
    }

    public function writeFiles(): void
    {
        foreach($this->classes as $class) {
            $dir = dirname($class->getNormalizedPath());
            if ($dir === '.') {
                $dir = '..';
            }
            $filePath = Path::join($this->basePath, $dir , $class->getName() . ".php");
            @mkdir(dirname($filePath), 0744, true);
            assert(!file_exists($filePath), new GeneratorException("$filePath exists and cannot be overwritten"));
            file_put_contents($filePath, $class);
            $this->log("Wrote " . $class->getType() . " to $filePath");
        }
        shell_exec(Path::join(getcwd(), '/vendor/bin/php-cs-fixer') . " fix " . $this->basePath);
    }
}
