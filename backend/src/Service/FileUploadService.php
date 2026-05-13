<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class FileUploadService
{
    public function __construct(
        private readonly SluggerInterface $slugger,
        private readonly string $projectDir,
        private readonly string $storageBaseUrl = '/storage',
        private readonly string $storagePath = 'var/storage',
    ) {
    }

    /**
     * @return array{path: string, url: string, hash: string, originalName: string, mimeType: ?string, size: int|null}
     */
    public function upload(UploadedFile $file, string $directory): array
    {
        $safeDirectory = trim($directory, '/\\');
        $targetDirectory = $this->resolveStorageRoot() . DIRECTORY_SEPARATOR . $safeDirectory;

        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0775, true);
        }

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = (string) $this->slugger->slug($originalName)->lower();
        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin';
        $fileName = sprintf('%s-%s.%s', $safeName ?: 'file', bin2hex(random_bytes(8)), $extension);
        $hash = hash_file('sha256', $file->getPathname());
        $mimeType = $file->getMimeType();
        $size = $file->getSize();
        $clientOriginalName = $file->getClientOriginalName();

        $file->move($targetDirectory, $fileName);

        $relativePath = $safeDirectory . '/' . $fileName;

        return [
            'path' => $this->storagePath . '/' . $relativePath,
            'url' => rtrim($this->storageBaseUrl, '/') . '/' . $relativePath,
            'hash' => $hash,
            'originalName' => $clientOriginalName,
            'mimeType' => $mimeType,
            'size' => $size,
        ];
    }

    private function resolveStorageRoot(): string
    {
        if (str_starts_with($this->storagePath, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $this->storagePath)) {
            return rtrim($this->storagePath, '/\\');
        }

        return $this->projectDir . DIRECTORY_SEPARATOR . trim($this->storagePath, '/\\');
    }
}
