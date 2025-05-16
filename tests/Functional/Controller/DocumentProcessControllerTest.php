<?php

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class DocumentProcessControllerTest extends WebTestCase
{
    public function testProcessDocument(): void
    {
        $client = static::createClient();
        
        // Preparar el archivo ODT
        $odtPath = dirname(__DIR__, 2) . '/Resources/example_odt_template.odt';
        $uploadedFile = new UploadedFile(
            $odtPath,
            'example_odt_template.odt',
            'application/vnd.oasis.opendocument.text',
            null,
            true
        );

        // Preparar los parámetros
        $parameters = [
            'client_name' => 'Test Client',
            'payment_amount' => '1000 €',
            'email_contact' => 'test@example.com'
        ];

        // Realizar la petición POST
        $client->request(
            'POST',
            '/process-document',
            ['parameters' => json_encode($parameters)],
            ['template' => $uploadedFile]
        );

        // Verificar la respuesta
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertEquals('application/pdf', $client->getResponse()->headers->get('Content-Type'));
        
        // Verificar que el contenido de la respuesta es un PDF válido
        $pdfContent = $client->getResponse()->getContent();
        $this->assertNotEmpty($pdfContent);
        $this->assertStringStartsWith('%PDF-', $pdfContent, 'El contenido no es un PDF válido');
        
        // Guardar el PDF para inspección manual si es necesario
        file_put_contents(dirname(__DIR__, 2) . '/Resources/test_output.pdf', $pdfContent);
    }
} 