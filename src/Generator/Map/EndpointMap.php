<?php

namespace Battis\OpenAPI\Generator\Map;

use Battis\DataUtilities\Path;
use Battis\Loggable\Loggable;
use Battis\OpenAPI\Client\BaseEndpoint;
use Battis\OpenAPI\Generator\Exceptions\ConfigurationException;
use Battis\OpenAPI\Generator\Exceptions\GeneratorException;
use Battis\OpenAPI\Generator\Exceptions\SchemaException;
use Battis\OpenAPI\Generator\PHPDoc;
use Battis\OpenAPI\Generator\TypeMap;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Response;
use cebe\openapi\spec\Schema;

/**
 * @api
 */
class EndpointMap extends BaseMap
{
    protected static string $CONTENT_MIMETYPE = "application/json";

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
    public function __construct(array $config)
    {
        $config["baseType"] ??= BaseEndpoint::class;
        parent::__construct($config);
        assert(
            is_a($this->baseType, BaseEndpoint::class, true),
            new ConfigurationException(
                "\$baseType must be instance of " . BaseEndpoint::class
            )
        );
    }

    public function generate(): TypeMap
    {
        foreach ($this->spec->paths as $path => $pathItem) {
            $path = (string) $path;
            $this->log($path);
            $urlParameters = [];
            $normalizedPath = $this->normalizePath($path, $urlParameters);
            $url = Path::join($this->spec->servers[0]->url, $path);

            $name = $this->sanitize->clean(basename($normalizedPath));
            $dir = dirname($normalizedPath);
            if ($dir === "." || $dir === "/") {
                $dir = null;
            }
            $namespace = $this->parseType($dir);
            $filePath = $this->parseFilePath($normalizedPath);

            $this->map->register([
              "url" => $url,
              "path" => $filePath,
              "type" => "$namespace/$name",
            ]);

            $classDoc = new PHPDoc($this->logger);
            if (!empty($pathItem->description)) {
                $classDoc->addItem($pathItem->description);
            }

            $uses = [BaseEndpoint::class];
            $methods = [];
            foreach (
                ["get", "put", "post", "delete", "options", "head", "patch", "trace"]
                as $operation
            ) {
                if ($pathItem->$operation) {
                    $this->log(strtoupper($operation) . " " . $url);
                    $instantiate = false;
                    $methodDoc = new PHPDoc();
                    $op = $pathItem->$operation;
                    assert(is_a($op, Operation::class), new GeneratorException());
                    assert(
                        $op->responses !== null,
                        new SchemaException("$operation $url has no responses")
                    );
                    $responses = $op->responses;
                    /** @var ?\cebe\openapi\spec\Response $resp */
                    $resp = is_array($responses)
                      ? $responses["200"] ?? $responses["201"]
                      : $responses->getResponse("200") ?? $responses->getResponse("201");
                    assert(
                        $resp !== null,
                        new SchemaException("$operation $url has no OK response")
                    );
                    $content = $resp->content;
                    $content = $content[static::$CONTENT_MIMETYPE] ?? null;
                    $type = null;
                    if ($content !== null) {
                        $schema = $content->schema;
                        if ($schema instanceof Reference) {
                            $ref = $schema->getReference();
                            $type = $this->map->getTypeFromSchema($ref, true, true);
                            $methodDoc->addItem("@return $type");
                            $type = $this->map->getTypeFromSchema($ref);
                            array_push($uses, $type);
                            $type = $this->map->getTypeFromSchema($ref, false);
                            if ($operation === "get" && $type !== null) {
                                $this->map->registerUrlGet($url, $type);
                            }
                            $instantiate = true;
                        } elseif ($schema instanceof Schema) {
                            $method = $schema->type;
                            $type = (string) $this->map->$method($schema);
                            $methodDoc->addItem("@return $type");
                        }
                    } else {
                        $type = "void";
                        $methodDoc->addItem("@return $type");
                    }
                    assert(is_string($type), new GeneratorException('type undefined'));
                    $args = [];
                    foreach ($urlParameters as $pattern => $param) {
                        array_push($args, "\"$pattern\" => $param");
                    }
                    $args = "[" . join(", ", $args) . "]";
                    array_push(
                        $methods,
                        $methodDoc->asString(1) .
                        "    public function $operation(" .
                        (empty($urlParameters)
                          ? ""
                          : join(
                              ", ",
                              array_map(fn($n) => "string $n", array_values($urlParameters))
                          )) .
                        ")" .
                        PHP_EOL .
                        "    {" .
                        PHP_EOL .
                        "        return " .
                        ($instantiate ? "new $type(" : "") .
                        "\$this->send(\"$operation\"" .
                        (empty($urlParameters) ? "" : ", $args") .
                        ")" .
                        ($instantiate ? ")" : "") .
                        ";" .
                        PHP_EOL .
                        "    }" .
                        PHP_EOL
                    );
                }
            }

            $fileContents =
              "<?php" .
              PHP_EOL .
              PHP_EOL .
              "namespace $namespace;" .
              PHP_EOL .
              PHP_EOL .
              "use " .
              join(";" . PHP_EOL . "use ", $uses) .
              ";" .
              PHP_EOL .
              PHP_EOL .
              $classDoc->asString() .
              "class $name extends BaseEndpoint" .
              PHP_EOL .
              "{" .
              PHP_EOL .
              "    protected static string \$url = \"$url\";" .
              PHP_EOL .
              PHP_EOL .
              join(PHP_EOL, $methods) .
              "}" .
              PHP_EOL;

            @mkdir(dirname($filePath), 0744, true);
            file_put_contents($filePath, $fileContents);
        }

        return $this->map;
    }

    /**
     * @param string $path
     * @param array<string,string> &$urlParameters
     *
     * @return string
     */
    protected function normalizePath(string $path, array &$urlParameters): string
    {
        $parts = explode("/", $path);
        $namespaceParts = [];
        foreach ($parts as $part) {
            if (preg_match("/\{([^}]+)\}/", $part, $match)) {
                $urlParameters[$part] = "$" . $match[1];
            } else {
                array_push($namespaceParts, $part);
            }
        }
        return (substr($path, 0, 1) === "/" ? "/" : "") .
          join("/", $namespaceParts);
    }
}
