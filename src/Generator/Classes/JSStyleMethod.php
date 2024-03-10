<?php

namespace Battis\OpenAPI\Generator\Classes;

use Battis\OpenAPI\Generator\Sanitize;
use Battis\PHPGenerator\Access;
use Battis\PHPGenerator\JSStyleMethod as PHPJSStyleMethod;

class JSStyleMethod extends PHPJSStyleMethod
{
    /**
     * @param \Battis\PHPGenerator\Access $access
     * @param string $name
     * @param \Battis\PHPGenerator\Method\Parameter[] $parameters
     * @param string|\Battis\PHPGenerator\Type|\Battis\PHPGenerator\Method\ReturnType $returnType
     * @param ?string $body
     * @param ?string $description
     * @param string[]|\Battis\PHPGenerator\Type[]|\Battis\PHPGenerator\Method\ReturnType[] $throws
     * @param int $flags
     */
    public function __construct(
        Access $access,
        string $name,
        array $parameters = [],
        $returnType = "void",
        ?string $body = null,
        ?string $description = null,
        array $throws = [],
        int $flags = PHPJSStyleMethod::NONE
    ) {
        $sanitize = Sanitize::getInstance();
        parent::__construct(
            $access,
            $sanitize->clean($name),
            $parameters,
            $returnType,
            $body,
            $description !== null ? $sanitize->stripHtml($description) : null,
            $throws,
            $flags
        );
    }
}
