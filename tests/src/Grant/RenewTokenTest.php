<?php

namespace Wheniwork\OAuth2\Client\Test\Grant;

use Wheniwork\OAuth2\Client\Provider\Square;
use Wheniwork\OAuth2\Client\Grant\RenewToken;

use League\OAuth2\Client\Token\AccessToken;

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

        $response = m::mock('GuzzleHttp\Psr7\Response');

        $response->shouldReceive('getHeader')
            ->with('content-type')
            ->andReturn(['application/json']);

        $response->shouldReceive('getBody')
            ->andReturn(sprintf(
                '{"access_token": "mock_renewed_token", "expires_at": "%s", "merchant_id": 1}',
                date('c', $expiration) // ISO 8601
            ));

        $client = m::mock('GuzzleHttp\Client[send]');

        $client->shouldReceive('send')
            ->with(m::type('GuzzleHttp\Psr7\Request'))
            ->andReturn($response);

        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('renew_token', ['access_token' => 'mock_token']);

        $this->assertInstanceOf('League\OAuth2\Client\Token\AccessToken', $token);
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testInvalidParameters()
    {
        $this->provider->getAccessToken('renew_token', ['not_access_token' => 'mock_access_token']);
    }
}
