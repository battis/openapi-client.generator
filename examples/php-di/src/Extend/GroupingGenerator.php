<?php

namespace Example\Extend;

use Battis\DataUtilities\Path;
use Battis\OpenAPI\Generator;
use cebe\openapi\spec\OpenApi;

/**
 * We know that the OpenAPI specifications we're dealing with have titles that
 * we can use to organize our clients (e.g. "Foo Bar", "Foo Baz" so we'll
 * organize them as "Foo\Bar" and "Foo\Baz").
 */
class GroupingGenerator extends Generator
{
    protected function getNamespaceFromSpec(
        string $specPath,
        OpenApi $spec,
        string $baseNamespace
    ): string {
        return Path::join(
            '\\',
            array_merge([$baseNamespace], explode(' ', $spec->info->title))
        );
    }

    protected function getBasePathFromSpec(
        string $specPath,
        OpenApi $spec,
        string $basePath
    ): string {
        return Path::join(
            array_merge([$basePath], explode(' ', $spec->info->title))
        );
    }
}
