<?php

namespace Battis\OpenAPI\CLI;

use Battis\DataUtilities\Filesystem;
use Battis\DataUtilities\Path;
use Battis\Loggable\Loggable;
use Battis\OpenAPI\Generator\Mappers\ComponentMapper;
use Battis\OpenAPI\Generator\Mappers\EndpointMapper;
use Battis\OpenAPI\Generator\Specification;
use cebe\openapi\spec\OpenApi;
use Psr\Log\LoggerInterface;
use splitbrain\phpcli\CLI;
use splitbrain\phpcli\Options;

class Map extends CLI
{
    public function __construct(?LoggerInterface $logger = null)
    {
        parent::__construct();
        Loggable::init($logger);
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
        Loggable::staticLog("Scanning $path");
        foreach(scandir($path) as $item) {
            $specPath = Path::join($path, $item);
            if (preg_match("/.*\\.(json|ya?ml)/i", $item)) {
                Loggable::staticLog("Parsing $specPath");
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

    public function cleanup(string $path): void
    {
        if (file_exists($path)) {
            Loggable::staticLog("Deleting contents of $path", Loggable::WARNING);
            foreach(Filesystem::safeScandir($path) as $item) {
                $filePath = Path::join($path, $item);
                if(FileSystem::delete($filePath, true)) {
                    Loggable::staticLog("$filePath deleted", Loggable::WARNING);
                } else {
                    Loggable::staticLog("Error deleting $filePath", Loggable::ERROR);
                }
            }
        }
    }

    protected function generateMapping(OpenApi $spec, string $basePath, string $baseNamespace, bool $cleanup = false): void
    {
        $config = [
            'spec' => $spec,
            'basePath' => $basePath,
            'baseNamespace' => $baseNamespace,
        ];
        $components = new ComponentMapper($config);
        $endpoints = new EndpointMapper($config);

        // generate the PHP classes in memmory
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
        shell_exec(Path::join(getcwd(), '/vendor/bin/php-cs-fixer') . " fix " . $basePath);
    }
}
