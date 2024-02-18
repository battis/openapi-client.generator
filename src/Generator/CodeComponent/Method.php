<?php

namespace Battis\OpenAPI\Generator\CodeComponent;

use Battis\Loggable\Loggable;
use Battis\OpenAPI\Generator\CodeComponent\Method\Parameter;
use Battis\OpenAPI\Generator\CodeComponent\Method\ReturnType;

class Method extends BaseComponent
{
    /**
     * @var 'public'|'protected'|'private' $access
     */
    protected string $access = "public";

    protected bool $static = false;

    protected string $description;

    protected string $name;

    /**
     * @var Parameter[] $parameters;
     */
    protected array $parameters;

    /** @var string $body */
    protected string $body;

    protected ReturnType $returnType;

    /**
     * @var ReturnType[] $throws
     */
    protected array $throws = [];

    public function getName(): string
    {
        return $this->name;
    }

    public function getReturnType(): ReturnType
    {
        return $this->returnType;
    }

    /**
     * @return Parameter[]
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function addParameter(Parameter $parameter): void
    {
        $this->parameters[] = $parameter;
    }

    /**
     * @param Parameter[] $parameters
     */
    public static function public(string $name, ReturnType $returnType, string $body, ?string $description =  null, array $parameters = [], array $throws = []): Method
    {
        $method = new Method();
        $method->name = $name;
        $method->access = 'public';
        $method->returnType = $returnType;
        $method->body = $body;
        $method->description = $description;
        $method->parameters = $parameters;
        $method->throws = $throws;
        return $method;
    }

    public static function publicStatic(string $name, ReturnType $returnType, string $body, ?string $description = null, array $parameters = [], array $throws = []): Method
    {
        $method = self::public($name, $returnType, $body, $description, $parameters, $throws);
        $method->static = true;
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
        foreach($this->throws as $throw) {
            $doc->addItem($throw->asPHPDocThrows());
        }
        $doc->addItem("@api");
        return $doc->asString(1) .
            "$this->access " . ($this->static ? "static " : "") . "function $this->name(" . join(", ", $params) . ")" . PHP_EOL .
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
        $body = $this->body;
        if (!empty($this->parameters)) {
            $parametersDoc = [];
            $requestBody = null;
            $params = [];
            $paramNames = [];
            foreach($this->parameters as $parameter) {
                if ($parameter->getName() === 'requestBody') {
                    $requestBody = $parameter;
                } else {
                    $parameters[] = $parameter->getName() . ($parameter->isOptional() ? "?" : "") . ": " . $parameter->getType();
                    $parametersDoc[] = $parameter->getName() . ": " . ($parameter->getDescription() ?? $parameter->getType());
                    $optional = $optional && $parameter->isOptional();
                    $paramNames[] = $parameter->getName();
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

            usort($paramNames, fn($a, $b) => strlen($b) - strlen($a));
            foreach($paramNames as $p) {
                $body = str_replace("\$$p", "\$params[\"$p\"]", $body);
            }

        }
        $doc->addItem($this->returnType->asPHPDocReturn());
        foreach($this->throws as $throw) {
            $doc->addItem($throw->asPHPDocThrows());
        }
        $doc->addItem('@api');
        return $doc->asString(1) .
            "$this->access " . ($this->static ? "static " : "") . "function $this->name($params)" . PHP_EOL .
            "{" . PHP_EOL .
            $body . PHP_EOL .
        "}" . PHP_EOL;
    }
}
