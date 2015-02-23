<?php

namespace Shadowhand\OAuth2\Client\Provider;

use League\OAuth2\Client\Exception\IDPException;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;

class Square extends AbstractProvider
{
    /**
     * Enable debugging by connecting to the Square staging server.
     *
     * @var boolean
     */
    public $debug = false;

    public $uidKey = 'id';

    public $scopeSeparator = ' ';

    /**
     * Get a Square connect URL, depending on path.
     *
     * @param  string $path
     * @return string
     */
    protected function getConnectUrl($path)
    {
        $staging = $this->debug ? 'staging' : '';
        return "https://connect.squareup{$staging}.com/{$path}";
    }

    public function urlAuthorize()
    {
        return $this->getConnectUrl('oauth2/authorize');
    }

    public function urlAccessToken()
    {
        return $this->getConnectUrl('oauth2/token');
    }

    public function urlUserDetails(AccessToken $token)
    {
        return $this->getConnectUrl('v1/me');
    }

    public function userDetails($response, AccessToken $token)
    {
        $user = new SquareMerchant((array) $response);
        return $user;
    }

    protected function fetchUserDetails(AccessToken $token)
    {
        $this->headers['Authorization'] = 'Bearer ' . $token->accessToken;
        $this->headers['Accept']        = 'application/json';

        return parent::fetchUserDetails($token);
    }
}
