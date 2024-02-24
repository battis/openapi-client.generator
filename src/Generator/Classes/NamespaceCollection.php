<?php

namespace Battis\OpenAPI\Generator\Classes;

use Battis\OpenAPI\Generator\Exceptions\GeneratorException;
use Battis\PHPGenerator\Type;

class NamespaceCollection
{
    /**
     * @var array<
     *      string,
     *      \Battis\OpenAPI\Generator\Classes\Writable
     *    > $classes
     */
    private array $classes = [];

    /**
     * @var array<string, NamespaceCollection> $children
     */
    private array $subnamespaces = [];

    private string $namespace;

    /**
     * @param string $namespace
     */
    public function __construct(string $namespace)
    {
        assert(
            !empty($namespace),
            new GeneratorException('`$namespace` must be defined')
        );
        $this->namespace = $namespace;
    }

    /**
     * @param string $type
     *
     * @return string[]
     */
    private function getSubnamespaceParts(string $type): array
    {
        return explode("\\", str_replace($this->namespace . "\\", "", $type));
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @return array<string, NamespaceCollection>
     */
    public function getSubnamespaces(): array
    {
        return $this->subnamespaces;
    }

    /**
     * @param bool $recursive
     *
     * @return Writable[]
     */
    public function getClasses(bool $recursive = false): array
    {
        $result = array_merge($this->classes);
        if ($recursive) {
            foreach ($this->subnamespaces as $child) {
                $result = array_merge($result, $child->getClasses($recursive));
            }
        }
        return $result;
    }

    public function containsNamespace(string $namespace): bool
    {
        return strpos($namespace, $this->namespace) === 0;
    }

    public function getClass(string $fqn): ?Writable
    {
        if ($this->containsNamespace($fqn)) {
            $parts = $this->getSubnamespaceParts($fqn);
            array_pop($parts); // class name
            $parent = $this;
            foreach ($parts as $part) {
                if (array_key_exists($part, $parent->subnamespaces)) {
                    $parent = $parent->subnamespaces[$part];
                } else {
                    return null;
                }
            }
            if (array_key_exists($fqn, $parent->classes)) {
                return $parent->classes[$fqn];
            }
        }
        return null;
    }

    /**
     * @param string $namespace
     *
     * @return NamespaceCollection
     */
    public function getNamespaceCollection(string $namespace): NamespaceCollection
    {
        assert(
            $this->containsNamespace($namespace),
            new GeneratorException(
                "Namespace $this->namespace does not contain $namespace"
            )
        );
        $parts = $this->getSubnamespaceParts($namespace);
        $parent = $this;
        foreach ($parts as $part) {
            if (!array_key_exists($part, $parent->subnamespaces)) {
                $parent->subnamespaces[$part] = new NamespaceCollection(
                    $parent->namespace . "\\" . $part
                );
            }
            $parent = $parent->subnamespaces[$part];
        }
        return $parent;
    }

    public function addClass(Writable $class): void
    {
        assert(
            $this->containsNamespace(
                $class->getNamespace()
            ),
            new GeneratorException(
                "Namespace $this->namespace does not contain " .
                $class->getNamespace()
            )
        );
        if ($class->getNamespace() === $this->namespace) {
            assert(
                !array_key_exists($class->getType()->as(Type::FQN), $this->classes),
                new GeneratorException(
                    "Class " .
                    $class->getType()->as(Type::FQN) .
                    " already exists in namespace $this->namespace"
                )
            );
            $this->classes[$class->getType()->as(Type::FQN)] = $class;
        } else {
            $this->getNamespaceCollection($class->getNamespace())->addClass($class);
        }
    }
}
