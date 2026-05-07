<?php

namespace App\Entity\Enum;

enum SubscriptionTarget: string
{
    case Candidat = 'candidat';
    case Employeur = 'employeur';
}
