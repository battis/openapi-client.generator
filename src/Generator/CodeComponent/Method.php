<?php

namespace Battis\OpenAPI\Generator\CodeComponent;

class Method extends BaseComponent{
    
    public string $access = "public";
    
    public string $description;
    
    public string $name;

    /** @var array<string, array{
     *     type: string,
     *     description: string
     *  }> $parameters
     *  ```
     *  [
     *     'name' => [
     *       'type` => 'int',
     *       'description' => 'a value'
     *     ]
     *  ]
     *  ```
     */
    protected array $parameters;

    /** @var string[] $body */
    public array $body;
    
    public string $returnType;
        
    public function setParameter(string $name, string $type, string $description = "") {
        $this->parameters[$name] = ['type' => $type, 'description' => $description];
    }
        
    public function __toString()
    {
        $params =[];
        $doc = new PHPDoc($this->logger);
        $doc->addItem($this->description);
        foreach($this->parameters as $name => $meta) {
            $params[] = $meta['type'] . " \$$name";
            $doc->addItem(trim("@param " . $meta['type'] . " \$$name " . $meta['description']));
        }
        $doc->addItem("@return " . $this->returnType);
        $doc->addItem("@api");
        return $doc->asString(1) . PHP_EOL .
            "$this->access function $this->name(" . join(", ", $params) . ")" . PHP_EOL.
            "{".PHP_EOL.
            $this->body.PHP_EOL;
            "}".PHP_EOL;
    }    
}