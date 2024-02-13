<?php

namespace Battis\OpenAPI\Generator\Map;

use Battis\Loggable\Loggable;
use Battis\OpenAPI\Client\Endpoint\BaseEndpoint;
use Battis\OpenAPI\Exceptions\ConfigurationException;
use Battis\OpenAPI\Generator\PHPDoc;
use Battis\OpenAPI\Generator\Sanitize;
use Battis\OpenAPI\Generator\TypeMap;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\PathItem;
use Psr\Log\LoggerInterface;

class EndpointMap extends BaseMap
{
    public function __construct(OpenApi $spec, string $basePath, string $baseNamespace, ?string $baseType = null, ?Sanitize $sanitize = null, ?TypeMap $typeMap = null, ?LoggerInterface $logger = null)
    {
        parent::__construct($spec, $basePath, $baseNamespace, $baseType ?? BaseEndpoint::class, $sanitize, $typeMap, $logger);
        assert(is_a($this->baseType, BaseEndpoint::class, true), new ConfigurationException("\$baseType must be instance of " . BaseEndpoint::class));
    }

    public function generate(): TypeMap
    {
        $deferred = [];

        foreach ($this->spec->paths as $path => $pathItem) {
            $path = (string) $path;
            $urlParameter = null;
            if (strpos($path, "{") != false &&  preg_match("/^([^{]+)\/\{([^}]+)\}$/", $path, $match)) {
                $path = $match[1];
                $urlParameter = $match[2];
            } elseif (strpos($path, "{")) {
                array_push($deferred, $path);
                $this->log("Deferring `$path`", Loggable::DEBUG);
                continue;
            }
            $url = $this->spec->servers[0]->url . $path;

            $name = $this->sanitize->clean(basename($path));
            $namespace = $this->parseNamespace(trim(dirname($path), '/'));
            $this->log($url);
            $filePath = $this->map->getFilepathFromUrl($url);
            if ($filePath) {
                $this->modifyFile($pathItem, $urlParameter, $url, $name, $namespace, $filePath);
            } else {
                $this->createFile($pathItem, $urlParameter, $url, $name, $namespace, $this->parseFilePath($path));
            }
        }

        $this->log(['deferred' => $deferred], Loggable::DEBUG);

        return $this->map;
    }

    private function createFile(
        PathItem $pathItem,
        ?string $urlParameter,
        string $url,
        string $name,
        string $namespace,
        string $filePath
    ) {
        $this->map->register([
            'url' => $url,
            'path' => $filePath,
            'type' => "$namespace/$name",
        ]);

        $classDoc = new PHPDoc();
        if (!empty($pathItem->description)) {
            $classDoc->addItem($pathItem->description);
        }

        $uses = [BaseEndpoint::class];
        $methods = [];
        foreach(['get','put', 'post','delete','options','head','patch','trace']  as $operation) {
            if ($pathItem->$operation) {

                $content = $pathItem
                    ->$operation
                    ->responses[200]
                    ->content ?? null;
                if ($content) {
                    $ref = $content['application/json']
                        ->schema
                        ->getReference();
                    array_push($uses, $this->map->getTypeFromSchema($ref));
                    $type = $this->map->getTypeFromSchema($ref, false);
                } else {
                    $type = 'void';
                }
                array_push(
                    $methods,
                    "    public function $operation(" . ($urlParameter ? "string \$$urlParameter" : "") . "): $type" . PHP_EOL .
                    "    {" . PHP_EOL .
                    "        return " . ($content ? "new $type(" : "") . "\$this->send(\"$operation\"" . ($urlParameter ? ", \$$urlParameter" : "") . ($content ? ")" : "") . ");" . PHP_EOL .
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

        @mkdir(dirname($filePath, true));
        file_put_contents($filePath, $fileContents);
    }

    private function modifyFile(
        PathItem $pathItem,
        ?string $urlParameter,
        string $url,
        string $name,
        string $namespace,
        string $filePath
    ) {
        $this->log([
            'urlParameter' => $urlParameter,
            'url' => $url,
            'name' => $name,
            'namespace' => $namespace,
            'filePath', $filePath,
        ], Loggable::DEBUG);
    }
}
