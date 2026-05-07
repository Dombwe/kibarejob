<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class ChunkedUploadService
{
    public function __construct(
        private readonly SluggerInterface $slugger,
        private readonly string $projectDir,
        private readonly string $storagePath = 'var/storage',
    ) {
    }

    /**
     * @return array{complete: bool, receivedChunks: int, totalChunks: int, uploadId: string, filePath?: string, fileName?: string, hash?: string}
     */
    public function receiveChunk(UploadedFile $chunk, string $uploadId, int $chunkIndex, int $totalChunks, string $originalName): array
    {
        if ($chunkIndex < 0 || $totalChunks < 1 || $chunkIndex >= $totalChunks) {
            throw new \InvalidArgumentException('Index de chunk invalide.');
        }

        $safeUploadId = preg_replace('/[^A-Za-z0-9_-]/', '', $uploadId) ?: bin2hex(random_bytes(8));
        $chunkDirectory = $this->projectDir . DIRECTORY_SEPARATOR . $this->storagePath . DIRECTORY_SEPARATOR . 'chunks' . DIRECTORY_SEPARATOR . $safeUploadId;

        if (!is_dir($chunkDirectory)) {
            mkdir($chunkDirectory, 0775, true);
        }

        $chunk->move($chunkDirectory, sprintf('%06d.part', $chunkIndex));
        $receivedChunks = count(glob($chunkDirectory . DIRECTORY_SEPARATOR . '*.part') ?: []);

        if ($receivedChunks < $totalChunks) {
            return [
                'complete' => false,
                'receivedChunks' => $receivedChunks,
                'totalChunks' => $totalChunks,
                'uploadId' => $safeUploadId,
            ];
        }

        $finalDirectory = $this->projectDir . DIRECTORY_SEPARATOR . $this->storagePath . DIRECTORY_SEPARATOR . 'documents';
        if (!is_dir($finalDirectory)) {
            mkdir($finalDirectory, 0775, true);
        }

        $extension = pathinfo($originalName, PATHINFO_EXTENSION) ?: 'bin';
        $baseName = pathinfo($originalName, PATHINFO_FILENAME) ?: 'document';
        $safeBaseName = (string) $this->slugger->slug($baseName)->lower();
        $fileName = sprintf('%s-%s.%s', $safeBaseName ?: 'document', bin2hex(random_bytes(8)), $extension);
        $finalPath = $finalDirectory . DIRECTORY_SEPARATOR . $fileName;

        $output = fopen($finalPath, 'wb');
        if (false === $output) {
            throw new \RuntimeException('Impossible de finaliser le fichier.');
        }

        for ($index = 0; $index < $totalChunks; ++$index) {
            $partPath = $chunkDirectory . DIRECTORY_SEPARATOR . sprintf('%06d.part', $index);
            $input = fopen($partPath, 'rb');
            if (false === $input) {
                fclose($output);
                throw new \RuntimeException('Chunk manquant pendant la fusion.');
            }
            stream_copy_to_stream($input, $output);
            fclose($input);
        }
        fclose($output);

        $this->removeDirectory($chunkDirectory);

        return [
            'complete' => true,
            'receivedChunks' => $totalChunks,
            'totalChunks' => $totalChunks,
            'uploadId' => $safeUploadId,
            'filePath' => $this->storagePath . '/documents/' . $fileName,
            'fileName' => $fileName,
            'hash' => hash_file('sha256', $finalPath),
        ];
    }

    public function cleanupExpiredChunks(int $olderThanHours = 24): int
    {
        $chunksRoot = $this->projectDir . DIRECTORY_SEPARATOR . $this->storagePath . DIRECTORY_SEPARATOR . 'chunks';
        if (!is_dir($chunksRoot)) {
            return 0;
        }

        $deleted = 0;
        $threshold = time() - ($olderThanHours * 3600);
        foreach (glob($chunksRoot . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $directory) {
            if (filemtime($directory) < $threshold) {
                $this->removeDirectory($directory);
                ++$deleted;
            }
        }

        return $deleted;
    }

    private function removeDirectory(string $directory): void
    {
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }
}
