<?php

namespace Battis\OpenAPI\Generator\Classes;

use Battis\OpenAPI\Generator\Sanitize;
use Battis\PHPGenerator\Access;
use Battis\PHPGenerator\Property as PHPProperty;

class Property extends PHPProperty
{
    /**
     * @param \Battis\PHPGenerator\Access $access
     * @param string $name
     * @param string|\Battis\PHPGenerator\Type $type
     * @param ?string $description
     * @param ?string $defaultValue
     */
    public function __construct(
        Access $access,
        string $name,
        $type,
        ?string $description = null,
        ?string $defaultValue = null,
        int $flags = PHPProperty::NONE
    ) {
        $sanitize = Sanitize::getInstance();
        parent::__construct(
            $access,
            $sanitize->clean($name),
            $type,
            $description !== null ? $sanitize->stripHtml($description) : null,
            $defaultValue,
            $flags
        );
    }
}
