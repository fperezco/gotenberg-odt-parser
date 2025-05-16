<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class DocumentProcessController extends AbstractController
{
    private string $gotenbergUrl;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger
    ) {
        $this->gotenbergUrl = $_ENV['GOTENBERG_URL'];
    }

    private function processOdtTemplate(string $odtPath, array $vars): string
    {
        // Create temporary directory that will be automatically deleted at the end of the script
        $tmpDir = sys_get_temp_dir() . '/odt_' . uniqid();
        $outputPath = $tmpDir . '_processed.odt';
        mkdir($tmpDir);

        try {
            // 1. Extract the ODT file
            $zip = new \ZipArchive();
            if ($zip->open($odtPath) !== true) {
                throw new \Exception("Could not open ODT file");
            }
            $zip->extractTo($tmpDir);
            $zip->close();

            // 2. Read and modify content.xml
            $contentXmlPath = $tmpDir . '/content.xml';
            $content = file_get_contents($contentXmlPath);

            // 3. Replace template variables
            foreach ($vars as $key => $value) {
                $content = str_replace('{{' . $key . '}}', htmlspecialchars($value), $content);
            }

            file_put_contents($contentXmlPath, $content);

            // 4. Recompress as ODT
            $zip = new \ZipArchive();
            if ($zip->open($outputPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \Exception("Could not create output file");
            }

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($tmpDir),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($tmpDir) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }

            $zip->close();

            return $outputPath;
        } finally {
            // Cleanup temporary files
            $this->removeDirectory($tmpDir);
        }
    }

    private function removeDirectory($dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->removeDirectory($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }

    #[Route('/process-document', name: 'process_document', methods: ['POST'])]
    public function processDocument(Request $request): Response
    {
        try {
            $templateFile = $request->files->get('template');
            if (!$templateFile) {
                throw new \Exception('Template file not provided');
            }

            $parameters = json_decode($request->request->get('parameters', '{}'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON parameters');
            }

            // Process the document
            $processedPath = $this->processOdtTemplate($templateFile->getPathname(), $parameters);
            
            try {
                // Send to Gotenberg for PDF conversion
                $boundary = '------------------------' . bin2hex(random_bytes(8));
                $content = '';
                
                // Add file to multipart content
                $content .= "--{$boundary}\r\n";
                $content .= "Content-Disposition: form-data; name=\"files\"; filename=\"document.odt\"\r\n";
                $content .= "Content-Type: application/vnd.oasis.opendocument.text\r\n\r\n";
                $content .= file_get_contents($processedPath) . "\r\n";
                $content .= "--{$boundary}--\r\n";

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
                // Cleanup processed temporary file
                if (file_exists($processedPath)) {
                    unlink($processedPath);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error processing document', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return new JsonResponse([
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 