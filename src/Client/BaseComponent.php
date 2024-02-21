<?php

namespace Battis\OpenAPI\Client;

use Battis\OpenAPI\Client\Exceptions\ClientException;
use JsonSerializable;

/**
 * @api
 */
abstract class BaseComponent extends Mappable implements JsonSerializable
{
    /** @var string[] $fields */
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
     * @api
     */
    public function __construct(array $data)
    {
        parent::__construct();
        $this->data = $data;
        foreach(static::$fields as $property => $type) {
            if (strpos($type, "\\") !== false) {
                if (strpos($type, "[]") !== false) {
                    assert(is_array($this->data[$property]), new ClientException("`$property` declared as array ($type)"));
                    $type = preg_replace("/(.+)\\[\\]$/", "$1", $type);
                    for ($i = 0; $i < count($this->data[$property]); $i++) {
                        $this->data[$i] = new $type($this->data[$i]);
                    }
                } else {
                    $this->data[$property] = new $type($this->data[$property]);
                }
            }
        }
    }

    /**
     * @param string $name
     * @return mixed
     * @throws \Battis\OpenAPI\Client\Exceptions\ClientException if unknown property accessed
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
        throw new ClientException(
            "Unknown property `$name`",
        );
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
