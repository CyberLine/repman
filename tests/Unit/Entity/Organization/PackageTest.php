<?php

declare(strict_types=1);

namespace Buddy\Repman\Tests\Unit\Entity\Organization;

use Buddy\Repman\Entity\Organization\Package;
use Buddy\Repman\Entity\Organization\Package\Link;
use Buddy\Repman\Entity\Organization\Package\Version;
use Buddy\Repman\Tests\MotherObject\PackageMother;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final class PackageTest extends TestCase
{
    private Package $package;

    protected function setUp(): void
    {
        $this->package = PackageMother::withOrganization('vcs', 'https://url.to/package', 'buddy');
    }

    public function testCheckNameOnSuccessSync(): void
    {
        $this->expectException(RuntimeException::class);

        $this->package->syncSuccess('../invalid/name', 'desc', '1.2.0.0', [], [], new DateTimeImmutable());
    }

    public function testSyncSuccessRemovesUnencounteredVersions(): void
    {
        $this->package->addOrUpdateVersion($version1 = new Version(Uuid::uuid4(), '1.0.0', 'someref', 1234, new DateTimeImmutable(), Version::STABILITY_STABLE));
        $this->package->addOrUpdateVersion($version2 = new Version(Uuid::uuid4(), '1.0.1', 'anotherref', 5678, new DateTimeImmutable(), Version::STABILITY_STABLE));
        $this->package->addOrUpdateVersion($version3 = new Version(Uuid::uuid4(), '1.1.0', 'lastref', 6543, new DateTimeImmutable(), Version::STABILITY_STABLE));

        $this->package->syncSuccess('some/package', 'desc', '1.1.0', ['1.0.0' => true, '1.1.0' => true], [], new DateTimeImmutable());

        $this->assertCount(2, $this->package->versions());
        $this->assertContains($version1, $this->package->versions());
        $this->assertNotContains($version2, $this->package->versions());
        $this->assertContains($version3, $this->package->versions());
    }

    public function testSyncSuccessRemovesUnencounteredLinks(): void
    {
        $this->package->addLink($link1 = new Link(Uuid::uuid4(), $this->package, 'replaces', 'buddy-works/testone', '^1.0'));
        $this->package->addLink($link2 = new Link(Uuid::uuid4(), $this->package, 'replaces', 'buddy-works/testtwo', '^1.0'));
        $this->package->addLink($link3 = new Link(Uuid::uuid4(), $this->package, 'replaces', 'buddy-works/testthree', '^1.0'));

        $this->package->syncSuccess(
            'some/package',
            'desc',
            '1.1.0',
            [],
            ['replaces-buddy-works/testone' => true, 'replaces-buddy-works/testthree' => true],
            new DateTimeImmutable()
        );

        $this->assertCount(2, $this->package->links());
        $this->assertContains($link1, $this->package->links());
        $this->assertNotContains($link2, $this->package->links());
        $this->assertContains($link3, $this->package->links());
    }

    public function testSyncSuccessRemovesDuplicatedLinks(): void
    {
        $this->package->addLink(new Link(Uuid::uuid4(), $this->package, 'requires', 'phpunit/phpunit', '^1.0'));
        $this->package->addLink(new Link(Uuid::uuid4(), $this->package, 'requires', 'phpunit/phpunit', '^1.0'));
        $this->package->syncSuccess('some/package', 'desc', '1.1.0', [], ['requires-phpunit/phpunit' => true], new DateTimeImmutable());

        $this->assertCount(1, $this->package->links());
    }

    public function testOuathTokenNotFound(): void
    {
        $this->expectException(RuntimeException::class);

        $this->package->oauthToken();
    }

    public function testMetadataNotFound(): void
    {
        $this->expectException(RuntimeException::class);

        $this->package->metadata('not-exist');
    }

    public function testPackageGetProperties(): void
    {
        $date = new DateTimeImmutable();
        $version = new Version($id = Uuid::uuid4(), '1.0.0', 'someref', 1234, $date, Version::STABILITY_STABLE);
        $this->package->addOrUpdateVersion($version);

        $this->assertInstanceOf(Version::class, $returnedVersion = $this->package->getVersion('1.0.0'));
        $this->assertEquals($id, $returnedVersion->id());
        $this->assertSame('1.0.0', $returnedVersion->version());
        $this->assertSame('someref', $returnedVersion->reference());
        $this->assertSame(1234, $returnedVersion->size());
        $this->assertEquals($date, $returnedVersion->date());
    }

    public function testPackageNonExisting(): void
    {
        $version = new Version(Uuid::uuid4(), '1.0.0', 'someref', 1234, new DateTimeImmutable(), Version::STABILITY_STABLE);
        $this->package->addOrUpdateVersion($version);

        $this->assertEquals(false, $this->package->getVersion('1.0.1'));
    }

    public function testPackageUpdateVersion(): void
    {
        $date = new DateTimeImmutable('tomorrow');
        // Make sure the dates do not match so we can test that it is updated
        $version = new Version($id1 = Uuid::uuid4(), '1.0.0', 'someref', 1234, new DateTimeImmutable('today'), Version::STABILITY_STABLE);
        $versionUpdated = new Version($id2 = Uuid::uuid4(), '1.0.0', 'newref', 5678, $date, Version::STABILITY_STABLE);
        $this->package->addOrUpdateVersion($version);
        $this->package->addOrUpdateVersion($versionUpdated);

        $this->assertInstanceOf(Version::class, $returnedVersion = $this->package->getVersion('1.0.0'));
        $this->assertEquals($id1, $returnedVersion->id()); // We don't update the ID
        $this->assertSame('1.0.0', $returnedVersion->version());
        $this->assertSame('newref', $returnedVersion->reference());
        $this->assertSame(5678, $returnedVersion->size());
        $this->assertEquals($date, $returnedVersion->date());
    }

    public function testPackageAddSameVersion(): void
    {
        $version = new Version(Uuid::uuid4(), '1.0.0', 'someref', 1234, new DateTimeImmutable(), Version::STABILITY_STABLE);
        $this->package->addOrUpdateVersion($version);
        $this->package->addOrUpdateVersion($version); // this should not throw exception

        $this->expectException(RuntimeException::class);
        $version->setPackage($this->package);
    }
}
