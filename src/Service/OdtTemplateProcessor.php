<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class OdtTemplateProcessor
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function processTemplate(string $odtPath, array $variables): string
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
            foreach ($variables as $key => $value) {
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
} 