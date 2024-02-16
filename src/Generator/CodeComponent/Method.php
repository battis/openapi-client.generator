<?php

namespace Battis\OpenAPI\Generator\CodeComponent;

use Battis\OpenAPI\Generator\CodeComponent\Method\Parameter;
use Battis\OpenAPI\Generator\TypeMap;

class Method extends BaseComponent
{
    protected string $access = "public";

    protected string $description;

    protected string $name;

    /**
     * @var Parameter[] $parameters;
     */
    protected array $parameters;

    /** @var string $body */
    protected string $body;

    protected string $returnType;

    public function addParameter(Parameter $parameter): void
    {
        $this->parameters[] = $parameter;
    }

    /**
     * @param Parameter[] $parameters
     */
    public static function public(string $name, string $returnType, string $body, ?string $description =  null, array $parameters = []): Method
    {
        $method = new Method();
        $method->name = $name;
        $method->access = 'public';
        $method->returnType = $returnType;
        $method->body = $body;
        $method->description = $description;
        $method->parameters = $parameters;
        return $method;
    }

    public function asImplementation(): string
    {
        $params = [];
        $doc = new PHPDoc($this->logger);
        $doc->addItem($this->description);
        foreach($this->parameters as $param) {
            $params[] = $param->asDeclaration();
            $doc->addItem($param->asPHPDocParam());
        }
        $doc->addItem("@return " . TypeMap::parseType($this->returnType, true, true));
        $doc->addItem("@api");
        return $doc->asString(1) .
            "$this->access function $this->name(" . join(", ", $params) . ")" . PHP_EOL .
            "{" . PHP_EOL .
            $this->body . PHP_EOL .
        "}" . PHP_EOL;
    }
}
