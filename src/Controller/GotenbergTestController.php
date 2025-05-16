<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GotenbergTestController extends AbstractController
{
    private const GOTENBERG_URL = 'https://demo.gotenberg.dev';

    public function __construct(
        private HttpClientInterface $httpClient
    ) {}

    #[Route('/test-gotenberg-connection', name: 'test_gotenberg_connection', methods: ['GET'])]
    public function testConnection(): JsonResponse
    {
        try {
            $response = $this->httpClient->request('GET', self::GOTENBERG_URL);
            
            return new JsonResponse([
                'status' => 'success',
                'gotenberg_status' => $response->getStatusCode(),
                'gotenberg_response' => $response->getContent(),
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'message' => $e->getMessage(),
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            ], 500);
        }
    }
} 