<?php

namespace Battis\OpenAPI\Generator\Map;

use Battis\DataUtilities\Path;
use Battis\OpenAPI\Client\BaseObject;
use Battis\OpenAPI\Generator\Exceptions\ConfigurationException;
use Battis\OpenAPI\Generator\Exceptions\SchemaException;
use Battis\OpenAPI\Generator\TypeMap;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;

/**
 * @api
 */
class ObjectMap extends BaseMap
{
    /**
     * @var ClassObject[] $objects
     */
    private $objects = [];

    /**
     * @param array{
     *     spec: \cebe\openapi\spec\OpenApi,
     *     basePath: string,
     *     baseNamespace: string,
     *     baseType?: string,
     *     sanitize?: \Battis\OpenAPI\Generator\Sanitize,
     *     typeMap?: \Battis\OpenAPI\Generator\TypeMap,
     *     logger?: ?\Psr\Log\LoggerInterface
     *   } $config
     */
    public function __construct($config)
    {
        $config['baseType'] ??= BaseObject::class;
        parent::__construct($config);
        assert(is_a($this->baseType, BaseObject::class, true), new ConfigurationException("\$baseType must be instance of " . BaseObject::class));
    }

    public function generate(): TypeMap
    {
        assert(
            $this->spec->components && $this->spec->components->schemas,
            new SchemaException("#/components/schemas not defined")
        );

        foreach (array_keys($this->spec->components->schemas) as $name) {
            $ref = "#/components/schemas/$name";
            $name = $this->sanitize->clean((string) $name);
            $this->map->registerSchema($ref, $this->parseType($name));
            $this->log($ref);
        }

        foreach ($this->spec->components->schemas as $name => $schema) {
            if ($schema instanceof Reference) {
                $schema = $schema->resolve();
                /** @var Schema $schema (because we just resolved it)*/
            }
            $this->objects[$name] = ObjectClass::fromSchema($name, $schema, $this);
        }
        return $this->map;
    }

    public function writeFiles()
    {
        foreach($this->objects as $class) {
            $filePath = Path::join($this->basePath, $class->getName() . ".php");
            file_put_contents($filePath, $class);
            $this->log($filePath);
        }
        shell_exec(Path::join(getcwd(), '/vendor/bin/php-cs-fixer') . " fix " . $this->basePath);
    }
}
