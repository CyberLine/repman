<?php

declare(strict_types=1);

namespace Buddy\Repman\DataFixtures;

use Buddy\Repman\Message\Organization\GenerateToken;
use Buddy\Repman\Query\Admin\Model\Organization;
use Buddy\Repman\Query\Admin\OrganizationQuery;
use Buddy\Repman\Query\Filter;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Load download for current packages:
 * symfony console d:f:l --group=TokenFixtures --append.
 */
final class TokenFixtures extends Fixture
{
    private readonly Generator $faker;

    public function __construct(private readonly MessageBusInterface $messageBus, private readonly OrganizationQuery $organizations)
    {
        $this->faker = Factory::create();
    }

    public function load(ObjectManager $manager): void
    {
        $output = new ConsoleOutput();
        $progress = new ProgressBar($output, $this->organizations->count());
        $output->writeln('Generating tokens');
        $progress->start();

        foreach ($this->organizations->findAll(new Filter(0, 100)) as $organization) {
            $this->generateTokens($organization);
            $progress->advance();
        }

        $output->writeln('');
    }

    public function generateTokens(Organization $organization, int $count = 20): void
    {
        for ($i = 0; $i < $count; ++$i) {
            $this->messageBus->dispatch(new GenerateToken($organization->id(), $this->faker->company));
        }
    }
}
