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

class Generator
{
    public static function getDefaultLogger()
    {
        $logger = new Logger('console');
        $handler = new ErrorLogHandler();
        $handler->setFormatter(new CliFormatter());
        $logger->pushHandler($handler);
        return $logger;
    }

    private LoggerInterface $logger;
    private ComponentMapperFactory $componentMapperFactory;
    private EndpointMapperFactory $endpointMapperFactory;

    public function __construct(
        ComponentMapperFactory $componentMapperFactory,
        EndpointMapperFactory $endpointMapperFactory,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->componentMapperFactory = $componentMapperFactory;
        $this->endpointMapperFactory = $endpointMapperFactory;
    }
    public function generate(
        string $path,
        string $basePath,
        string $baseNamespace,
        bool $delete = false
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
                    $delete
                );
            } elseif ($item !== '.' && $item !== '..' && is_dir($specPath)) {
                $this->generate($specPath, $basePath, $baseNamespace, $delete);
            }
        }
    }

    /**
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
     * @api
     */
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
        $components = $this->componentMapperFactory->create($config);
        $endpoints = $this->endpointMapperFactory->create($config);

        // generate the PHP classes in memory
        $components->generate();
        $endpoints->generate();

        // clean up last run if requested
        if ($cleanup) {
            $this->cleanup($basePath);
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
