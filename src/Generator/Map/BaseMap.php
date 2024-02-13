<?php

namespace Battis\OpenAPI\Generator\Map;

use Battis\DataUtilities\Path;
use Battis\Loggable\Loggable;
use Battis\OpenAPI\Client\Mappable;
use Battis\OpenAPI\Exceptions\ConfigurationException;
use Battis\OpenAPI\Generator\Sanitize;
use Battis\OpenAPI\Generator\TypeMap;
use cebe\openapi\spec\OpenApi;
use Psr\Log\LoggerInterface;

abstract class BaseMap extends Loggable
{
    protected OpenApi $spec;
    protected Sanitize $sanitize;
    protected TypeMap $map;

    protected string $baseType;
    protected string $basePath;
    protected string $baseNamespace;

    public function __construct(OpenApi $spec, string $basePath, string $baseNamespace, string $baseType, ?Sanitize $sanitize = null, ?TypeMap $typeMap = null, ?LoggerInterface $logger = null)
    {
        parent::__construct($logger);

        $this->spec = $spec;

        $this->baseType = $baseType;
        assert(is_a($this->baseType, Mappable::class, true), new ConfigurationException("\$baseType must be instance of " . Mappable::class));

        $this->basePath = Path::canonicalize($basePath, getcwd());
        assert(!empty($this->basePath), new ConfigurationException('base path nust be specified'));
        @mkdir($this->basePath, 0744, true);

        assert(!empty($baseNamespace), new ConfigurationException("base namespace must be specified"));
        $this->baseNamespace = trim($baseNamespace, "\\");

        $this->sanitize = $sanitize ?? new Sanitize();

        $this->map = $typeMap ?? new TypeMap($this->logger);

        $this->log([
            'basePath' => $this->basePath,
            'baseNamespace' => $this->baseNamespace,
            'baseType' => $this->baseType,
        ], Loggable::DEBUG);
    }

    abstract public function generate(): TypeMap;

    public function deletePreviousMapping(): void
    {
        $this->log("Deleting contents of $this->basePath", Loggable::WARNING);
        // FIXME this should maybe (not) be recursive?
        foreach (scandir($this->basePath) as $file) {
            if (!is_dir("$this->basePath/$file")) {
                unlink("$this->basePath/$file");
                $this->log("Deleted " . $this->basePath . "/$file", Loggable::WARNING);
            }
        }
    }

    protected function parseFilePath(string $path): string
    {
        return Path::join($this->basePath, "$path.php");
    }

    protected function parseNamespace(string $path = null): string
    {
        return $this->baseNamespace . ($path !== null ? "\\" . preg_replace("/\//", "\\", $path) : "");
    }
}
