<?php

namespace Battis\OpenAPI\Generator\Mappers;

use Battis\DataUtilities\Path;
use Battis\OpenAPI\Client\BaseEndpoint;
use Battis\OpenAPI\Generator\Classes\Endpoint;
use Battis\OpenAPI\Generator\Classes\Router;
use Battis\OpenAPI\Generator\Exceptions\ConfigurationException;
use Battis\OpenAPI\Generator\Sanitize;

/**
 * @api
 */
class EndpointMapper extends BaseMapper
{
    private string $name;

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

    public function rootRouterName(): string
    {
        $sanitize = Sanitize::getInstance();
        return $sanitize->clean($this->getSpec()->info->title);
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
        $config[self::BASE_PATH] = Path::join($config[self::BASE_PATH], $this->simpleNamespace());
        $config[self::BASE_NAMESPACE] = Path::join("\\", [$config[self::BASE_NAMESPACE], $this->simpleNamespace()]);
        parent::__construct($config);
        assert(
            is_a($this->getBaseType(), BaseEndpoint::class, true),
            new ConfigurationException(
                "`" . self::BASE_TYPE . "` must be instance of " . BaseEndpoint::class
            )
        );
    }

    public function generate(): void
    {
        $namespaces = [];
        foreach ($this->getSpec()->paths as $path => $pathItem) {
            $path = (string) $path;
            $url = Path::join($this->getSpec()->servers[0]->url, $path);
            $class = Endpoint::fromPathItem($path, $pathItem, $this, $url);
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
            $class = Router::fromClassList($namespace, $classes, $this);
            if (array_key_exists($class->getType(), $this->classes)) {
                $this->classes[$class->getType()]->mergeWith($class);
            } else {
                $this->classes[$class->getType()] = $class;
            }
        }
    }
}
