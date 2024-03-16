<?php

namespace Battis\OpenAPI\Generator\Mappers;

use Battis\OpenAPI\Generator\Classes\EndpointFactory;
use Psr\Log\LoggerInterface;

class EndpointMapperFactory
{
    private LoggerInterface $logger;
    private EndpointFactory $endpointFactory;

    public function __construct(
        LoggerInterface $logger,
        EndpointFactory $endpointFactory
    ) {
        $this->logger = $logger;
        $this->endpointFactory = $endpointFactory;
    }

    /**
     * @param array{
     *     spec: \cebe\openapi\spec\OpenApi,
     *     basePath: string,
     *     baseNamespace: string,
     *     baseType?: \Battis\PHPGenerator\Type,
     *   } $config
     */
    public function create(array $config): EndpointMapper
    {
        return new EndpointMapper(
            $config,
            $this->endpointFactory,
            $this->logger
        );
    }
}
