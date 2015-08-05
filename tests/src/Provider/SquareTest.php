<?php

namespace Wheniwork\OAuth2\Client\Test\Provider;

use GuzzleHttp\Exception\BadResponseException;
use League\OAuth2\Client\Token\AccessToken;
use Wheniwork\OAuth2\Client\Provider\Square;

use Mockery as m;

class SquareTest extends \PHPUnit_Framework_TestCase
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

    public function testAuthorizationUrl()
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);
        parse_str($uri['query'], $query);

        $this->assertArrayHasKey('client_id', $query);
        $this->assertArrayHasKey('redirect_uri', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertArrayHasKey('scope', $query);
        $this->assertArrayHasKey('response_type', $query);
        $this->assertNotNull($this->provider->getState());

        $this->assertEquals('/oauth2/authorize', $uri['path']);
    }

    public function testUrlAccessToken()
    {
        $url = $this->provider->getBaseAccessTokenUrl([]);
        $uri = parse_url($url);

        $this->assertEquals('/oauth2/token', $uri['path']);
    }

    public function testUrlUserDetails()
    {
        $token = new AccessToken(['access_token' => 'fake']);

        $url = $this->provider->getResourceOwnerDetailsUrl($token);
        $uri = parse_url($url);

        $this->assertEquals('/v1/me', $uri['path']);
        $this->assertArrayNotHasKey('query', $uri);
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
                '{"access_token": "mock_access_token", "expires_at": "%s", "merchant_id": 1}',
                date('c', $expiration) // ISO 8601
            ));

        $client = m::mock('GuzzleHttp\Client[send]');

        $client->shouldReceive('send')
            ->with(m::type('GuzzleHttp\Psr7\Request'))
            ->andReturn($response);

        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);

        $this->assertEquals('mock_access_token', $token->getToken());
        $this->assertLessThanOrEqual($expiration, $token->getExpires());
        $this->assertGreaterThanOrEqual(time(), $token->getExpires());
        $this->assertEquals('1', $token->getResourceOwnerId());
    }

    /**
     * @expectedException League\OAuth2\Client\Provider\Exception\IdentityProviderException
     */
    public function testGetAccessTokenFailure()
    {
        $response = m::mock('GuzzleHttp\Psr7\Response');
        $response->shouldReceive('getHeader')
            ->with('content-type')
            ->andReturn(['application/json']);

        $response->shouldReceive('getBody')
            ->andReturn(
                '{"type": "internal_server_error", "message": "Something went wrong"}'
            );

        $response->shouldReceive('getStatusCode')
            ->andReturn(500);

        $exception = m::mock('GuzzleHttp\Exception\BadResponseException');
        $exception->shouldReceive('getResponse')
            ->andReturn($response);

        $client = m::mock('GuzzleHttp\Client[send]');
        $client->shouldReceive('send')
            ->andThrow($exception);

        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }


    public function testUserData()
    {
        $response = m::mock('GuzzleHttp\Psr7\Response');
        $response->shouldReceive('getHeader')
            ->with('content-type')
            ->andReturn(['application/json']);

        $response->shouldReceive('getBody')
            ->andReturn(
                '{"id": 12345, "name": "mock_name", "email": "mock_email"}'
            );

        $client = m::mock('GuzzleHttp\Client[send]');

        $client->shouldReceive('send')
            ->with(m::on(function ($request) {
                $header = $request->getHeader('Authorization');
                return $header && $header[0] === 'Bearer mock_token';
            }))
            ->andReturn($response);

        $this->provider->setHttpClient($client);

        $token = new AccessToken([
            'access_token' => 'mock_token',
        ]);

        $owner = $this->provider->getResourceOwner($token);

        $this->assertInstanceOf('Wheniwork\OAuth2\Client\Provider\SquareMerchant', $owner);

        $this->assertEquals(12345, $owner->getId());
        $this->assertEquals('mock_name', $owner->getName());
        $this->assertEquals('mock_email', $owner->getEmail());
    }

    /**
     * @expectedException League\OAuth2\Client\Grant\Exception\InvalidGrantException
     */
    public function testRefreshAccessToken()
    {
        $token = $this->provider->getAccessToken('refresh_token', ['refresh_token' => 'mock_access_token']);
    }
}
