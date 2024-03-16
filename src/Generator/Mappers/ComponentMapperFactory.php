<?php

namespace Battis\OpenAPI\Generator\Mappers;

use Battis\OpenAPI\Generator\Classes\ComponentFactory;
use Psr\Log\LoggerInterface;

class ComponentMapperFactory
{
    private LoggerInterface $logger;
    private ComponentFactory $componentFactory;

    public function __construct(
        LoggerInterface $logger,
        ComponentFactory $componentFactory
    ) {
        $this->logger = $logger;
        $this->componentFactory = $componentFactory;
    }

    /**
     * @param array{
     *     spec: \cebe\openapi\spec\OpenApi,
     *     basePath: string,
     *     baseNamespace: string,
     *     baseType?: \Battis\PHPGenerator\Type,
     *   } $config
     */
    public function create($config): ComponentMapper
    {
        return new ComponentMapper(
            $config,
            $this->componentFactory,
            $this->logger
        );
    }
}
