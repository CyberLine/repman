<?php

declare(strict_types=1);

namespace Buddy\Repman\MessageHandler\Organization;

use Buddy\Repman\Message\Organization\RemovePackage;
use Buddy\Repman\Repository\OrganizationRepository;
use Buddy\Repman\Repository\PackageRepository;
use Buddy\Repman\Service\Organization\PackageManager;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

final class RemovePackageHandler implements MessageHandlerInterface
{
    public function __construct(private readonly OrganizationRepository $organizations, private readonly PackageManager $packageManager, private readonly PackageRepository $packages)
    {
    }

    public function __invoke(RemovePackage $message): void
    {
        $id = Uuid::fromString($message->id());
        $organization = $this->organizations
            ->getById(Uuid::fromString($message->organizationId()));
        $package = $this->packages->getById($id);

        $organization->removePackage($id);

        if ($package->isSynchronized()) {
            $this
                ->packageManager
                ->removeProvider($package->organizationAlias(), (string) $package->name())
                ->removeDist($package->organizationAlias(), (string) $package->name());
        }
    }
}
