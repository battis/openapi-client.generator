<?php

namespace Battis\OpenAPI\Generator\Classes;

use Battis\Loggable\Loggable;
use Battis\OpenAPI\Generator\Exceptions\GeneratorException;

class NamespaceCollection extends Loggable
{
    /**
     * @var array<string, Writable> $classes
     */
    private array $classes = [];

    /**
     * @var array>string, NamespaceCollection> $children
     */
    private array $children = [];

    private string $namespace;

    public function __construct(string $namespace)
    {
        parent::__construct();
        assert(!empty($namespace), new GeneratorException('`$namespace` must be defined'));
        $this->namespace = $namespace;
    }

    private function getSubnamespaceParts(string $type)
    {
        return explode("\\", str_replace($this->namespace . "\\", "", $type));
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @return NamespaceCollection[];
     */
    public function getSubnamespaces(): array
    {
        return $this->children;
    }

    /**
     * @param bool $recursive
     *
     * @return Writeable[]
     */
    public function getClasses(bool $recursive = false): array
    {
        $result = array_merge($this->classes);
        if ($recursive) {
            foreach($this->children as $child) {
                $result = array_merge($result, $child->getClasses($recursive));
            }
        }
        return $result;
    }

    public function containsNamespace(string $namespace): bool
    {
        return strpos($namespace, $this->namespace) === 0;
    }

    public function hasClass(string $type): bool
    {
        return $this->getClass($type) !== null;
    }

    public function getClass(string $type): ?Writable
    {
        if ($this->containsNamespace($type)) {
            $parts = $this->getSubnamespaceParts($type);
            array_pop($parts); // class name
            $parent = $this;
            foreach($parts as $part) {
                if (array_key_exists($part, $parent->children)) {
                    $parent = $parent->children[$part];
                } else {
                    return null;
                }
            }
            if (array_key_exists($type, $this->classes)) {
                return $this->classes[$type];
            }
        }
        return null;
    }

    public function getNamespaceCollection(string $namespace): NamespaceCollection
    {
        assert($this->containsNamespace($namespace), new GeneratorException("$this->namespace does not contain $namespace"));
        $parts = $this->getSubnamespaceParts($namespace);
        $parent = $this;
        foreach($parts as $part) {
            if (!array_key_exists($part, $parent->children)) {
                $parent->children[$part] = new NamespaceCollection($parent->namespace . "\\" . $part);
            }
            $parent = $parent->children[$part];
        }
        return $parent;
    }

    public function addClass(Writable $class)
    {
        assert($this->containsNamespace($class->getNamespace(), new GeneratorException("$this->namespace does not contain " . $class->getNamespace())));
        if ($class->getNamespace() === $this->namespace) {
            assert(!array_key_exists($class->getType(), $this->classes), new GeneratorException($class->getType() . " already exists in $this->namespace"));
            $this->classes[$class->getType()] = $class;
        } else {
            $this->getNamespaceCollection($class->getNamespace())->addClass($class);
        }
    }
}
