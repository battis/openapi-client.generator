<?php

namespace Battis\OpenAPI\Generator\CodeComponent;

use Battis\OpenAPI\Generator\CodeComponent\Method\Parameter;
use Battis\OpenAPI\Generator\TypeMap;

class Method extends BaseComponent
{
    /**
     * @var 'public'|'protected'|'private' $access
     */
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

    public function getName(): string
    {
        return $this->name;
    }

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
        $doc = new PHPDoc();
        $doc->addItem($this->description);
        foreach($this->parameters as $param) {
            $params[] = $param->asDeclaration();
            $doc->addItem($param->asPHPDocParam());
        }
        // TODO order parameters with required first
        $doc->addItem("@return " . TypeMap::parseType($this->returnType, true, true));
        $doc->addItem("@api");
        return $doc->asString(1) .
            "$this->access function $this->name(" . join(", ", $params) . ")" . PHP_EOL .
            "{" . PHP_EOL .
            $this->body . PHP_EOL .
        "}" . PHP_EOL;
    }

    public function asJavascriptStyleImplementation(): string
    {
        $doc = new PHPDoc();
        $doc->addItem($this->description);
        $optional = true;
        if (!empty($this->parameters)) {
            $parameters = [];
            $parametersDoc = [];
            foreach($this->parameters as $parameter) {
                $parameters[] = $parameter->getName() . ($parameter->isOptional() ? "?" : "") . ": " . $parameter->getType();
                $parametersDoc[] = $parameter->getName() . ": " . ($parameter->getDescription() ?? $parameter->getType());
                $optional = $optional && $parameter->isOptional();
            }
            $doc->addItem("@param array{" . join(", ", $parameters) . "} \$params An associative array" . PHP_EOL . "    - " . join(PHP_EOL . "    - ", $parametersDoc));
        }
        $doc->addItem("@return " . TypeMap::parseType($this->returnType, true, true));
        $doc->addItem('@api');
        $body = str_replace("\$params[\"this\"]", "\$this", preg_replace("/\\$([a-z0-9_]+)/i", "\$params[\"$1\"]", $this->body));
        return $doc->asString(1) .
            "$this->access function $this->name(" . (empty($this->parameters) ? "" : "array \$params" . ($optional ? " = []" : "")) . ")" . PHP_EOL .
            "{" . PHP_EOL .
            $body . PHP_EOL .
        "}" . PHP_EOL;
    }
}
