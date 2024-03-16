<?php

namespace Example;

use Battis\OpenAPI\Generator;
use Battis\OpenAPI\Generator\Classes\EndpointFactory;
use DI\ContainerBuilder;
use Example\Extend\GroupingGenerator;
use Example\Extend\PrettierEndpointFactory;
use Psr\Log\LoggerInterface;

use function DI\get;

/**
 * Use dependency injection to extend the generator
 */
class Builder
{
    public static function build()
    {
        // define dependencies to inject (those not specififed will be defaults)
        $builder = new ContainerBuilder();
        $builder->addDefinitions([
            // use the generic color-coded console log
            LoggerInterface::class => fn() => Generator::getDefaultLogger(),

            // inject our extensions
            Generator::class => get(GroupingGenerator::class),
            EndpointFactory::class => get(PrettierEndpointFactory::class),
        ]);
        $container = $builder->build();
        $generator = $container->get(Generator::class);

        // build our clients
        $generator->geenrate(
            __DIR__ . '/../var', // scan recursively for the specs in /var

            // build clients in Example\Client namespace
            __DIR__ . '/../src/Client',
            'Example\Client',
            true // purge any files present before writing new files
        );
    }
}
