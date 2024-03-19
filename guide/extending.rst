Extending Factories
###################

Using this framework, one can extend various factories, implementing the suggested API hooks to tweak how the client is generated.

One might extend the ``Generator`` to better organize the namespaces of a directory of OpenAPI specifications.

.. code-block:: php

    use Battis\DataUtilities\Path;
    use Battis\OpenAPI\Generator;
    use cebe\openapi\spec\OpenApi;
    
    /**
     * We know that the OpenAPI specifications we're dealing with have titles that
     * we can use to organize our clients (e.g. "Foo Bar", "Foo Baz" so we'll
     * organize them as "Foo\Bar" and "Foo\Baz").
     */
    class GroupingGenerator extends Generator
    {
        protected function getNamespaceFromSpec(
            string $specPath,
            OpenApi $spec,
            string $baseNamespace
        ): string {
            return Path::join(
                '\\',
                array_merge([$baseNamespace], explode(' ', $spec->info->title))
            );
        }
    
        protected function getBasePathFromSpec(
            string $specPath,
            OpenApi $spec,
            string $basePath
        ): string {
            return Path::join(
                array_merge([$basePath], explode(' ', $spec->info->title))
            );
        }
    }

We can then inject this dependency in our :doc:`basic` by simply adjusting the ``ContainerBuilder`` definition.

.. code-block:: php

    $builder->addDefinitions([
        // use the generic color-coded console log
        LoggerInterface::class => fn() => Generator::getDefaultLogger(),
    
        // inject our extensions
        Generator::class => DI\get(GroupingGenerator::class)
    ]);


