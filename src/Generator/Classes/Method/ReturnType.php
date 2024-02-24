<?php

namespace Battis\OpenAPI\Generator\Classes\Method;

use Battis\OpenAPI\Generator\Sanitize;
use Battis\PHPGenerator\Method\ReturnType as PHPReturnType;

class ReturnType extends PHPReturnType
{
    /**
     * @param string|\Battis\PHPGenerator\Type $type
     * @param ?string $description
     * @param int $flags
     */
    public function __construct($type = "void", ?string $description = null, int $flags = PHPReturnType::NONE)
    {
        parent::__construct($type, $description !== null ? Sanitize::getInstance()->stripHtml($description) : null, $flags);
    }
}
