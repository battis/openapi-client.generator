Basic Implementation
####################

While you can certainly instantiate the various required objects by hand, the recommended approach is to use dependency injection (for example, `PHP-DI <https://php-di.org>`_). This allows for relatively easy extension of the various factories, without having to keep track of what goes where.

.. code-block:: php

    namespace Example;
    
    use Battis\OpenAPI\Generator;
    use Battis\OpenAPI\Generator\Classes\EndpointFactory;
    use DI;
    use Psr\Log\LoggerInterface;
    
    class Builder
    {
        public static function build()
        {
            $builder = new DI\ContainerBuilder();
            $builder->addDefinitions([
                LoggerInterface::class => fn() => Generator::getDefaultLogger(),
            ]);
            $container = $builder->build();
            $generator = $container->get(Generator::class);
    
            // build our clients
            $generator->generate(
                'path/to/openapi/spec/file(s)',
                'path/to/output/directory',
                'Client\Namespace'
                true // purge any files present before writing new files
            );
        }
    }