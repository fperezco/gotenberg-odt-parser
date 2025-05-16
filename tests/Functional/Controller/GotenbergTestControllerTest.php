<?php

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;

class GotenbergTestControllerTest extends WebTestCase
{
    public function testSuccessfulGotenbergConnection(): void
    {
        $client = static::createClient();
        
        // Mock the HTTP client response
        $mockResponse = new MockResponse('Gotenberg is running', [
            'http_code' => 200,
        ]);
        $mockHttpClient = new MockHttpClient($mockResponse);
        
        // Override the HttpClient service with our mock
        self::getContainer()->set('http_client', $mockHttpClient);
        
        $client->request('GET', '/test-gotenberg-connection');
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        
        $responseData = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('status', $responseData);
        $this->assertArrayHasKey('gotenberg_status', $responseData);
        $this->assertArrayHasKey('gotenberg_response', $responseData);
        $this->assertArrayHasKey('timestamp', $responseData);
        
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals(200, $responseData['gotenberg_status']);
        $this->assertEquals('Gotenberg is running', $responseData['gotenberg_response']);
        
        // Verify timestamp format
        $timestamp = \DateTime::createFromFormat('Y-m-d H:i:s', $responseData['timestamp']);
        $this->assertNotFalse($timestamp);
        $this->assertEquals($responseData['timestamp'], $timestamp->format('Y-m-d H:i:s'));
    }
    
    public function testFailedGotenbergConnection(): void
    {
        $client = static::createClient();
        
        // Mock a failed HTTP client response
        $mockResponse = new MockResponse('Connection failed', [
            'http_code' => 500,
            'error' => 'Failed to connect to Gotenberg'
        ]);
        $mockHttpClient = new MockHttpClient($mockResponse);
        
        // Override the HttpClient service with our mock
        self::getContainer()->set('http_client', $mockHttpClient);
        
        $client->request('GET', '/test-gotenberg-connection');
        
        $this->assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        
        $responseData = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('status', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertArrayHasKey('timestamp', $responseData);
        
        $this->assertEquals('error', $responseData['status']);
        
        // Verify timestamp format
        $timestamp = \DateTime::createFromFormat('Y-m-d H:i:s', $responseData['timestamp']);
        $this->assertNotFalse($timestamp);
        $this->assertEquals($responseData['timestamp'], $timestamp->format('Y-m-d H:i:s'));
    }
} 