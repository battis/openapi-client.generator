<?php

namespace Battis\OpenAPI\Client\Object;

use Battis\OpenAPI\Client\Mappable;
use JsonSerializable;

abstract class BaseObject extends Mappable implements JsonSerializable
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
    }

    /**
     * @param string $name
     * @return mixed
     * @throws \Battis\OpenAPI\Client\Object\ObjectException if unknown property accessed
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
        throw new ObjectException(
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
