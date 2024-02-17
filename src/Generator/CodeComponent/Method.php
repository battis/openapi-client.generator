<?php

namespace Battis\OpenAPI\Generator\CodeComponent;

use Battis\OpenAPI\Generator\CodeComponent\Method\Parameter;
use Battis\OpenAPI\Generator\CodeComponent\Method\ReturnType;

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

    protected ReturnType $returnType;
    
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
    public static function public(string $name, ReturnType $returnType, string $body, ?string $description =  null, array $parameters = []): Method
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
        $doc->addItem($this->returnType->asPHPDocReturn());
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
        $parameters = [];
        $params = "";
        if (!empty($this->parameters)) {
            $parametersDoc = [];
            $requestBody = null;
            $params = [];
            foreach($this->parameters as $parameter) {
                if ($parameter->getName() === 'requestBody') {
                    $requestBody = $parameter;
                } else {
                    $parameters[] = $parameter->getName() . ($parameter->isOptional() ? "?" : "") . ": " . $parameter->getType();
                    $parametersDoc[] = $parameter->getName() . ": " . ($parameter->getDescription() ?? $parameter->getType());
                    $optional = $optional && $parameter->isOptional();
                }
            }
            if (!empty($parameters)) {
                $doc->addItem("@param array{" . join(", ", $parameters) . "} \$params An associative array" . PHP_EOL . "    - " . join(PHP_EOL . "    - ", $parametersDoc));
                $params[] = "array \$params" . ($optional ? " = []" : "");
            }
            if ($requestBody !== null) {
                /** @var Parameter $requestBody */
                $doc->addItem($requestBody->asPHPDocParam());
                $params[] = $requestBody->asDeclaration();
            }
            $params = empty($params) ? "" : join(", ", $params);
        }
        $doc->addItem($this->returnType->asPHPDocReturn());
        $doc->addItem('@api');
        $body = str_replace(["\$params[\"this\"]", "\$params[\"requestBody\"]"], ["\$this","\$requestBody"], preg_replace("/\\$([a-z0-9_]+)/i", "\$params[\"$1\"]", $this->body));
        return $doc->asString(1) .
            "$this->access function $this->name($params)" . PHP_EOL .
            "{" . PHP_EOL .
            $body . PHP_EOL .
        "}" . PHP_EOL;
    }
}
