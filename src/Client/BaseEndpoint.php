<?php

namespace Battis\OpenAPI\Client;

use Battis\OpenAPI\Client\Client as APIClient;
use Battis\OpenAPI\Client\Exceptions\ClientException;
use Battis\OpenAPI\Generator\Classes\Endpoint;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request;
use JsonSerializable;

/**
 * @api
 */
abstract class BaseEndpoint extends Mappable
{
    protected string $url = "";

    /**
     * @var array<string, class-string> $endpoints
     */
    protected array $endpoints = [];

    protected APIClient $api;
    protected ?HttpClient $http = null;

    protected static string $EXPECTED_RESPONSE_MIMETYPE = 'application/json';

    public function __construct(APIClient $api)
    {
        $this->api = $api;
    }

    /**
     * @param string $method
     * @param array<string,string> $urlParameters
     * @param array<string, string> $parameters
     * @param string|JsonSerializable|null $body
     *
     * @return mixed  description
     */
    protected function send(
        string $method,
        array $pathParameters = [],
        array $queryParameters = [],
        mixed $body = null
    ): mixed {
        /*
         * TODO deal with refreshing tokens (need callback to store new refresh token)
         *   https://developer.blackbaud.com/skyapi/docs/in-depth-topics/api-request-throttling
         */
        usleep(100000);

        $token = $this->api->getToken();
        assert(
            $token !== null,
            new ClientException('No valid token available, must interactively authenticate')
        );

        if ($body instanceof JsonSerializable) {
            $body = json_encode($body) ?: null;
        }

        $request = new Request(
            $method,
            str_replace(
                array_keys($pathParameters),
                array_values($pathParameters),
                $this->url
            ) . "?" . http_build_query($queryParameters),
            ['Authentication' => "Bearer $token"],
            $body
        );

        if ($this->http === null) {
            $this->http = new HttpClient();
        }

        return $this->decodeResponse(
            $this->http
            ->send($request)
            ->getBody()
            ->getContents()
        );
    }

    protected function decodeResponse(string $response): mixed
    {
        return json_decode($response, true);
    }

    public function __get($name): ?Endpoint
    {
        if (array_key_exists($name, static::$endpoints)) {
            $instance = "_$name";
            if (static::$$instance === null) {
                $class = $this->$endpoints[$name];
                $this->$$instance = new $class($this->api);
            }
            return $this->$$instance;
        }
        trigger_error(
            "Undefined property: " . static::class . "::$name",
            E_USER_WARNING
        );
    }
}
