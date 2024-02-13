<?php

namespace Battis\OpenAPI\Client\Endpoint;

use Battis\DataUtilities\Path;
use Battis\OpenAPI\Client\Mappable;
use GuzzleHttp\Client;
use League\OAuth2\Client\Provider\AbstractProvider;

abstract class BaseEndpoint extends Mappable
{
    protected static string $url = "";

    protected AbstractProvider $api;
    protected Client $client;

    protected static $EXPECTED_RESPONSE_MIMETYPE = 'application/json';

    public function __construct(AbstractProvider &$api)
    {
        parent::__construct();
        $this->api = $api;
        $this->client = new Client([
          "base_uri" => static::$url,
        ]);
    }

    protected function send(string $method, string $path = "", array $parameters = [], string $body = null): mixed
    {
        /*
         * TODO deal with refreshing tokens (need callback to store new refresh token)
         *   https://developer.blackbaud.com/skyapi/docs/in-depth-topics/api-request-throttling
         */
        usleep(100000);
        $request = $this->api->getAuthenticatedRequest(
            $method,
            Path::join(static::$url, $path) . "?" . http_build_query($parameters),
            $this->api,
            $body === null ? [] : ["body" => $body]
        );
        return $this->deserializeResponse(
            $this->client
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
