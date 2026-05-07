<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ValidationService
{
    private const PHONE_PATTERN = '/^\+226[0-9]{8}$/';
    private const ALLOWED_CV_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    private const ALLOWED_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(private readonly ValidatorInterface $validator)
    {
    }

    /**
     * @return string[]
     */
    public function validateEntity(object $entity): array
    {
        $violations = $this->validator->validate($entity);
        $errors = [];

        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        return $errors;
    }

    public function normalizeEmail(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }

    public function isValidBurkinaPhone(?string $phone): bool
    {
        return null === $phone || '' === $phone || 1 === preg_match(self::PHONE_PATTERN, $phone);
    }

    /**
     * @return string[]
     */
    public function validatePassword(?string $password): array
    {
        $password = (string) $password;
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Le mot de passe doit contenir au moins 8 caracteres.';
        }

        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins une lettre et un chiffre.';
        }

        return $errors;
    }

    /**
     * @return string[]
     */
    public function validateCvFile(?UploadedFile $file): array
    {
        if (!$file instanceof UploadedFile) {
            return ['Aucun fichier CV fourni.'];
        }

        return $this->validateUploadedFile($file, self::ALLOWED_CV_MIMES, 5 * 1024 * 1024);
    }

    /**
     * @return string[]
     */
    public function validateLogoFile(?UploadedFile $file): array
    {
        if (!$file instanceof UploadedFile) {
            return ['Aucun logo fourni.'];
        }

        return $this->validateUploadedFile($file, self::ALLOWED_IMAGE_MIMES, 2 * 1024 * 1024);
    }

    /**
     * @param string[] $allowedMimeTypes
     *
     * @return string[]
     */
    private function validateUploadedFile(UploadedFile $file, array $allowedMimeTypes, int $maxBytes): array
    {
        $errors = [];

        if (!$file->isValid()) {
            $errors[] = 'Le fichier envoye est invalide.';
        }

        if ($file->getSize() > $maxBytes) {
            $errors[] = sprintf('Le fichier ne doit pas depasser %d Mo.', (int) ($maxBytes / 1024 / 1024));
        }

        if (!in_array($file->getMimeType(), $allowedMimeTypes, true)) {
            $errors[] = 'Type de fichier non autorise.';
        }

        return $errors;
    }
}
