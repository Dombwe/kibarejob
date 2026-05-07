<?php

namespace App\Entity\Enum;

enum ContractType: string
{
    case Cdi = 'CDI';
    case Cdd = 'CDD';
    case Stage = 'Stage';
    case Freelance = 'Freelance';
    case LocalContract = 'Contrat local';
}
