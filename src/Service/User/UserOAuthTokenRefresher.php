<?php

declare(strict_types=1);

namespace Buddy\Repman\Service\User;

use Buddy\Repman\Service\User\UserOAuthTokenRefresher\AccessToken;
use DateTimeImmutable;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Grant\RefreshToken;

class UserOAuthTokenRefresher
{
    public function __construct(private readonly ClientRegistry $oauth)
    {
    }

    public function refresh(string $type, string $refreshToken): AccessToken
    {
        $accessToken = $this->oauth->getClient($type)->getOAuth2Provider()
            ->getAccessToken(new RefreshToken(), ['refresh_token' => $refreshToken]);

        return new AccessToken(
            $accessToken->getToken(),
            $accessToken->getExpires() !== null ? (new DateTimeImmutable())->setTimestamp($accessToken->getExpires()) : null
        );
    }
}
