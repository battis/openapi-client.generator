<?php

namespace Battis\OpenAPI\Generator\Mappers;

use Battis\DataUtilities\Path;
use Battis\OpenAPI\CLI\Logger;
use Battis\OpenAPI\Client\BaseComponent;
use Battis\OpenAPI\Generator\Classes\Component;
use Battis\OpenAPI\Generator\Exceptions\ConfigurationException;
use Battis\OpenAPI\Generator\Exceptions\SchemaException;
use Battis\OpenAPI\Generator\Sanitize;
use Battis\OpenAPI\Generator\TypeMap;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;

/**
 * @api
 */
class ComponentMapper extends BaseMapper
{
    public function simpleNamespace(): string
    {
        return "Components";
    }

    /**
     * @param array{
     *     spec: \cebe\openapi\spec\OpenApi,
     *     basePath: string,
     *     baseNamespace: string,
     *     baseType?: string,
     *   } $config
     */
    public function __construct($config)
    {
        $config[self::BASE_TYPE] ??= BaseComponent::class;
        $config[self::BASE_PATH] = Path::join($config[self::BASE_PATH], $this->simpleNamespace());
        $config[self::BASE_NAMESPACE] = Path::join("\\", [$config[self::BASE_NAMESPACE], $this->simpleNamespace()]);
        parent::__construct($config);
        assert(
            is_a($this->getBaseType(), BaseComponent::class, true),
            new ConfigurationException("`" . self::BASE_TYPE . "` must be instance of " . BaseComponent::class)
        );
    }

    public function generate(): void
    {
        $map = TypeMap::getInstance();
        $sanitize = Sanitize::getInstance();

        assert(
            $this->getSpec()->components && $this->getSpec()->components->schemas,
            new SchemaException("#/components/schemas not defined")
        );

        // pre-map all the schemas to FQN class names
        foreach (array_keys($this->getSpec()->components->schemas) as $name) {
            $ref = "#/components/schemas/$name";
            $nameParts = array_map(fn(string $p) => $sanitize->clean($p), explode('.', $name));
            $map->registerSchema($ref, Path::join("\\", [$this->getBaseNamespace(), $nameParts]));
            Logger::log("Mapped $ref => " . $map->getTypeFromSchema($ref));
        }

        // generate the classes representing all the components defined in the spec
        foreach ($this->getSpec()->components->schemas as $name => $schema) {
            if ($schema instanceof Reference) {
                $schema = $schema->resolve();
                /** @var Schema $schema (because we just resolved it)*/
            }
            $class = Component::fromSchema("#/components/schemas/$name", $schema, $this);
            Logger::log("Generated " . $class->getType());
            $map->registerClass($class);
            $this->classes->addClass($class);
        }
    }
}
