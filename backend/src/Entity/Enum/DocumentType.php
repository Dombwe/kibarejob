<?php

namespace App\Entity\Enum;

enum DocumentType: string
{
    case Diploma = 'diploma';
    case Certificate = 'certificate';
    case Attestation = 'attestation';
    case Other = 'other';
}
