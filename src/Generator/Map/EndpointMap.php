<?php

namespace Battis\OpenAPI\Generator\Map;

use Battis\DataUtilities\Path;
use Battis\Loggable\Loggable;
use Battis\OpenAPI\Client\BaseEndpoint;
use Battis\OpenAPI\Generator\Exceptions\ConfigurationException;
use Battis\OpenAPI\Generator\Exceptions\SchemaException;
use Battis\OpenAPI\Generator\PHPDoc;
use Battis\OpenAPI\Generator\TypeMap;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Response;

/**
 * @api
 */
class EndpointMap extends BaseMap
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
    public function __construct(array $config)
    {
        $config['baseType'] ??= BaseEndpoint::class;
        parent::__construct($config);
        assert(is_a($this->baseType, BaseEndpoint::class, true), new ConfigurationException("\$baseType must be instance of " . BaseEndpoint::class));
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
            if ($dir === '.' || $dir === '/') {
                $dir = null;
            }
            $namespace = $this->parseType($dir);
            $filePath = $this->map->getFilepathFromUrl($normalizedPath);
            $this->log([
                'path' => $path,
                'normalizedPath' => $normalizedPath,
                'urlParameters' => $urlParameters,
                'name' => $name,
                'namespace' => $namespace,
                'filePath' => $filePath,
            ], Loggable::DEBUG);
            if ($filePath !== null) {
                $this->modifyFile($pathItem, $urlParameters, $url, $name, $namespace, $filePath);
            } else {
                $this->createFile($pathItem, $urlParameters, $url, $name, $namespace, $this->parseFilePath($normalizedPath));
            }
        }

        return $this->map;
    }

    /**
     * @param PathItem $pathItem
     * @param array<string,string> $urlParameters
     * @param string $url
     * @param string $name
     * @param string $namespace
     * @param string $filePath
     *
     * @return void
     */
    protected function createFile(
        PathItem $pathItem,
        array $urlParameters,
        string $url,
        string $name,
        string $namespace,
        string $filePath
    ): void {
        $this->map->register([
            'url' => $url,
            'path' => $filePath,
            'type' => "$namespace/$name",
        ]);

        $classDoc = new PHPDoc($this->logger);
        if (!empty($pathItem->description)) {
            $classDoc->addItem($pathItem->description);
        }

        $uses = [BaseEndpoint::class];
        $methods = [];
        foreach(['get','put', 'post','delete','options','head','patch','trace']  as $operation) {
            if ($pathItem->$operation) {
                $this->log(strtoupper($operation) . " " . $url);
                $instantiate = false;
                /** @var \cebe\openapi\spec\Operation $op */
                $op = $pathItem->$operation;
                assert($op->responses !== null, new SchemaException("$operation $url has no responses"));
                $responses = $op->responses;
                $resp = is_array($responses) ? ($responses['200'] ?? $responses['201']) : $responses->getResponse('200') ?? ($responses->getResponse('201'));
                assert($resp !== null, new SchemaException("$operation $url has no OK response"));
                $content = $resp instanceof Response && property_exists($resp, 'content') ? $resp->content : null;
                if ($content !== null) {
                    $schema = $content['application/json']
                        ->schema;
                    if ($schema instanceof Reference) {
                        $ref = $schema->getReference();
                        $type = $this->map->getTypeFromSchema($ref);
                        assert($type !== null, new SchemaException("$operation $url response 200 does not resolve to a type"));
                        array_push($uses, $type);
                        if ($operation === 'get') {
                            $this->map->registerUrlGet($url, $type);
                        }
                        $type = $this->map->getTypeFromSchema($ref, false);
                        $instantiate = true;
                    } else {
                        /** @var \cebe\openapi\spec\Schema $schema */
                        $method = $schema->type;
                        /** @var string $type */
                        $type = $this->map->$method($schema);
                    }
                } else {
                    $type = 'void';
                }
                $args = [];
                foreach($urlParameters as $pattern => $param) {
                    array_push($args, "\"$pattern\" => $param");
                }
                $args = "[" . join(", ", $args) . "]";
                array_push(
                    $methods,
                    "    public function $operation(" . (empty($urlParameters) ? "" : join(", ", array_map(fn($n) => "string $n", array_values($urlParameters)))) . "): $type" . PHP_EOL .
                    "    {" . PHP_EOL .
                    "        return " . ($instantiate ? "new $type(" : "") . "\$this->send(\"$operation\"" . (empty($urlParameters) ? "" : ", $args") . ")" . ($instantiate ? ")" : "") . ";" . PHP_EOL .
                    "    }" . PHP_EOL
                );
            }
        }

        $fileContents = "<?php" . PHP_EOL . PHP_EOL .
        "namespace $namespace;" . PHP_EOL . PHP_EOL .
        "use " . join(";" . PHP_EOL . "use ", $uses) . ";" . PHP_EOL . PHP_EOL .
        $classDoc->asString() .
        "class $name extends BaseEndpoint" . PHP_EOL .
        "{" . PHP_EOL .
        "    protected static string \$url = \"$url\";" . PHP_EOL . PHP_EOL .
        join(PHP_EOL, $methods) .
        "}" . PHP_EOL;

        @mkdir(dirname($filePath), 0744, true);
        file_put_contents($filePath, $fileContents);
    }

    /**
     * @param PathItem $pathItem
     * @param array<string,string> $urlParameters
     * @param string $url
     * @param string $name
     * @param string $namespace
     * @param string $filePath
     *
     * @return void
     */
    protected function modifyFile(
        PathItem $pathItem,
        array $urlParameters,
        string $url,
        string $name,
        string $namespace,
        string $filePath
    ): void {
        $this->log([
            'urlParameters' => $urlParameters,
            'url' => $url,
            'name' => $name,
            'namespace' => $namespace,
            'filePath' => $filePath,
            'modifyFilePath' => $this->map->getFilePathFromUrlGet(preg_replace("/^([^{]+).*$/", "$1", $url)),
        ], Loggable::DEBUG);
    }

    /**
     * @param string $path
     * @param array<string,string> &$urlParameters
     *
     * @return string
     */
    protected function normalizePath(string $path, array &$urlParameters): string
    {
        $parts = explode('/', $path);
        $namespaceParts = [];
        foreach($parts as $part) {
            if (preg_match("/\{([^}]+)\}/", $part, $match)) {
                $urlParameters[$part] = "$" . $match[1];
            } else {
                array_push($namespaceParts, $part);
            }
        }
        return (substr($path, 0, 1) === "/" ? "/" : "") . join("/", $namespaceParts);
    }
}
