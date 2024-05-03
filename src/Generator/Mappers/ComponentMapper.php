<?php

namespace Battis\OpenAPI\Generator\Mappers;

use Battis\DataUtilities\Path;
use Battis\OpenAPI\Client\BaseComponent;
use Battis\OpenAPI\Generator\Classes\ComponentFactory;
use Battis\OpenAPI\Generator\Exceptions\ConfigurationException;
use Battis\OpenAPI\Generator\Exceptions\GeneratorException;
use Battis\OpenAPI\Generator\Exceptions\SchemaException;
use Battis\OpenAPI\Generator\Sanitize;
use Battis\OpenAPI\Generator\TypeMap;
use Battis\PHPGenerator\Type;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * @api
 */
class ComponentMapper extends BaseMapper
{
    public const IS_TRAVERSABLE = 'isTraversable';
    public const BASE_TRAVERSABLE_TYPE = 'baseTraversableType';
    public const GET_TRAVERSABLE_PROPERTY_NAME = 'getTraversablePropertyName';

    private ComponentFactory $componentFactory;

    private ?Type $baseTraversableType;

    /** @var callable(\cebe\openapi\spec\Schema): bool */
    private $isTraversable;

    /** @var callable(\cebe\openapi\spec\Schema): string */
    private $getTraversablePropertyName;

    /**
     * @param array{
     *     spec: \cebe\openapi\spec\OpenApi,
     *     basePath: string,
     *     baseNamespace: string,
     *     baseType?: \Battis\PHPGenerator\Type,
     *     isTraversable?: callable(\cebe\openapi\spec\Schema): bool,
     *     baseTraversableType?: \Battis\PHPGenerator\Type,
     *     getTraversablePropertyName?: callable(\cebe\openapi\spec\Schema): string
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

        if (
            !empty($config[self::BASE_TRAVERSABLE_TYPE]) ||
            !empty($config[self::IS_TRAVERSABLE]) ||
            !empty($config[self::GET_TRAVERSABLE_PROPERTY_NAME])
        ) {
            assert(
                !empty($config[self::BASE_TRAVERSABLE_TYPE]) &&
                    !empty($config[self::IS_TRAVERSABLE]) &&
                    !empty($config[self::GET_TRAVERSABLE_PROPERTY_NAME]),
                new ConfigurationException(
                    '`' .
                        self::BASE_TRAVERSABLE_TYPE .
                        '`, `' .
                        self::IS_TRAVERSABLE .
                        '`, and `' .
                        self::GET_TRAVERSABLE_PROPERTY_NAME .
                        '` must all be defined together'
                )
            );
            $this->baseTraversableType = $config[self::BASE_TRAVERSABLE_TYPE];
            assert(
                $this->getBaseTraversableType()->is_a(BaseComponent::class),
                new ConfigurationException(
                    '`' .
                        self::BASE_TRAVERSABLE_TYPE .
                        '` must be instance of ' .
                        BaseComponent::class
                )
            );
            $this->isTraversable = $config[self::IS_TRAVERSABLE];
            $this->getTraversablePropertyName =
                $config[self::GET_TRAVERSABLE_PROPERTY_NAME];
        }
    }

    public function getBaseTraversableType(): ?Type
    {
        return $this->baseTraversableType;
    }

    public function isTraversable(Schema $schema): bool
    {
        if (empty($this->isTraversable)) {
            return false;
        }
        return call_user_func($this->isTraversable, $schema);
    }

    public function getTraversablePropertyName(Schema $schema): string
    {
        assert(
            !empty($this->getTraversablePropertyName),
            new GeneratorException(
                '`' . self::GET_TRAVERSABLE_PROPERTY_NAME . '` undefined'
            )
        );
        return call_user_func($this->getTraversablePropertyName, $schema);
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
