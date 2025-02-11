<?php

declare(strict_types=1);

namespace Buddy\Repman\Message\Admin;

final class AddTechnicalEmail
{
    public function __construct(private readonly string $email)
    {
    }

    public function email(): string
    {
        return $this->email;
    }
}
