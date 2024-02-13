<?php

namespace Battis\OpenAPI\Generator\Map;

use Battis\Loggable\Loggable;
use Battis\OpenAPI\Client\Object\BaseObject;
use Battis\OpenAPI\Exceptions\ConfigurationException;
use Battis\OpenAPI\Exceptions\SchemaException;
use Battis\OpenAPI\Generator\PHPDoc;
use Battis\OpenAPI\Generator\Sanitize;
use Battis\OpenAPI\Generator\TypeMap;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;
use Psr\Log\LoggerInterface;

class ObjectMap extends BaseMap
{
    public function __construct(OpenApi $spec, string $basePath, string $baseNamespace, ?string $baseType = null, ?Sanitize $sanitize = null, ?TypeMap $typeMap = null, ?LoggerInterface $logger = null)
    {
        parent::__construct($spec, $basePath, $baseNamespace, $baseType ?? BaseObject::class, $sanitize, $typeMap, $logger);
        assert(is_a($this->baseType, BaseObject::class, true), new ConfigurationException("$baseType must be instance of " . BaseObject::class));
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
            $this->map->registerSchema($ref, $this->parseNamespace($name));
            $this->log($ref);
        }

        foreach ($this->spec->components->schemas as $_name => $schema) {
            if ($schema instanceof Reference) {
                $ref = $schema->getReference();
                $schema = $schema->resolve();
                $this->log([
                    "loc" => __FILE__ . "#" . __FUNCTION__ . "()@" . __LINE__,
                    "ref" => $ref,
                    "type" => $schema::class,
                ], Loggable::DEBUG);
            }
            /** @var Schema $schema (because we just resolved it)*/

            $name = $this->sanitize->clean((string) $_name);
            $namespace = $this->parseNamespace();
            $filePath = $this->parseFilePath($name);
            $this->map->registerClass("$namespace\\$name", $filePath);
            $this->log("$namespace\\$name");

            $classDoc = new PHPDoc();
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
                    $this->log(['ref' => $ref, 'class' => $property::class], Loggable::DEBUG);
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
              // TODO the short name pf $this->baseType feels like a hack
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

            @mkdir(dirname($filePath, true));
            file_put_contents($filePath, $fileContents);
        }
        return $this->map;
    }
}
