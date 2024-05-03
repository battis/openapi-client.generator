<?php

namespace Battis\OpenAPI;

use Battis\DataUtilities\Filesystem;
use Battis\DataUtilities\Path;
use Battis\OpenAPI\Generator\Mappers\ComponentMapperFactory;
use Battis\OpenAPI\Generator\Mappers\BaseMapper;
use Battis\OpenAPI\Generator\Mappers\EndpointMapperFactory;
use Battis\OpenAPI\Generator\Specification;
use cebe\openapi\spec\OpenApi;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use pahanini\Monolog\Formatter\CliFormatter;
use Psr\Log\LoggerInterface;

/**
 * Generate PHP client(s) from OpenAPI specification(s)
 *
 * @api
 */
class Generator
{
    private LoggerInterface $logger;
    private ComponentMapperFactory $componentMapperFactory;
    private EndpointMapperFactory $endpointMapperFactory;

    /**
     * Meant for autowired dependency-injection
     *
     * @see https://battis.github.io/openapi-client-generator/latest/guide/basic.html for example
     *
     * @param ComponentMapperFactory $componentMapperFactory
     * @param EndpointMapperFactory $endpointMapperFactory
     * @param LoggerInterface $logger
     *
     * @api
     */
    public function __construct(
        ComponentMapperFactory $componentMapperFactory,
        EndpointMapperFactory $endpointMapperFactory,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->componentMapperFactory = $componentMapperFactory;
        $this->endpointMapperFactory = $endpointMapperFactory;
    }

    /**
     * Pre-configured console logger
     *
     * @return LoggerInterface A color-coded console logger
     *
     * @api
     */
    public static function getDefaultLogger(): LoggerInterface
    {
        $logger = new Logger('console');
        $handler = new ErrorLogHandler();
        $handler->setFormatter(new CliFormatter());
        $logger->pushHandler($handler);
        return $logger;
    }

    /**
     * Generate PHP client(s) from OpenAPI specification(s)
     *
     * @param string $path Path to an individual OpenAPI specification file or
     *   a directory containing OpenAPI specifications. Directories will be
     *   recursiely spidered to find all specification files.
     * @param string $basePath Path to the directory in which to create the
     *   PHP client files
     * @param string $baseNamespace Namespace within which to generate the PHP
     *   client objects
     * @param bool $purge (Optional, default `false`) Purge the `$basePath`
     *   directory of all contents before writing the generated files (if not
     *   purged, any conflicting files will be over-written)
     *
     * @return void
     *
     * @api
     */
    public function generate(
        string $path,
        string $basePath,
        string $baseNamespace,
        bool $purge = false
    ): void {
        $this->logger->info("Scanning $path");
        if (is_file($path)) {
            $items = [basename($path)];
            $path = dirname($path);
        } else {
            $items = scandir($path);
        }
        foreach ($items as $item) {
            $specPath = Path::join($path, $item);
            if (preg_match('/.*\\.(json|ya?ml)/i', $item)) {
                $this->logger->info("Parsing $specPath");
                $spec = Specification::from($specPath);
                $this->generateMapping(
                    $spec,
                    $this->getBasePathFromSpec($specPath, $spec, $basePath),
                    $this->getNamespaceFromSpec(
                        $specPath,
                        $spec,
                        $baseNamespace
                    ),
                    $purge
                );
            } elseif ($item !== '.' && $item !== '..' && is_dir($specPath)) {
                $this->generate($specPath, $basePath, $baseNamespace, $purge);
            }
        }
    }

    /**
     * Define base path to directory within which to generate the PHP client
     * files.
     *
     * Override to define a base path for client file creation based on a
     * combination of the specification itself, the path to the specification
     * and the originally passed `$basePath` argument.
     *
     * For example: suppose
     * you are generating clients on a nested directory of OpenAPI
     * specifications and want the clients to mirror the directory structure
     * of the nested specifications.
     *
     * **Defaults to the `$basePath` argument passed to `generate()`**
     *
     * @see \Battis\OpenAPI\Generator::generate()
     * @see https://battis.github.io/openapi-client-generator/latest/guide/extending.html Example
     *
     * @param string $specPath Path to an individual OpenAPI specification file
     * @param OpenApi $spec OpenAPI specification
     * @param string $basePath Path to the directory in which to create the
     *   PHP client files (`$basePath` argument passed to `generate()`)
     *
     * @return string
     *
     * @api
     */
    protected function getBasePathFromSpec(
        string $specPath,
        OpenApi $spec,
        string $basePath
    ): string {
        return $basePath;
    }

    /**
     * Define base namespace to directory within which to generate the PHP client
     * objects.
     *
     * **Defaults to the `$baseNamespace` argument passed to `generate()`**
     *
     * @see \Battis\OpenAPI\Generator::getBasePathFromSpec() for proposed usage
     * @see \Battis\OpenAPI\Generator::generate()
     * @see https://battis.github.io/openapi-client-generator/latest/guide/extending.html Example
     *
     * @param string $specPath Path to an individual OpenAPI specification file
     * @param OpenApi $spec OpenAPI specification
     * @param string $baseNamespace Namespace within which to generate the PHP
     *   client objects (`$baseNamespace` argument passed to `generate()`)
     *
     * @return string
     *
     * @api
     */
    protected function getNamespaceFromSpec(
        string $specPath,
        OpenApi $spec,
        string $baseNamespace
    ): string {
        return $baseNamespace;
    }

    /**
     * Purge directory contents
     *
     * @param string $path Path to directory to purge
     *
     * @return void
     */
    private function purge(string $path): void
    {
        if (file_exists($path)) {
            $this->logger->warning("Deleting contents of $path");
            if (file_exists($path)) {
                foreach (Filesystem::safeScandir($path) as $item) {
                    $filePath = Path::join($path, $item);
                    if (FileSystem::delete($filePath, true)) {
                        $this->logger->warning("$filePath deleted");
                    } else {
                        $this->logger->error("Error deleting $filePath");
                    }
                }
            }
        }
    }

    /**
     * Generate a mapping from an OpenAPI spec to PHP client
     *
     * @param OpenApi $spec OpenAPI specification
     * @param string $basePath Path to the directory in which to create the
     *   PHP client files
     * @param string $baseNamespace Namespace within which to generate the PHP
     *   client objects
     * @param bool $purge (Optional, default `false`) Purge the `$basePath`
     *   directory of all contents before writing the generated files (if not
     *   purged, any conflicting files will be over-written)
     *
     * @return void
     */
    private function generateMapping(
        OpenApi $spec,
        string $basePath,
        string $baseNamespace,
        bool $purge = false
    ): void {
        $config = [
            BaseMapper::SPEC => $spec,
            BaseMapper::BASE_PATH => $basePath,
            BaseMapper::BASE_NAMESPACE => $baseNamespace,
        ];
        $components = $this->componentMapperFactory->create($config);
        $endpoints = $this->endpointMapperFactory->create($config);

        // generate the PHP classes in memory
        $components->generate();
        $endpoints->generate();

        // clean up last run if requested
        if ($purge) {
            $this->purge($basePath);
        }

        /*
         * write the PHP classes from memory to disk -- will not overwrite
         * existing files
         */
        $components->writeFiles();
        $endpoints->writeFiles();

        /**
         * tidy up the PHP to make it all pretty
         */
        shell_exec(
            Path::join(getcwd(), '/vendor/bin/php-cs-fixer') .
                ' fix ' .
                $basePath
        );
    }
}
