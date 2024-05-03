<?php

namespace Battis\OpenAPI\Generator\Mappers;

use Battis\DataUtilities\Path;
use Battis\OpenAPI\Client\BaseComponent;
use Battis\OpenAPI\Generator\Classes\ComponentFactory;
use Battis\OpenAPI\Generator\Exceptions\ConfigurationException;
use Battis\OpenAPI\Generator\Exceptions\SchemaException;
use Battis\OpenAPI\Generator\Sanitize;
use Battis\OpenAPI\Generator\TypeMap;
use Battis\PHPGenerator\Type;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;
use Psr\Log\LoggerInterface;

/**
 * @api
 */
class ComponentMapper extends BaseMapper
{
    private ComponentFactory $componentFactory;

    /**
     * @param array{
     *     spec: \cebe\openapi\spec\OpenApi,
     *     basePath: string,
     *     baseNamespace: string,
     *     baseType?: \Battis\PHPGenerator\Type,
     *   } $config
     */
    public function __construct(
        $config,
        ComponentFactory $componentFactory,
        LoggerInterface $logger
    ) {
        $config[self::BASE_TYPE] ??= new Type(BaseComponent::class);
        $config[self::BASE_PATH] = Path::join(
            $config[self::BASE_PATH],
            $this->subnamespace()
        );
        $config[self::BASE_NAMESPACE] = Path::join('\\', [
            $config[self::BASE_NAMESPACE],
            $this->subnamespace(),
        ]);
        parent::__construct($config, $logger);
        assert(
            $this->getBaseType()->is_a(BaseComponent::class),
            new ConfigurationException(
                '`' .
                    self::BASE_TYPE .
                    '` must be instance of ' .
                    BaseComponent::class
            )
        );

        $this->componentFactory = $componentFactory;
    }

    public function subnamespace(): string
    {
        return 'Components';
    }

    public function generate(): void
    {
        $map = TypeMap::getInstance();
        $sanitize = Sanitize::getInstance();

        assert(
            ($c = $this->getSpec()->components) !== null && $c->schemas,
            new SchemaException('#/components/schemas not defined')
        );

        // pre-map all the schemas to FQN class names
        foreach (array_keys($c->schemas) as $name) {
            $ref = "#/components/schemas/$name";
            $nameParts = array_map(
                fn(string $p) => $sanitize->clean($p),
                explode('.', (string) $name)
            );
            $type = new Type(
                Path::join('\\', [$this->getBaseNamespace(), $nameParts])
            );
            $map->registerReference($ref, $type);
            $this->logger->info("Mapped $ref => " . $type->as(Type::FQN));
        }

        // generate the classes representing all the components defined in the spec
        assert(
            ($c = $this->getSpec()->components) !== null,
            new SchemaException('null schema definition')
        );
        foreach ($c->schemas as $name => $schema) {
            if ($schema instanceof Reference) {
                $schema = $schema->resolve();
                /** @var Schema $schema (because we just resolved it)*/
            }
            $class = $this->componentFactory->fromSchema(
                "#/components/schemas/$name",
                $schema,
                $this
            );
            $this->logger->info(
                'Generated ' . $class->getType()->as(Type::FQN)
            );
            $map->registerClass($class);
            $this->classes->addClass($class);
        }
    }
}
