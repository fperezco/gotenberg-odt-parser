<?php

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HealthCheckControllerTest extends WebTestCase
{
    public function testHealthCheckEndpoint(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $responseData = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('status', $responseData);
        $this->assertArrayHasKey('timestamp', $responseData);
        $this->assertEquals('ok', $responseData['status']);
        
        // Verify timestamp format
        $timestamp = \DateTime::createFromFormat('Y-m-d H:i:s', $responseData['timestamp']);
        $this->assertNotFalse($timestamp);
        $this->assertEquals($responseData['timestamp'], $timestamp->format('Y-m-d H:i:s'));
    }
} 