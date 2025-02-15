<?php

declare(strict_types=1);

namespace Buddy\Repman\DataFixtures;

use Buddy\Repman\Service\Proxy\Downloads;
use Buddy\Repman\Service\Proxy\Downloads\Package;
use Buddy\Repman\Service\Proxy\ProxyRegister;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Load download for current proxy packages:
 * symfony console d:f:l --group=ProxyPackageDownloadFixtures --append.
 */
final class ProxyPackageDownloadFixtures extends Fixture
{
    private readonly Generator $faker;

    public function __construct(private readonly Downloads $downloads, private readonly ProxyRegister $register, private readonly EntityManagerInterface $em)
    {
        $this->faker = Factory::create();
    }

    public function load(ObjectManager $manager): void
    {
        $this->register->getByHost('packagist.org')->syncedPackages()->forEach(function (string $package): void {
            $this->loadDownloads($package);
        });
    }

    private function loadDownloads(string $package): void
    {
        $output = new ConsoleOutput();
        $progress = new ProgressBar($output, 30);
        $output->writeln(sprintf('Package: %s', $package));
        $progress->start();
        $versions = array_map(fn () => $this->faker->numerify('#.#.#'), range(1, 10));

        for ($i = 0; $i < 30; ++$i) {
            $dayDownloads = random_int(0, 100);
            for ($j = 0; $j < $dayDownloads; ++$j) {
                $this->downloads->save(
                    [new Package($package, $this->faker->randomElement($versions))],
                    (new DateTimeImmutable())->modify(sprintf('-%s days', $i)),
                    $this->faker->ipv4,
                    $this->faker->userAgent
                );
            }

            $progress->advance();
        }

        $this->em->commit();
        $this->em->beginTransaction();

        $output->writeln('');
    }
}
