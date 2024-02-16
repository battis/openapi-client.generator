<?php

namespace Battis\OpenAPI\Generator\CodeComponent;

use Battis\OpenAPI\Generator\TypeMap;

class PHPClass extends BaseComponent
{    
    protected string $namespace = "";

    protected ?string $description = "";

    protected string $name = "";

    protected string $baseType = "";

    /**
     * @var string[] $uses
     */
    protected array $uses = [];

    /**
     * @var Property[] $properties
     */
    protected array $properties = [];

    /**
     * @var Method[]
     */
    protected array $methods = [];

    public function getName(): string
    {
        return $this->name;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function addProperty(Property $property): void
    {
        $this->properties[] = $property;
    }

    public function addMethod(Method $method): void
    {
        $this->methods[] = $method;
    }
    
    public function addUses(string $type): void
        {
            $this->uses[] = $type;
        }

    public function __toString()
    {
        $this->addUses($this->baseType);
        sort($this->uses);
        $this->uses = array_unique($this->uses);
        
        $classDoc = new PHPDoc($this->logger);
        if ($this->description !== null) {
            $classDoc->addItem($this->description);
        }
        $properties = [];
        foreach($this->properties as $prop) {
            if ($prop->isDocumentationOnly()) {
                $classDoc->addItem($prop->asPHPDocProperty());
            } else {
                $properties[] = $prop->asDeclaration();
            }
        }
        $classDoc->addItem("@api");

        return "<?php" . PHP_EOL . PHP_EOL .
        "namespace " . $this->namespace . ";" . PHP_EOL . PHP_EOL .
        (empty($this->uses) ? "" : join(PHP_EOL, array_map(fn($t) => "use $t;", $this->uses)) . PHP_EOL) .
        $classDoc->asString(0) .
        "class $this->name extends " . TypeMap::parseType($this->baseType, false) . PHP_EOL .
        "{" . PHP_EOL .
        (empty($properties) ? "" : join(PHP_EOL, $properties) ).
        (empty($this->methods) ? "" : PHP_EOL . join(PHP_EOL, array_map(fn($m) => $m->asImplementation(), $this->methods))) .
        "}" . PHP_EOL;
    }
}
