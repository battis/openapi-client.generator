<?php

namespace Battis\OpenAPI\Client;

use League\OAuth2\Client\Token\AccessTokenInterface;

class TokenStorage
{
    /**
     * Retrieve access token from persistent storage
     *
     * @return ?AccessTokenInterface
     */
    public function getToken(): ?AccessTokenInterface
    {
        return null;
    }

    /**
     * Save an access token to persistent storage
     *
     * @param AccessTokenInterface $token
     *
     * @return bool `true` if the token was successfully saved, false otherwise
     */
    public function setToken(AccessTokenInterface $token): bool
    {
        return false;
    }
}
