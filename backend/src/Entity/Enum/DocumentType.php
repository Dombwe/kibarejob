<?php

namespace App\Entity\Enum;

enum DocumentType: string
{
    case Diploma = 'diploma';
    case Certificate = 'certificate';
    case Attestation = 'attestation';
    case DrivingLicense = 'driving_license';
    case Other = 'other';
}
