<?php

namespace Battis\OpenAPI\CLI;

use Battis\DataUtilities\Filesystem;
use Battis\DataUtilities\Path;
use Battis\OpenAPI\CLI\Logger;
use Battis\OpenAPI\Generator\Mappers\BaseMapper;
use Battis\OpenAPI\Generator\Mappers\ComponentMapper;
use Battis\OpenAPI\Generator\Mappers\EndpointMapper;
use Battis\OpenAPI\Generator\Specification;
use cebe\openapi\spec\OpenApi;
use Monolog\Handler\ErrorLogHandler;
use Monolog;
use pahanini\Monolog\Formatter\CliFormatter;
use Psr\Log\LoggerInterface;
use splitbrain\phpcli\CLI;
use splitbrain\phpcli\Options;

class Map extends CLI
{
    public function __construct(?LoggerInterface $logger = null)
    {
        parent::__construct();
        if ($logger === null) {
            $logger = new Monolog\Logger("console");
            $handler = new ErrorLogHandler();
            $handler->setFormatter(new CliFormatter());
            $logger->pushHandler($handler);
        }
        Logger::init($logger);
    }

    protected function setup(Options $options)
    {
        $options->registerOption("delete-previous", "Delete previous mapping", "d");
        $options->registerArgument(
            "OpenAPI-spec",
            "The path to an OpenAPI YAML or JSON file",
            true
        );
        $options->registerArgument(
            "map-dest",
            "The path where the mapping should be generated",
            true
        );
        $options->registerArgument(
            "namespace",
            "The namespace within which the mapping should be generated",
            true
        );
    }

    protected function main(Options $options)
    {
        /** @var string[] $args */
        $args = $options->getArgs();
        $this->scanPath(
            $args[0],
            $args[1],
            $args[2],
            (bool) $options->getOpt("delete-previous", false)
        );
    }

    protected function scanPath(
        string $path,
        string $basePath,
        string $baseNamespace,
        bool $delete = false
    ): void {
        Logger::log("Scanning $path");
        foreach (scandir($path) as $item) {
            $specPath = Path::join($path, $item);
            if (preg_match("/.*\\.(json|ya?ml)/i", $item)) {
                Logger::log("Parsing $specPath");
                $spec = Specification::from($specPath);
                $this->generateMapping(
                    $spec,
                    $this->getBasePathFromSpec($specPath, $spec, $basePath),
                    $this->getNamespaceFromSpec($specPath, $spec, $baseNamespace),
                    $delete
                );
            } elseif ($item !== "." && $item !== ".." && is_dir($specPath)) {
                $this->scanPath($specPath, $basePath, $baseNamespace, $delete);
            }
        }
    }

    protected function getBasePathFromSpec(
        string $specPath,
        OpenApi $spec,
        string $basePath
    ): string {
        return $basePath;
    }

    protected function getNamespaceFromSpec(
        string $specPath,
        OpenApi $spec,
        string $baseNamespace
    ): string {
        return $baseNamespace;
    }

    public function cleanup(string $path): void
    {
        if (file_exists($path)) {
            Logger::log("Deleting contents of $path", Logger::WARNING);
            if (file_exists($path)) {
                foreach (Filesystem::safeScandir($path) as $item) {
                    $filePath = Path::join($path, $item);
                    if (FileSystem::delete($filePath, true)) {
                        Logger::log("$filePath deleted", Logger::WARNING);
                    } else {
                        Logger::log("Error deleting $filePath", Logger::ERROR);
                    }
                }
            }
        }
    }

    protected function generateMapping(
        OpenApi $spec,
        string $basePath,
        string $baseNamespace,
        bool $cleanup = false
    ): void {
        $config = [
          BaseMapper::SPEC => $spec,
          BaseMapper::BASE_PATH => $basePath,
          BaseMapper::BASE_NAMESPACE => $baseNamespace,
        ];
        $components = new ComponentMapper($config);
        $endpoints = new EndpointMapper($config);

        // generate the PHP classes in memory
        $components->generate();
        $endpoints->generate();

        // clean up last run if requested
        if ($cleanup) {
            $this->cleanup($basePath);
        }

        // write the PHP classes from memory to disk -- will not overwrite existing files
        $components->writeFiles();
        $endpoints->writeFiles();

        // tidy up the PHP to make it all pretty
        shell_exec(
            Path::join(getcwd(), "/vendor/bin/php-cs-fixer") . " fix " . $basePath
        );
    }
}
