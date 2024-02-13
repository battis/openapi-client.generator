<?php

namespace Battis\OpenAPI\Client;

use Battis\OpenAPI\Client\Exceptions\ClientException;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * @api
 */
class Client
{
    // environment variables
    public const Bb_ACCESS_KEY = "BLACKBAUD_ACCESS_KEY";
    public const Bb_CLIENT_ID = "BLACKBAUD_CLIENT_ID";
    public const Bb_CLIENT_SECRET = "BLACKBAUD_CLIENT_SECRET";
    public const Bb_REDIRECT_URL = "BLACKBAUD_REDIRECT_URL";
    public const Bb_TOKEN = "BLACKBAUD_API_TOKEN";

    // keys
    public const OAuth2_STATE = "oauth2_state";
    public const Request_URI = "request_uri";

    // OAuth2 terms
    public const CODE = "code";
    public const STATE = "state";
    public const AUTHORIZATION_CODE = "authorization_code";
    public const REFRESH_TOKEN = "refresh_token";

    private AbstractProvider $api;
    private CacheInterface $cache;

    public function __construct(AbstractProvider $api, CacheInterface $cache)
    {
        session_start();
        $this->api = $api;
        $this->cache = $cache;
    }

    public function isReady(): bool
    {
        return !!self::getToken(false);
    }

    /**
     * @param boolean $interactive
     *
     * @return AccessTokenInterface|null
     */
    public function getToken($interactive = true)
    {
        /** @var array $cachedToken */
        $cachedToken = $this->cache->get(self::Bb_TOKEN, true);
        $token = $cachedToken ? new AccessToken($cachedToken) : null;

        // acquire an API access token
        if (empty($token)) {
            if ($interactive) {
                // interactively acquire a new access token
                if (false === isset($_GET[self::CODE])) {
                    $authorizationUrl = $this->api->getAuthorizationUrl();
                    $_SESSION[self::OAuth2_STATE] = $this->api->getState();
                    $this->cache->set(self::Request_URI, $_SERVER["REQUEST_URI"] ?? null);
                    header("Location: $authorizationUrl");
                    exit();
                } elseif (
                    !isset($_GET[self::STATE]) ||
                    (isset($_SESSION[self::OAuth2_STATE]) &&
                      $_GET[self::STATE] !== $_SESSION[self::OAuth2_STATE])
                ) {
                    if (isset($_SESSION[self::OAuth2_STATE])) {
                        unset($_SESSION[self::OAuth2_STATE]);
                    }

                    throw new ClientException(var_export(["error" => "invalid state"], true));
                } else {
                    $token = $this->api->getAccessToken(self::AUTHORIZATION_CODE, [
                      self::CODE => $_GET[self::CODE],
                    ]);
                    $this->cache->set(self::Bb_TOKEN, $token);
                }
            } else {
                return null;
            }
        } elseif ($token->hasExpired()) {
            // use refresh token to get new Bb access token
            $newToken = $this->api->getAccessToken(self::REFRESH_TOKEN, [
              self::REFRESH_TOKEN => $token->getRefreshToken(),
            ]);
            $this->cache->set(self::Bb_TOKEN, $newToken);
            $token = $newToken;
        }

        return $token;
    }

    public function handleRedirect(): void
    {
        self::getToken();
        /** @var string $uri */
        $uri = $this->cache->get(self::Request_URI) ?? "/";
        header("Location: $uri");
        exit();
    }
}
