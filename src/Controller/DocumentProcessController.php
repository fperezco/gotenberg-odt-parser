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
    private const GOTENBERG_URL = 'https://demo.gotenberg.dev/forms/libreoffice/convert';
    private string $tempDir;
    private string $debugDir;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger
    ) {
        $this->tempDir = sys_get_temp_dir();
        $this->debugDir = dirname(__DIR__, 2) . '/var/debug/odt';
    }

    private function reemplazarKeysEnOdt(string $odtPath, array $vars, string $outputPath): void
    {
        $tmpDir = $this->tempDir . '/odt_' . uniqid();
        mkdir($tmpDir);

        // 1. Descomprimir el .odt
        $zip = new \ZipArchive();
        if ($zip->open($odtPath) === true) {
            $zip->extractTo($tmpDir);
            $zip->close();
        } else {
            throw new \Exception("No se pudo abrir el archivo ODT");
        }

        // 2. Leer content.xml
        $contentXmlPath = $tmpDir . '/content.xml';
        $content = file_get_contents($contentXmlPath);

        $this->logger->info('Contenido original del content.xml', [
            'content' => $content
        ]);

        // 3. Reemplazar claves
        foreach ($vars as $clave => $valor) {
            // Reemplaza {{clave}} por el valor
            $content = str_replace('{{' . $clave . '}}', htmlspecialchars($valor), $content);
        }

        $this->logger->info('Contenido después de reemplazar variables', [
            'content' => $content
        ]);

        // 4. Guardar content.xml modificado
        file_put_contents($contentXmlPath, $content);

        // 5. Volver a comprimir todo como .odt
        $zip = new \ZipArchive();
        if ($zip->open($outputPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
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
        } else {
            throw new \Exception("No se pudo crear el archivo de salida");
        }

        // Limpieza
        $this->rrmdir($tmpDir);
    }

    private function rrmdir($dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->rrmdir($dir . "/" . $object);
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
                throw new \Exception('No se proporcionó el archivo de plantilla');
            }

            $parameters = json_decode($request->request->get('parameters', '{}'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Parámetros JSON inválidos');
            }

            // Guardar una copia del archivo original para debug
            $originalPath = $this->debugDir . '/original_' . uniqid() . '.odt';
            copy($templateFile->getPathname(), $originalPath);
            $this->logger->info('Original file saved', [
                'path' => $originalPath
            ]);

            // Procesar el documento
            $processedPath = $this->debugDir . '/processed_' . uniqid() . '.odt';
            $this->reemplazarKeysEnOdt($templateFile->getPathname(), $parameters, $processedPath);
            $this->logger->info('Processed file saved', [
                'path' => $processedPath
            ]);

            // Enviar a Gotenberg
            $boundary = '------------------------' . bin2hex(random_bytes(8));
            $content = '';
            
            // Añadir el archivo al contenido multipart
            $content .= "--{$boundary}\r\n";
            $content .= "Content-Disposition: form-data; name=\"files\"; filename=\"document.odt\"\r\n";
            $content .= "Content-Type: application/vnd.oasis.opendocument.text\r\n\r\n";
            $content .= file_get_contents($processedPath) . "\r\n";
            $content .= "--{$boundary}--\r\n";

            $response = $this->httpClient->request('POST', self::GOTENBERG_URL, [
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