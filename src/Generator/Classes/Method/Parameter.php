<?php

namespace Battis\OpenAPI\Generator\Classes\Method;

use Battis\OpenAPI\Generator\Sanitize;
use Battis\PHPGenerator\Method\Parameter as PHPParameter;

class Parameter extends PHPParameter
{
    /**
     * @param string $name
     * @param string|\Battis\PHPGenerator\Type $type
     * @param ?string $defaultValue
     * @param ?string $description
     * @param int $flags
     */
    public function __construct(
        string $name,
        $type,
        ?string $defaultValue = null,
        ?string $description = null,
        int $flags = PHPParameter::NONE
    ) {
        $sanitize = Sanitize::getInstance();
        parent::__construct(
            $sanitize->clean($name),
            $type,
            $defaultValue,
            $description !== null ? $sanitize->stripHtml($description) : null,
            $flags
        );
    }
}
