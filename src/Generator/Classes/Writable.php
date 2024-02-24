<?php

namespace Battis\OpenAPI\Generator\Classes;

use Battis\OpenAPI\Generator\Sanitize;
use Battis\PHPGenerator\PHPClass;

abstract class Writable extends PHPClass
{
    /**
     * Relative path to class from `BaseMapper->getBasePath()`
     *
     * @var string $path
     */
    protected string $path;

    /**
     * @param string $path Relative path to class from `BaseMapper->getBasePath()`
     * @param string $namespace
     * @param null|string|\Battis\PHPGenerator\Type $baseType
     * @param ?string $description
     */
    public function __construct(
        string $path,
        string $namespace,
        $baseType = null,
        ?string $description = null
    ) {
        $this->path = $path;
        $sanitize = Sanitize::getInstance();
        parent::__construct(
            $namespace,
            $sanitize->clean(basename($path)),
            $baseType,
            $description !== null ?
                $sanitize->stripHtml($description) :
                null
        );
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
