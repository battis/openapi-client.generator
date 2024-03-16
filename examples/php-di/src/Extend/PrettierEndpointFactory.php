<?php

namespace Example\Extend;

use Battis\OpenAPI\Generator\Classes\EndpointFactory;
use cebe\openapi\spec\Operation;
use Battis\PHPGenerator\Type;

/**
 * We know that, consistently, GET endpoints that return components whose
 * names end in "Collection" would be better named as "list" or "filter"
 */
class PrettierEndpointFactory extends EndpointFactory
{
    protected function getMethodNameForOperation(
        string $operation,
        Operation $operationDescription,
        string $url,
        array $parameters,
        Type $returnType
    ): string {
        $base = parent::getMethodNameForOperation(
            $operation,
            $operationDescription,
            $url,
            $parameters,
            $returnType
        );
        if (
            $operation === 'get' &&
            preg_match("/Collection$/", $returnType->as(Type::SHORT))
        ) {
            if (count($parameters['path']) === 0) {
                return 'list';
            } else {
                return preg_replace("/^$operation/", 'search', $base);
            }
        }
        return $base;
    }
}
