<?php

namespace Battis\OpenAPI\Generator\Classes;

use Battis\OpenAPI\Generator\Exceptions\GeneratorException;

class NamespaceCollection
{
    /**
     * @var array<class-string<\Battis\OpenAPI\Generator\Classes\Writable>, \Battis\OpenAPI\Generator\Classes\Writable> $classes
     */
    private array $classes = [];

    /**
     * @var array<string, NamespaceCollection> $children
     */
    private array $children = [];

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
        return $this->children;
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
            foreach ($this->children as $child) {
                $result = array_merge($result, $child->getClasses($recursive));
            }
        }
        return $result;
    }

    public function containsNamespace(string $namespace): bool
    {
        return strpos($namespace, $this->namespace) === 0;
    }

    /**
     * @param class-string<\Battis\OpenAPI\Generator\Classes\Writable> $type
     *
     * @return ?Writable
     */
    public function getClass(string $type): ?Writable
    {
        if ($this->containsNamespace($type)) {
            $parts = $this->getSubnamespaceParts($type);
            array_pop($parts); // class name
            $parent = $this;
            foreach ($parts as $part) {
                if (array_key_exists($part, $parent->children)) {
                    $parent = $parent->children[$part];
                } else {
                    return null;
                }
            }
            if (array_key_exists($type, $parent->classes)) {
                return $parent->classes[$type];
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
            if (!array_key_exists($part, $parent->children)) {
                $parent->children[$part] = new NamespaceCollection(
                    $parent->namespace . "\\" . $part
                );
            }
            $parent = $parent->children[$part];
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
                !array_key_exists($class->getType(), $this->classes),
                new GeneratorException(
                    "Class " .
                    $class->getType() .
                    " already exists in namespace $this->namespace"
                )
            );
            /** @psalm-suppress PropertyTypeCoercion */
            $this->classes[$class->getType()] = $class;
        } else {
            $this->getNamespaceCollection($class->getNamespace())->addClass($class);
        }
    }
}
