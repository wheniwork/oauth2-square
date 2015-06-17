<?php

namespace Wheniwork\OAuth2\Client\Test\Provider;

use Guzzle\Http\Exception\BadResponseException;
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

    public function testGetAccessToken()
    {
        $expiration = time() + 60 * 60 * 24 * 30; // Square tokens expire after 30 days

        $response = m::mock('Guzzle\Http\Message\Response');
        $response->shouldReceive('getBody')->times(1)->andReturn(sprintf(
            '{"access_token": "mock_access_token", "expires_at": "%s", "merchant_id": 1}',
            date('c', $expiration) // ISO 8601
        ));

        $client = m::mock('Guzzle\Service\Client');
        $client->shouldReceive('setBaseUrl')->times(1);
        $client->shouldReceive('post->send')->times(1)->andReturn($response);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);

        $this->assertEquals('mock_access_token', $token->accessToken);
        $this->assertLessThanOrEqual($expiration, $token->expires);
        $this->assertGreaterThanOrEqual(time(), $token->expires);
        $this->assertEquals('1', $token->uid);
    }

    /**
     * @expectedException League\OAuth2\Client\Exception\IDPException
     */
    public function testGetAccessTokenFailure()
    {
        $response = m::mock('Guzzle\Http\Message\Response');
        $response->shouldReceive('getBody')->times(1)->andReturn(
            '{"type": "internal_server_error", "message": "Something went wrong"}'
        );

        $exception = new BadResponseException;
        $exception->setResponse($response);

        $request = m::mock('Guzzle\Http\Message\Request');
        $request->shouldReceive('setBody')->with(
            $body = m::type('string'),
            $type = 'application/json'
        )->times(1)->andReturn($request);
        $request->shouldReceive('send')->times(1)->andThrow($exception);

        $client = m::mock('Guzzle\Service\Client');
        $client->shouldReceive('post')->with(
            $this->provider->urlRenewToken(),
            m::on(function ($headers) {
                return !empty($headers['Authorization'])
                    && strpos($headers['Authorization'], 'Client') === 0;
            })
        )->times(1)->andReturn($request);
        $this->provider->setHttpClient($client);

        $this->provider->getAccessToken('renew_token', ['access_token' => 'mock_token']);
    }


    public function testUserData()
    {
        $expiration = time() + 60 * 60 * 24 * 30; // Square tokens expire after 30 days

        $postResponse = m::mock('Guzzle\Http\Message\Response');
        $postResponse->shouldReceive('getBody')->times(1)->andReturn(sprintf(
            '{"access_token": "mock_access_token", "expires_at": "%s", "merchant_id": 1}',
            date('c', $expiration) // ISO 8601
        ));

        $getResponse = m::mock('Guzzle\Http\Message\Response');
        $getResponse->shouldReceive('getBody')->times(4)->andReturn(
            '{"id": 12345, "name": "mock_name", "email": "mock_email"}'
        );

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

    /**
     * @expectedException InvalidArgumentException
     */
    public function testRefreshAccessToken()
    {
        $token = $this->provider->getAccessToken('refresh_token', ['refresh_token' => 'mock_access_token']);
    }
}
