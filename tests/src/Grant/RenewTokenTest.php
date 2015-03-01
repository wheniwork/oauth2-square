<?php

namespace Wheniwork\OAuth2\Client\Test\Grant;

use Wheniwork\OAuth2\Client\Provider\Square;
use Wheniwork\OAuth2\Client\Grant\RenewToken;

use Mockery as m;

class RenewTokenTest extends \PHPUnit_Framework_TestCase
{
    protected $provider;

    protected function setUp()
    {
        $this->provider = new Square([
            'clientId' => 'mock_client_id',
            'clientSecret' => 'mock_secret',
            'redirectUri' => 'none',
        ]);
    }

    public function tearDown()
    {
        m::close();
        parent::tearDown();
    }

    public function testConvertToString()
    {
        $grant = new RenewToken();
        $this->assertEquals('renew_token', (string) $grant);
    }

    public function testGetAccessToken()
    {
        $expiration = time() + 60 * 60 * 24 * 30; // Square tokens expire after 30 days

        $response = m::mock('Guzzle\Http\Message\Response');
        $response->shouldReceive('getBody')->times(2)->andReturn(sprintf(
            '{"access_token": "mock_renewed_token", "expires_at": "%s", "merchant_id": 1}',
            date('c', $expiration) // ISO 8601
        ));

        $client = m::mock('Guzzle\Service\Client');
        $client->shouldReceive('post->setBody->send')->times(2)->andReturn($response);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('renew_token', ['access_token' => 'mock_access_token']);
        $this->assertInstanceOf('League\OAuth2\Client\Token\AccessToken', $token);

        // Repeat the test with an injected grant.
        $grant = new RenewToken($token);
        $token = $this->provider->getAccessToken($grant);
        $this->assertInstanceOf('League\OAuth2\Client\Token\AccessToken', $token);
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testInvalidRefreshToken()
    {
        $this->provider->getAccessToken('renew_token', ['not_access_token' => 'mock_access_token']);
    }
}
