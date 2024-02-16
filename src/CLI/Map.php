<?php

namespace Battis\OpenAPI\CLI;

use Battis\DataUtilities\Path;
use Battis\OpenAPI\Generator\Map\EndpointMap;
use Battis\OpenAPI\Generator\Map\ObjectMap;
use Battis\OpenAPI\Generator\Specification;
use cebe\openapi\spec\OpenApi;
use Psr\Log\LoggerInterface;
use splitbrain\phpcli\CLI;
use splitbrain\phpcli\Options;

class Map extends CLI
{
    private ?LoggerInterface $logger;

    public function __construct(LoggerInterface $logger = null)
    {
        parent::__construct();
        $this->logger = $logger;
    }

    protected function setup(Options $options)
    {
        $options->registerOption('delete-previous', 'Delete previous mapping', 'd');
        $options->registerArgument('OpenAPI-spec', 'The path to an OpenAPI YAML or JSON file', true);
        $options->registerArgument('map-dest', 'The path where the mapping should be generated', true);
        $options->registerArgument('namespace', 'The namespace within which the mapping should be generated', true);
    }

    protected function main(Options $options)
    {
        /** @var string[] $args */
        $args = $options->getArgs();
        $this->scanPath($args[0], $args[1], $args[2], (bool) $options->getOpt('delete-previous', false));
    }

    protected function scanPath(string $path, string $basePath, string $baseNamespace, bool $delete = false): void
    {
        if ($this->logger) {
            $this->logger->info("Scanning $path");
        }
        foreach(scandir($path) as $item) {
            $specPath = Path::join($path, $item);
            if (preg_match("/.*\\.(json|ya?ml)/i", $item)) {
                if ($this->logger) {
                    $this->logger->info("Parsing $specPath");
                }
                $spec = Specification::from($specPath);
                $this->generateMapping(
                    $spec,
                    $this->getBasePathFromSpec($specPath, $spec, $basePath),
                    $this->getNamespaceFromSpec($specPath, $spec, $baseNamespace),
                    $delete
                );
            } elseif ($item !== '.' && $item !== '..' && is_dir($specPath)) {
                $this->scanPath($specPath, $basePath, $baseNamespace, $delete);
            }
        }
    }

    protected function getBasePathFromSpec(string $specPath, OpenApi $spec, string $basePath): string
    {
        return $basePath;
    }

    protected function getNamespaceFromSpec(string $specPath, OpenApi $spec, string $baseNamespace): string
    {
        return $baseNamespace;
    }

    protected function generateMapping(OpenApi $spec, string $basePath, string $baseNamespace, bool $delete = false): void
    {
        $objectMap = new ObjectMap([
            'spec' => $spec,
            'basePath' => Path::join($basePath, 'Objects'),
            'baseNamespace' => Path::join("\\", [$baseNamespace, 'Objects']),
            'logger' => $this->logger,
        ]);
        if ($delete) {
            $objectMap->deletePreviousMapping();
        }
        $map = $objectMap->generate();
        $objectMap->writeFiles();
        $endpointMap = new EndpointMap([
            'spec' => $spec,
            'basePath' => Path::join($basePath, 'Endpoints'),
            'baseNamespace' => Path::join("\\", [$baseNamespace, 'Endpoints']),
            'typeMap' => $map,
            'logger' => $this->logger,
        ]);
        if ($delete) {
            $endpointMap->deletePreviousMapping();
        }
        $endpointMap->generate();
        $endpointMap->writeFiles();
    }
}
