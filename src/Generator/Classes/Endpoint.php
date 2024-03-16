<?php

namespace Battis\OpenAPI\Generator\Classes;

use Battis\OpenAPI\Generator\Exceptions\GeneratorException;
use Battis\PHPGenerator\Access;
use Battis\PHPGenerator\PHPClass;
use Battis\PHPGenerator\Property;
use Psr\Log\LoggerInterface;

class Endpoint extends Writable
{
    private LoggerInterface $logger;

    /**
     * @param string $path Relative path to class from `BaseMapper->getBasePath()`
     * @param string $namespace
     * @param null|string|\Battis\PHPGenerator\Type $baseType
     * @param ?string $description
     */
    public function __construct(
        LoggerInterface $logger,
        string $path,
        string $namespace,
        $baseType = null,
        ?string $description = null
    ) {
        parent::__construct($path, $namespace, $baseType, $description);
        $this->logger = $logger;
    }

    public function mergeWith(PHPClass $other): void
    {
        // merge $url properties to longer URL that includes shorter URL
        $thisUrlProps = array_filter(
            $this->properties,
            fn(Property $prop) => $prop->getName() === 'url'
        );
        $thisUrlProp = $thisUrlProps[0] ?? null;

        $otherUrlProps = array_filter(
            $other->properties,
            fn(Property $prop) => $prop->getName() === 'url'
        );
        $otherUrlProp = $otherUrlProps[0] ?? null;

        if ($thisUrlProp && $otherUrlProp) {
            $base = $thisUrlProp->getDefaultValue();
            assert(
                $base !== null,
                new GeneratorException(
                    '`$url` property should be defined with default value'
                )
            );
            $base = substr($base, 1, strlen($base) - 2);
            $extension = $otherUrlProp->getDefaultValue();
            assert(
                $extension !== null,
                new GeneratorException(
                    '`$url` property should be defined with default value'
                )
            );
            $extension = substr($extension, 1, strlen($extension) - 2);
            if ($base !== $extension) {
                if (strlen($base) > strlen($extension)) {
                    $temp = $base;
                    $base = $extension;
                    $extension = $temp;
                }
                $this->logger->warning(
                    "Merging $base and $extension into one endpoint"
                );

                $this->removeProperty($thisUrlProp);
                $other->removeProperty($otherUrlProp);
                $this->addProperty(
                    new Property(
                        Access::Protected,
                        'url',
                        'string',
                        null,
                        "\"$extension\""
                    )
                );
            } else {
                $other->removeProperty($otherUrlProp);
            }
        }

        parent::mergeWith($other);
    }
}
