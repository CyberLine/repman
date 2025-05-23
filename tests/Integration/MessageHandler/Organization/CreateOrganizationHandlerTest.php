<?php

declare(strict_types=1);

namespace Buddy\Repman\Tests\Integration\MessageHandler\Organization;

use Buddy\Repman\Message\Organization\CreateOrganization;
use Buddy\Repman\Query\User\Model\Organization;
use Buddy\Repman\Query\User\OrganizationQuery\DbalOrganizationQuery;
use Buddy\Repman\Tests\Integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;

final class CreateOrganizationHandlerTest extends IntegrationTestCase
{
    public function testSuccess(): void
    {
        $this->dispatchMessage(new CreateOrganization(
            $id = Uuid::uuid4()->toString(),
            $ownerId = $this->fixtures->createUser('test@buddy.works', 'secret'),
            $name = 'Acme Inc.'
        ));

        /** @var Organization $organization */
        $organization = $this->container()->get(DbalOrganizationQuery::class)->getByAlias('acme-inc')->get();

        $this->assertSame('Acme Inc.', $organization->name());
        $this->assertSame('acme-inc', $organization->alias());
        $this->assertSame($id, $organization->id());
        $this->assertTrue($organization->isOwner($ownerId));
        $this->assertTrue($organization->isMember($ownerId));
    }
}
