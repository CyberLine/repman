<?php

declare(strict_types=1);

namespace Buddy\Repman\MessageHandler\Organization\Member;

use Buddy\Repman\Message\Organization\Member\InviteUser;
use Buddy\Repman\Repository\OrganizationRepository;
use Buddy\Repman\Service\Mailer;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

final class InviteUserHandler implements MessageHandlerInterface
{
    public function __construct(private readonly OrganizationRepository $organizations, private readonly Mailer $mailer)
    {
    }

    public function __invoke(InviteUser $message): void
    {
        $organization = $this->organizations->getById(Uuid::fromString($message->organizationId()));
        if ($organization->inviteUser($message->email(), $message->role(), $message->token())) {
            $this->mailer->sendInvitationToOrganization($message->email(), $message->token(), $organization->name());
        }
    }
}
