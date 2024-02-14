<?php

namespace Battis\OpenAPI\Generator\Map;

use Battis\Loggable\Loggable;
use Battis\OpenAPI\Client\BaseObject;
use Battis\OpenAPI\Generator\Exceptions\ConfigurationException;
use Battis\OpenAPI\Generator\Exceptions\SchemaException;
use Battis\OpenAPI\Generator\PHPDoc;
use Battis\OpenAPI\Generator\TypeMap;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;

/**
 * @api
 */
class ObjectMap extends BaseMap
{
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

        foreach ($this->spec->components->schemas as $_name => $schema) {
            if ($schema instanceof Reference) {
                $schema = $schema->resolve();
                /** @var Schema $schema (because we just resolved it)*/
            }

            $name = $this->sanitize->clean((string) $_name);
            $namespace = $this->parseType();
            $filePath = $this->parseFilePath($name);
            $this->map->registerType("$namespace\\$name", $filePath);
            $this->log("$namespace\\$name");

            $classDoc = new PHPDoc($this->logger);
            if (!empty($schema->description)) {
                $classDoc->addItem($schema->description);
            }
            $fileContents =
              "<?php" .
              PHP_EOL .
              PHP_EOL .
              "namespace $namespace;" .
              PHP_EOL .
              PHP_EOL .
              "use " .
              $this->baseType .
              ";" .
              PHP_EOL .
              PHP_EOL;
            $fields = [];
            foreach ($schema->properties as $key => $property) {
                $type = null;
                if ($property instanceof Reference) {
                    $ref = $property->getReference();
                    $property = $property->resolve();
                    $type = $this->map->getTypeFromSchema($ref, true, true);
                }
                /** @var Schema $property (because we just resolved it)*/

                array_push($fields, $key);
                $method = $property->type;
                $type ??= (string) $this->map->$method($property, true);
                // TODO handle enums

                $classDoc->addItem(
                    "@property $type" . // FIXME $type should be absolute
                    ($property->nullable ? " | null" : "") .
                    " $$key " .
                    $property->description
                );
            }

            // TODO additionalProperties
            $classDoc->addItem("@api");
            $fileContents .=
              $classDoc->asString(0) .
              "class $name extends " .
              preg_replace("/^.*\\\\/", "", $this->baseType) .
              PHP_EOL .
              "{" .
              PHP_EOL .
              "    /** @var string[] \$fields */" .
              PHP_EOL .
              "    protected static array \$fields = " .
              json_encode($fields) .
              ";" .
              PHP_EOL .
              "}" .
              PHP_EOL;

            @mkdir(dirname($filePath), 0744, true);
            file_put_contents($filePath, $fileContents);
        }
        return $this->map;
    }
}
