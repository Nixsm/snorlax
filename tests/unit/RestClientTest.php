<?php

use Mockery as m;
use Snorlax\Auth\BearerAuth;
use Kevinrob\GuzzleCache\CacheMiddleware;

/**
 * Tests for the Snorlax\RestClient class
 */
class RestClientTest extends TestCase
{
    public function tearDown()
    {
        m::close();
    }

    /**
     * Verifies that the constructor correctly sets the resources
     */
    public function testResourcesGetter()
    {
        $client = $this->getRestClient();

        $this->assertInstanceOf('PokemonResource', $client->pokemons);
        $this->assertInstanceOf('PokemonResource', $client->getResource('pokemons'));
    }

    /**
     * @expectedException \Snorlax\Exception\ResourceNotImplemented
     * @expectedExceptionMessage Resource "digimons" is not implemented
     */
    public function testResourceNotImplementedException()
    {
        $this->getRestClient()->digimons;
    }

    /**
     * Verifies that the custom instance passed through the constructor is set
     */
    public function testCustomClientWithInstance()
    {
        $custom_client = m::mock('GuzzleHttp\ClientInterface');

        $client = $this->getRestClient([
            'custom' => $custom_client,
            'params' => []
        ]);

        $this->assertSame($custom_client, $client->getOriginalClient());
    }

    /**
     * Verifies that the custom closure gets executed when client is created
     */
    public function testCustomClientWithClosure()
    {
        $custom_client = m::mock('GuzzleHttp\ClientInterface');
        $client = $this->getRestClient([
            'custom' => function (array $params) use ($custom_client) {
                return $custom_client;
            },
            'params' => []
        ]);

        $this->assertSame($custom_client, $client->getOriginalClient());
    }

    /**
     * Verifies that the instance is correctly set with cache and debug params
     */
    public function testClientWithCacheAndDebug()
    {
        $custom_client = m::mock('GuzzleHttp\ClientInterface');

        $client = $this->getRestClient([
            'custom' => $custom_client,
            'params' => [
                'defaults' => ['debug' => true],
                'cache' => true
            ]
        ]);

        $this->assertSame($custom_client, $client->getOriginalClient());
    }

    /**
     * Verifies that the authorization method is set correctly
     */
    public function testSetAuthMethod()
    {
        $custom_client = m::mock('GuzzleHttp\ClientInterface');
        $custom_client
            ->shouldReceive('setDefaultOption')
            ->once()
            ->with('headers', ['Authorization' => 'Bearer token']);

        $client = $this->getRestClient([
            'custom' => $custom_client
        ]);
        $client->setAuthMethod(new BearerAuth('token'));
    }

    public function testClientAddsCacheMiddlewareToHandlerStackWhenNoCustomClientProvidedAndCacheEnabled()
    {
        $restClient = $this->getRestClient(['params' => ['cache' => true]]);
        //Get the guzzle client to inspect it.
        $originalClient = $restClient->getOriginalClient();
        $handlerStack = $originalClient->getConfig('handler');
        //The property we need to check is not accessible, make it so.
        $reflection = new ReflectionObject($handlerStack);
        $stackProperty = $reflection->getProperty('stack');
        $stackProperty->setAccessible(true);
        $stack = $stackProperty->getValue($handlerStack);
        //Look for the middleware
        foreach ($stack as $stackItem) {
            if ($stackItem[1] === 'snorlax-cache' && $stackItem[0] instanceof CacheMiddleware) {
                return true;
            }
        }
        //Middleware does not exist on the stack
        $this->assertTrue(false, 'No CacheMiddleware named snorlax-cache was found');
    }
}
