<?php

namespace App\Entity\Enum;

enum VerificationAction: string
{
    case AutoVerified = 'auto_verified';
    case Flagged = 'flagged';
    case AdminValidated = 'admin_validated';
    case AdminRejected = 'admin_rejected';
}
