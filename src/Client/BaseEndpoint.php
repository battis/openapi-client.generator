<?php

namespace Battis\OpenAPI\Client;

use Battis\OpenAPI\Client\Client as APIClient;
use Battis\OpenAPI\Client\Exceptions\ClientException;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request;
use JsonSerializable;

/**
 * @api
 */
abstract class BaseEndpoint extends Mappable
{
    protected static string $url = "";

    protected APIClient $api;
    protected HttpClient $http;

    protected static string $EXPECTED_RESPONSE_MIMETYPE = 'application/json';

    public function __construct(APIClient $api)
    {
        parent::__construct();
        $this->api = $api;
        $this->http = new HttpClient();
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
        assert($token !== null, new ClientException('No valid token available, must interactively authenticate'));

        if ($body instanceof JsonSerializable) {
            $body = json_encode($body) ?: null;
        }

        $request = new Request(
            $method,
            str_replace(array_keys($pathParameters), array_values($pathParameters), static::$url) . "?" . http_build_query($queryParameters),
            ['Authentication' => "Bearer $token"],
            $body
        );
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
}
