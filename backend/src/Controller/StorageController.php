<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

class StorageController extends AbstractController
{
    public function __construct(
        private readonly string $projectDir,
        private readonly string $storagePath,
    ) {
    }

    #[Route('/storage/{path}', name: 'storage_file', requirements: ['path' => '.+'], methods: ['GET'])]
    public function serve(string $path): BinaryFileResponse
    {
        $storageRoot = $this->resolveStorageRoot();
        $requestedPath = $storageRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($path, '/\\'));
        $realRoot = realpath($storageRoot);
        $realFile = realpath($requestedPath);

        if (false === $realRoot || false === $realFile || !str_starts_with($realFile, $realRoot . DIRECTORY_SEPARATOR)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        if (!is_file($realFile) || !is_readable($realFile)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $response = new BinaryFileResponse($realFile);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($realFile));
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }

    private function resolveStorageRoot(): string
    {
        if (str_starts_with($this->storagePath, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $this->storagePath)) {
            return rtrim($this->storagePath, '/\\');
        }

        return $this->projectDir . DIRECTORY_SEPARATOR . trim($this->storagePath, '/\\');
    }
}
