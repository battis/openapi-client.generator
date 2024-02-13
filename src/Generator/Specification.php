<?php

namespace Battis\OpenAPI\Generator;

use Battis\DataUtilities\Path;
use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use Battis\OpenAPI\Exceptions\ConfigurationException;

use function Safe\yaml_parse;

class Specification
{
    private function __construct() {}

    /**
     * Convenience method to read a variety of formats of OpenAPI spec
     *
     * @param string $specification A descriptor of the specification. Either
     *     the path or URL of a JSON or YAML OpenAPI specification file (either
     *     absolute or relative to the current working directory), or a string
     *     literal  containing either a JSON or YAML OpenAPI specification.
     *     If `$specification` is a path or URL, `$resolveReferences` defaults
     *     to `true`.
     *
     * @return \cebe\openapi\spec\OpenApi The OpenApi object instance.
     *
     * @throws \Battis\OpenAPI\Exceptions\ConfigurationException if the $specification cannot be identified
     */
    public static function from(
        string $specification
    ): OpenApi {
        if (preg_match("/\\.json$/i", $specification)) {
            $spec = Reader::readFromJsonFile(
                Path::canonicalize($specification, getcwd()),
                OpenApi::class,
                false,
            );
        } elseif (preg_match("/\\.ya?ml$/i", $specification)) {
            $spec = Reader::readFromYamlFile(
                Path::canonicalize($specification, getcwd()),
                OpenApi::class,
                false,
            );
        } elseif (
            json_decode($specification) &&
            json_last_error() === JSON_ERROR_NONE
        ) {
            $spec = Reader::readFromJson(
                $specification,
                OpenApi::class
            );
        } elseif (yaml_parse($specification)) {
            $spec = Reader::readFromYaml(
                $specification,
                OpenApi::class
            );
        } else {
            throw new ConfigurationException(
                "\$specification must be a path to a valid OpenAPI JSON or YAML file, or a literal OpenAPI JSON or YAML string",
            );
        }

        return $spec;
    }
}
