<?php

namespace Battis\OpenAPI\Generator\Map;

use Battis\DataUtilities\Path;
use Battis\DataUtilities\Filesystem;
use Battis\Loggable\Loggable;
use Battis\OpenAPI\Client\Mappable;
use Battis\OpenAPI\Generator\Exceptions\ConfigurationException;
use Battis\OpenAPI\Generator\TypeMap;
use cebe\openapi\spec\OpenApi;

abstract class BaseMap extends Loggable
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
     * @return TypeMap
     *
     * @api
     */
    abstract public function generate(): void;

    /**
     * @return void
     *
     * @api
     */
    public function deletePreviousMapping(): bool
    {
        $success = true;
        if (file_exists($this->basePath)) {
            $this->log("Deleting contents of $this->basePath", Loggable::WARNING);
            foreach(Filesystem::safeScandir($this->basePath) as $item) {
                $filePath = Path::join($this->basePath, $item);
                if(FileSystem::delete($filePath, true)) {
                    $this->log("$filePath deleted");
                } else {
                    $this->log("Error deleting $filePath", Loggable::ERROR);
                    $success = false;
                }
            }
        }
        return $success;
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
