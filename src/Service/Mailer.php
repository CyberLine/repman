<?php

declare(strict_types=1);

namespace Buddy\Repman\Service;

interface Mailer
{
    public function sendPasswordResetLink(string $email, string $token, string $operatingSystem, string $browser): void;
}
