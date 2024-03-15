<?php

namespace Battis\OpenAPI\Client;

use Battis\OpenAPI\Client\Exceptions\ClientException;
use JsonSerializable;

/**
 * @api
 */
abstract class BaseComponent extends Mappable implements JsonSerializable
{
    /** @var array<string, class-string|string> $fields */
    protected static array $fields = [];

    /**
     * JSON array of object data
     *
     * @var array<string, mixed> $data
     */
    protected array $data;

    /**
     * Construct from a JSON object response value from the SKY API
     *
     * @param array<string, mixed> $data
     *
     * @api
     */
    public function __construct(array $data)
    {
        $this->data = $data;
        foreach (static::$fields as $property => $type) {
            if (strpos($type, '\\') !== false) {
                /** @var class-string<\Battis\OpenAPI\Client\BaseComponent> $type */
                if (strpos($type, '[]') !== false) {
                    assert(
                        is_array($this->data[$property]),
                        new ClientException(
                            "`$property` declared as array ($type)"
                        )
                    );
                    /** @var class-string<\Battis\OpenAPI\Client\BaseComponent> $type */
                    $type = preg_replace("/(.+)\\[\\]$/", "$1", $type);
                    $this->data[$property] = array_map(
                        fn($elt) => new $type($elt),
                        $this->data[$property]
                    );
                } else {
                    $this->data[$property] = new $type($this->data[$property]);
                }
            }
        }
    }

    /**
     * @param string $name
     * @return mixed
     * @api
     */
    public function __get(string $name): mixed
    {
        if (in_array($name, static::$fields)) {
            if (array_key_exists($name, $this->data)) {
                return $this->data[$name];
            } else {
                return null;
            }
        }
        trigger_error(
            'Undefined property: ' . static::class . "::$name",
            E_USER_WARNING
        );
        return null;
    }

    /**
     * @return mixed
     * @api
     */
    public function jsonSerialize(): mixed
    {
        return $this->data;
    }
}
