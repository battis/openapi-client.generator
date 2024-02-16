<?php

namespace Battis\OpenAPI\Generator\Map;

use Battis\DataUtilities\Path;
use Battis\OpenAPI\Client\BaseEndpoint;
use Battis\OpenAPI\Generator\Exceptions\ConfigurationException;
use Battis\OpenAPI\Generator\TypeMap;

/**
 * @api
 */
class EndpointMap extends BaseMap
{
    /**
     * @var EndpointClass[] $objects
     */
    protected array $objects = [];

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

    /**
     * @param array{
     *     spec: \cebe\openapi\spec\OpenApi,
     *     basePath: string,
     *     baseNamespace: string,
     *     baseType?: string,
     *     sanitize?: \Battis\OpenAPI\Generator\Sanitize,
     *     typeMap?: \Battis\OpenAPI\Generator\TypeMap,
     *     logger?: ?\Psr\Log\LoggerInterface
     *   } $config
     */
    public function __construct(array $config)
    {
        $config["baseType"] ??= BaseEndpoint::class;
        parent::__construct($config);
        assert(
            is_a($this->baseType, BaseEndpoint::class, true),
            new ConfigurationException(
                "\$baseType must be instance of " . BaseEndpoint::class
            )
        );
    }

    public function generate(): TypeMap
    {
        foreach ($this->spec->paths as $path => $pathItem) {
            $path = (string) $path;
            $this->log($path);
            $url = Path::join($this->spec->servers[0]->url, $path);
            $this->objects[] = EndpointClass::fromPathItem($path, $pathItem, $this, $url);
        }

        return $this->map;
    }

    public function writeFiles(): void
    {
        foreach($this->objects as $object) {
            $filePath = Path::join($this->basePath, $object->normalizedPath . ".php");
            @mkdir(dirname($filePath), 0744, true);
            file_put_contents($filePath, $object);
            $this->log($filePath);
        }
        shell_exec(Path::join(getcwd(), '/vendor/bin/php-cs-fixer') . " fix " . $this->basePath);
    }
}
