<?php

namespace Wheniwork\OAuth2\Client\Test\Provider;

use Wheniwork\OAuth2\Client\Provider\Square;
use League\OAuth2\Client\Token\AccessToken;

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
        $this->assertNotNull($this->provider->state);
    }

    public function testUrlAccessToken()
    {
        $url = $this->provider->urlAccessToken();
        $uri = parse_url($url);

        $this->assertEquals('/oauth2/token', $uri['path']);
    }

    public function testUrlUserDetails()
    {
        $token = new AccessToken(['access_token' => 'fake']);

        $url = $this->provider->urlUserDetails($token);
        $uri = parse_url($url);

        $this->assertEquals('/v1/me', $uri['path']);
        $this->assertArrayNotHasKey('query', $uri);
    }

    public function testUserData()
    {
        $postResponse = m::mock('Guzzle\Http\Message\Response');
        $postResponse->shouldReceive('getBody')->times(1)->andReturn('{"access_token": "mock_access_token", "expires": 3600, "refresh_token": "mock_refresh_token", "id": 1}');

        $getResponse = m::mock('Guzzle\Http\Message\Response');
        $getResponse->shouldReceive('getBody')->times(4)->andReturn('{"id": 12345, "name": "mock_name", "email": "mock_email"}');

        $client = m::mock('Guzzle\Service\Client');
        $client->shouldReceive('setBaseUrl')->times(5);
        $client->shouldReceive('setDefaultOption')->times(4);
        $client->shouldReceive('post->send')->times(1)->andReturn($postResponse);
        $client->shouldReceive('get->send')->times(4)->andReturn($getResponse);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $user = $this->provider->getUserDetails($token);

        $this->assertInstanceOf('Wheniwork\OAuth2\Client\Provider\SquareMerchant', $user);

        $this->assertEquals(12345, $this->provider->getUserUid($token));
        $this->assertEquals('mock_name', $this->provider->getUserScreenName($token));
        $this->assertEquals('mock_name', $user->name);
        $this->assertEquals('mock_email', $this->provider->getUserEmail($token));
    }
}
