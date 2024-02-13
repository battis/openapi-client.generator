<?php

namespace Battis\OpenAPI\Client;

use Battis\OpenAPI\Client\Client as APIClient;
use Battis\OpenAPI\Client\Exceptions\ClientException;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request;

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
     * @param string $body
     *
     * @return mixed  description
     */
    protected function send(
        string $method,
        array $urlParameters = [],
        array $parameters = [],
        string $body = null
    ): mixed {
        usleep(100000);

        $token = $this->api->getToken();
        assert($token !== null, new ClientException('No valid token available, must interactively authenticate'));
        $options = [
            'headers' => [
                'Authentication' => "Bearer $token",
            ],
        ];
        if ($body !== null) {
            $options['body'] = $body;
        }

        $request = new Request(
            $method,
            str_replace(array_keys($urlParameters), array_values($urlParameters), static::$url) . "?" . http_build_query($parameters),
            $options
        );
        return $this->deserializeResponse(
            $this->http
            ->send($request)
            ->getBody()
            ->getContents()
        );
    }

    protected function deserializeResponse(string $response): mixed
    {
        return json_decode($response, true);
    }
}
