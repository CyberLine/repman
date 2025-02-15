<?php

declare(strict_types=1);

namespace Buddy\Repman\MessageHandler\Organization;

use Buddy\Repman\Message\Organization\RemoveOrganization;
use Buddy\Repman\Repository\OrganizationRepository;
use Buddy\Repman\Service\Organization\PackageManager;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

final class RemoveOrganizationHandler implements MessageHandlerInterface
{
    public function __construct(private readonly OrganizationRepository $organizations, private readonly PackageManager $packageManager)
    {
    }

    public function __invoke(RemoveOrganization $message): void
    {
        $id = Uuid::fromString($message->id());
        $organization = $this->organizations->getById($id);

        $this->organizations->remove($id);

        foreach ($organization->synchronizedPackages() as $package) {
            $this
                ->packageManager
                ->removeProvider($package->organizationAlias(), (string) $package->name())
                ->removeDist($package->organizationAlias(), (string) $package->name());
        }

        $this->packageManager->removeOrganizationDir($organization->alias());
    }
}
