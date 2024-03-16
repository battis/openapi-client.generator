<?php

namespace Battis\OpenAPI\Generator\Mappers;

use Battis\DataUtilities\Path;
use Battis\OpenAPI\Client\Mappable;
use Battis\OpenAPI\Generator\Classes\NamespaceCollection;
use Battis\OpenAPI\Generator\Exceptions\ConfigurationException;
use Battis\OpenAPI\Generator\Exceptions\GeneratorException;
use Battis\PHPGenerator\Type;
use cebe\openapi\spec\OpenApi;
use Psr\Log\LoggerInterface;

abstract class BaseMapper
{
    public const SPEC = 'spec';
    public const BASE_PATH = 'basePath';
    public const BASE_NAMESPACE = 'baseNamespace';
    public const BASE_TYPE = 'baseType';

    private OpenApi $spec;

    private Type $baseType;
    private string $basePath;
    private string $baseNamespace;

    protected LoggerInterface $logger;

    /**
     * @param array{
     *     spec: \cebe\openapi\spec\OpenApi,
     *     basePath: string,
     *     baseNamespace: string,
     *     baseType: \Battis\PHPGenerator\Type,
     *   } $config
     */
    public function __construct(array $config, LoggerInterface $logger)
    {
        $this->spec = $config[self::SPEC];

        $this->baseType = $config[self::BASE_TYPE];
        assert(
            $this->baseType->is_a(Mappable::class),
            new ConfigurationException(
                '`' .
                    self::BASE_TYPE .
                    '` must be instance of ' .
                    Mappable::class
            )
        );

        $this->basePath = Path::canonicalize(
            $config[self::BASE_PATH],
            getcwd()
        );
        @mkdir($this->basePath, 0744, true);

        assert(
            !empty($config[self::BASE_NAMESPACE]),
            new ConfigurationException(
                '`' . self::BASE_NAMESPACE . '` must be defined'
            )
        );
        $this->baseNamespace = trim($config[self::BASE_NAMESPACE], '\\');

        $this->classes = new NamespaceCollection($this->baseNamespace);

        $this->logger = $logger;
    }

    abstract public function subnamespace(): string;

    public function getSpec(): OpenApi
    {
        return $this->spec;
    }

    public function getBaseType(): Type
    {
        return $this->baseType;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function getBaseNamespace(): string
    {
        return $this->baseNamespace;
    }

    protected NamespaceCollection $classes;

    /**
     * @api
     */
    abstract public function generate(): void;

    public function writeFiles(): void
    {
        foreach ($this->classes->getClasses(true) as $class) {
            $filePath = Path::join($this->basePath, $class->getPath() . '.php');
            @mkdir(dirname($filePath), 0744, true);
            assert(
                !file_exists($filePath),
                new GeneratorException(
                    "$filePath exists and cannot be overwritten"
                )
            );
            file_put_contents($filePath, $class->asImplementation());
            $this->logger->info(
                'Wrote ' . $class->getType()->as(Type::FQN) . " to $filePath"
            );
        }
    }
}
