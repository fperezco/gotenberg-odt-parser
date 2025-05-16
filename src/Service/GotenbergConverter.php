<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GotenbergConverter
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $gotenbergUrl
    ) {}

    public function convertToPdf(string $filePath): Response
    {
        try {
            // Prepare multipart request
            $boundary = '------------------------' . bin2hex(random_bytes(8));
            $content = '';
            
            // Add file to multipart content
            $content .= "--{$boundary}\r\n";
            $content .= "Content-Disposition: form-data; name=\"files\"; filename=\"document.odt\"\r\n";
            $content .= "Content-Type: application/vnd.oasis.opendocument.text\r\n\r\n";
            $content .= file_get_contents($filePath) . "\r\n";
            $content .= "--{$boundary}--\r\n";

            // Send to Gotenberg
            $response = $this->httpClient->request('POST', $this->gotenbergUrl, [
                'headers' => [
                    'Accept' => 'application/pdf',
                    'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
                ],
                'body' => $content
            ]);

            return new Response(
                $response->getContent(),
                $response->getStatusCode(),
                ['Content-Type' => 'application/pdf']
            );
        } finally {
            // Cleanup the input file if it exists
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }
} 