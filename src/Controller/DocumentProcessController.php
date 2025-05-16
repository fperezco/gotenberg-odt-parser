<?php

namespace App\Controller;

use App\Service\GotenbergConverter;
use App\Service\OdtTemplateProcessor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;

class DocumentProcessController extends AbstractController
{
    public function __construct(
        private OdtTemplateProcessor $templateProcessor,
        private GotenbergConverter $gotenbergConverter,
        private LoggerInterface $logger
    ) {}

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
            $processedPath = $this->templateProcessor->processTemplate(
                $templateFile->getPathname(), 
                $parameters
            );
            
            // Convert to PDF using Gotenberg
            return $this->gotenbergConverter->convertToPdf($processedPath);

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