<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class ContactEmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $mailFrom,
        private readonly string $contactTo,
    ) {
    }

    /**
     * @param array{full_name: string, email: string, profile: string, phone: string, subject: string, message: string} $data
     */
    public function send(array $data): void
    {
        $profileLabel = match ($data['profile']) {
            'candidate' => 'Candidat',
            'employer' => 'Recruteur',
            'partner' => 'Partenaire',
            default => 'Autre',
        };

        $text = sprintf(
            "Nouveau message depuis le formulaire de contact KIBARE-JOB\n\nNom: %s\nEmail: %s\nTelephone: %s\nProfil: %s\nObjet: %s\n\nMessage:\n%s\n",
            $data['full_name'],
            $data['email'],
            $data['phone'] ?: 'Non renseigné',
            $profileLabel,
            $data['subject'],
            $data['message']
        );

        $html = sprintf(
            '<h2>Nouveau message de contact KIBARE-JOB</h2>
            <p><strong>Nom :</strong> %s</p>
            <p><strong>Email :</strong> %s</p>
            <p><strong>Telephone :</strong> %s</p>
            <p><strong>Profil :</strong> %s</p>
            <p><strong>Objet :</strong> %s</p>
            <hr>
            <p style="white-space: pre-line;">%s</p>',
            htmlspecialchars($data['full_name'], ENT_QUOTES),
            htmlspecialchars($data['email'], ENT_QUOTES),
            htmlspecialchars($data['phone'] ?: 'Non renseigné', ENT_QUOTES),
            htmlspecialchars($profileLabel, ENT_QUOTES),
            htmlspecialchars($data['subject'], ENT_QUOTES),
            htmlspecialchars($data['message'], ENT_QUOTES)
        );

        $this->mailer->send((new Email())
            ->from($this->mailFrom)
            ->to($this->contactTo)
            ->replyTo($data['email'])
            ->subject('Contact KIBARE-JOB - ' . $data['subject'])
            ->text($text)
            ->html($html));
    }
}
