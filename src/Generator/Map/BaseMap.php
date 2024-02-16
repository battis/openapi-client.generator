<?php

namespace Battis\OpenAPI\Generator\Map;

use Battis\DataUtilities\Path;
use Battis\DataUtilities\Filesystem;
use Battis\Loggable\Loggable;
use Battis\OpenAPI\Client\Mappable;
use Battis\OpenAPI\Generator\Exceptions\ConfigurationException;
use Battis\OpenAPI\Generator\Sanitize;
use Battis\OpenAPI\Generator\TypeMap;
use cebe\openapi\spec\OpenApi;

abstract class BaseMap extends Loggable
{
    public OpenApi $spec;
    public Sanitize $sanitize;
    public TypeMap $map;

    public string $baseType;
    public string $basePath;
    public string $baseNamespace;

    /**
     * @param array{
     *     spec: \cebe\openapi\spec\OpenApi,
     *     basePath: string,
     *     baseNamespace: string,
     *     baseType: string,
     *     sanitize?: \Battis\OpenAPI\Generator\Sanitize,
     *     typeMap?: \Battis\OpenAPI\Generator\TypeMap,
     *     logger?: ?\Psr\Log\LoggerInterface
     *   } $config
     */
    public function __construct(array $config)
    {
        parent::__construct($config['logger'] ?? null);

        $this->spec = $config['spec'];

        $this->baseType = $config['baseType'];
        assert(is_a($this->baseType, Mappable::class, true), new ConfigurationException("\$baseType must be instance of " . Mappable::class));

        $this->basePath = Path::canonicalize($config['basePath'], getcwd());
        @mkdir($this->basePath, 0744, true);

        assert(!empty($config['baseNamespace']), new ConfigurationException("base namespace must be specified"));
        $this->baseNamespace = trim($config['baseNamespace'], "\\");

        $this->sanitize = $config['sanitize'] ?? new Sanitize();

        $this->map = $config['typeMap'] ?? new TypeMap($this->logger);
    }

    /**
     * @return TypeMap
     *
     * @api
     */
    abstract public function generate(): TypeMap;

    /**
     * @return void
     *
     * @api
     */
    public function deletePreviousMapping(): void
    {
        $this->log("Deleting contents of $this->basePath", Loggable::WARNING);
        foreach(Filesystem::safeScandir($this->basePath) as $item) {
            FileSystem::delete(Path::join($this->basePath, $item), true);
        }
    }

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
}
