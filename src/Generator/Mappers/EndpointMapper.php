<?php

namespace Battis\OpenAPI\Generator\Mappers;

use Battis\DataUtilities\Path;
use Battis\OpenAPI\Client\BaseEndpoint;
use Battis\OpenAPI\Generator\Classes\EndpointFactory;
use Battis\OpenAPI\Generator\Classes\NamespaceCollection;
use Battis\OpenAPI\Generator\Classes\Router;
use Battis\OpenAPI\Generator\Exceptions\ConfigurationException;
use Battis\PHPGenerator\Type;
use Psr\Log\LoggerInterface;

/**
 * @api
 */
class EndpointMapper extends BaseMapper
{
    private EndpointFactory $endpointFactory;

    /**
     * @param array{
     *     spec: \cebe\openapi\spec\OpenApi,
     *     basePath: string,
     *     baseNamespace: string,
     *     baseType?: \Battis\PHPGenerator\Type,
     *   } $config
     */
    public function __construct(
        array $config,
        EndpointFactory $endpointFactory,
        LoggerInterface $logger
    ) {
        $config[self::BASE_TYPE] ??= new Type(BaseEndpoint::class);
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
            $this->getBaseType()->is_a(BaseEndpoint::class),
            new ConfigurationException(
                '`' .
                    self::BASE_TYPE .
                    '` must be instance of ' .
                    BaseEndpoint::class
            )
        );

        $this->endpointFactory = $endpointFactory;
    }

    /**
     * @return string[]
     */
    public function supportedOperations(): array
    {
        return [
            'get',
            'put',
            'post',
            'delete',
            'options',
            'head',
            'patch',
            'trace',
        ];
    }

    public function expectedContentType(): string
    {
        return 'application/json';
    }

    public function subnamespace(): string
    {
        return 'Endpoints';
    }

    public function rootRouterName(): string
    {
        return 'Client';
    }

    public function generate(): void
    {
        // generate endpoint classes
        foreach ($this->getSpec()->paths as $path => $pathItem) {
            $path = (string) $path;
            $url = Path::join($this->getSpec()->servers[0]->url, $path);
            $class = $this->endpointFactory->fromPathItem(
                $path,
                $pathItem,
                $this,
                $url
            );
            $sibling = $this->classes->getClass(
                $class->getType()->as(Type::FQN)
            );
            if ($sibling !== null) {
                $sibling->mergeWith($class);
                $this->logger->info(
                    'Merged into ' . $sibling->getType()->as(Type::FQN)
                );
            } else {
                $this->classes->addClass($class);
                $this->logger->info(
                    'Generated ' . $class->getType()->as(Type::FQN)
                );
            }
        }

        // generate router classes to group endpoints conveniently
        $this->generateRouters($this->classes);
        $parts = explode('\\', $this->getBaseNamespace());
        array_pop($parts);
        $collection = new NamespaceCollection(join('\\', $parts));
        $collection->addClass(
            Router::fromClassList(
                $this->getBaseNamespace(),
                $this->classes->getClasses(),
                $this
            )
        );
        foreach ($this->classes->getClasses(true) as $class) {
            $collection->addClass($class);
        }
        $this->classes = $collection;
    }

    private function generateRouters(NamespaceCollection $namespace): void
    {
        foreach ($namespace->getSubnamespaces() as $sub) {
            $this->generateRouters($sub);
            $this->logger->info('Routing ' . $sub->getNamespace());
            $router = Router::fromClassList(
                $sub->getNamespace(),
                $sub->getClasses(),
                $this
            );
            $sibling = $namespace->getClass($router->getType()->as(Type::FQN));
            if ($sibling !== null) {
                $sibling->mergeWith($router);
                $this->logger->info(
                    'Merged into ' . $sibling->getType()->as(Type::FQN)
                );
            } else {
                $namespace->addClass($router);
                $this->logger->info(
                    'Generated ' . $router->getType()->as(Type::FQN)
                );
            }
        }
    }
}
