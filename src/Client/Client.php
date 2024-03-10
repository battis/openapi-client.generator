<?php

namespace Battis\OpenAPI\Client;

use Battis\OpenAPI\Client\Exceptions\ClientException;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;

/**
 * @api
 */
class Client
{
    // session keys
    public const CODE = "code";
    public const STATE = "state";
    public const AUTHORIZATION_CODE = "authorization_code";
    public const REFRESH_TOKEN = "refresh_token";
    public const REQUEST_URI = 'request_uri';

    private AbstractProvider $api;
    private TokenStorage $storage;

    public function __construct(AbstractProvider $api, TokenStorage $storage)
    {
        session_start();
        $this->api = $api;
        $this->storage = $storage;
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
        $cachedToken = $this->storage->getToken();
        $token = $cachedToken ? new AccessToken($cachedToken) : null;

        // acquire an API access token
        if (empty($token)) {
            if ($interactive) {
                // interactively acquire a new access token
                if (false === isset($_GET[self::CODE])) {
                    $authorizationUrl = $this->api->getAuthorizationUrl();
                    $_SESSION[self::STATE] = $this->api->getState();
                    // TODO wipe existing token?
                    $_SESSION[self::REQUEST_URI] = $_SERVER["REQUEST_URI"] ?? null;
                    header("Location: $authorizationUrl");
                    exit();
                } elseif (
                    !isset($_GET[self::STATE]) ||
                    (isset($_SESSION[self::STATE]) &&
                      $_GET[self::STATE] !== $_SESSION[self::STATE])
                ) {
                    if (isset($_SESSION[self::STATE])) {
                        unset($_SESSION[self::STATE]);
                    }

                    throw new ClientException(
                        var_export(["error" => "invalid state"], true)
                    );
                } else {
                    $token = $this->api->getAccessToken(self::AUTHORIZATION_CODE, [
                      self::CODE => $_GET[self::CODE],
                    ]);
                    $this->storage->setToken($token);
                }
            } else {
                return null;
            }
        } elseif ($token->hasExpired()) {
            // use refresh token to get new Bb access token
            $newToken = $this->api->getAccessToken(self::REFRESH_TOKEN, [
              self::REFRESH_TOKEN => $token->getRefreshToken(),
            ]);
            // FIXME need to handle _not_ being able to refresh!
            $this->storage->setToken($newToken);
            $token = $newToken;
        }

        return $token;
    }

    public function handleRedirect(): void
    {
        self::getToken();
        /** @var string $uri */
        $uri = $_SESSION[self::REQUEST_URI] ?? "/";
        header("Location: $uri");
        exit();
    }
}
