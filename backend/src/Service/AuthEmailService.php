<?php

namespace App\Service;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class AuthEmailService
{
    private const EMAIL_VERIFICATION_TTL = '+24 hours';
    private const PASSWORD_RESET_TTL = '+1 hour';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $publicUrl,
        private readonly string $mailFrom,
        private readonly string $mailFromName,
    ) {
    }

    public function createEmailVerificationToken(User $user): string
    {
        $token = $this->generateToken();

        $user
            ->setIsEmailVerified(false)
            ->setEmailVerificationTokenHash($this->hashToken($token))
            ->setEmailVerificationTokenExpiresAt(new \DateTimeImmutable(self::EMAIL_VERIFICATION_TTL));

        return $token;
    }

    public function createPasswordResetToken(User $user): string
    {
        $token = $this->generateToken();

        $user
            ->setPasswordResetTokenHash($this->hashToken($token))
            ->setPasswordResetTokenExpiresAt(new \DateTimeImmutable(self::PASSWORD_RESET_TTL));

        return $token;
    }

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isEmailVerificationTokenValid(User $user, string $token): bool
    {
        return null !== $user->getEmailVerificationTokenHash()
            && hash_equals($user->getEmailVerificationTokenHash(), $this->hashToken($token))
            && null !== $user->getEmailVerificationTokenExpiresAt()
            && $user->getEmailVerificationTokenExpiresAt() >= new \DateTimeImmutable();
    }

    public function isPasswordResetTokenValid(User $user, string $token): bool
    {
        return null !== $user->getPasswordResetTokenHash()
            && hash_equals($user->getPasswordResetTokenHash(), $this->hashToken($token))
            && null !== $user->getPasswordResetTokenExpiresAt()
            && $user->getPasswordResetTokenExpiresAt() >= new \DateTimeImmutable();
    }

    public function markEmailVerified(User $user): void
    {
        $user
            ->setIsEmailVerified(true)
            ->setEmailVerificationTokenHash(null)
            ->setEmailVerificationTokenExpiresAt(null);
    }

    public function clearPasswordResetToken(User $user): void
    {
        $user
            ->setPasswordResetTokenHash(null)
            ->setPasswordResetTokenExpiresAt(null);
    }

    public function sendEmailVerification(User $user, string $token): void
    {
        $url = $this->absoluteUrl('/verify-email?token=' . urlencode($token));
        $email = (new Email())
            ->from(new Address($this->mailFrom, $this->mailFromName))
            ->replyTo(new Address($this->mailFrom, $this->mailFromName))
            ->to($user->getEmail())
            ->subject('Confirmez votre adresse email KIBARE-JOB')
            ->text("Bienvenue sur KIBARE-JOB.\n\nConfirmez votre adresse email avec ce lien valable 24h :\n{$url}\n")
            ->html(sprintf(
                '<p>Bienvenue sur KIBARE-JOB.</p><p>Confirmez votre adresse email avec ce lien valable 24h :</p><p><a href="%s">Confirmer mon email</a></p>',
                htmlspecialchars($url, ENT_QUOTES)
            ));

        $this->sendAndLog($email, 'email_verification');
    }

    public function sendPasswordReset(User $user, string $token): void
    {
        $url = $this->absoluteUrl('/reset-password?token=' . urlencode($token));
        $email = (new Email())
            ->from(new Address($this->mailFrom, $this->mailFromName))
            ->replyTo(new Address($this->mailFrom, $this->mailFromName))
            ->to($user->getEmail())
            ->subject('Reinitialisation de votre mot de passe KIBARE-JOB')
            ->text("Une demande de reinitialisation a ete faite.\n\nUtilisez ce lien valable 1h :\n{$url}\n")
            ->html(sprintf(
                '<p>Une demande de reinitialisation a ete faite.</p><p>Utilisez ce lien valable 1h :</p><p><a href="%s">Reinitialiser mon mot de passe</a></p>',
                htmlspecialchars($url, ENT_QUOTES)
            ));

        $this->sendAndLog($email, 'password_reset');
    }

    private function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function absoluteUrl(string $path): string
    {
        return rtrim($this->publicUrl, '/') . $path;
    }

    private function sendAndLog(Email $email, string $type): void
    {
        $to = implode(', ', array_map(static fn (Address $address): string => $address->getAddress(), $email->getTo()));

        try {
            $this->mailer->send($email);
            $this->logger->info('Auth email accepted by mailer.', [
                'type' => $type,
                'to' => $to,
                'from' => $this->mailFrom,
                'message_id' => $email->getHeaders()->get('Message-ID')?->getBodyAsString(),
            ]);
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error('Auth email rejected by mailer.', [
                'type' => $type,
                'to' => $to,
                'from' => $this->mailFrom,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
