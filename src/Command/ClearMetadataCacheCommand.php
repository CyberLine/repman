<?php

declare(strict_types=1);

namespace Buddy\Repman\Command;

use ArrayIterator;
use Buddy\Repman\Service\Dist\FilePatternFilterIterator;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function sprintf;

final class ClearMetadataCacheCommand extends Command
{
    protected static $defaultName = 'repman:metadata:clear-cache';

    /** @var array<string> */
    private const VCS_PATTERNS = [
        '.svn', '_svn', 'CVS', '_darcs', '.arch-params', '.monotone', '.bzr', '.git', '.hg',
    ];

    public function __construct(private readonly FilesystemOperator $repoFilesystem)
    {
        parent::__construct();
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Clear packages metadata cache (json files)')
        ;
    }

    /**
     * @throws FilesystemException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $files = $this->repoFilesystem->listContents('/', true);

        $iterator = new FilePatternFilterIterator(
            new ArrayIterator($files),
            self::VCS_PATTERNS,
            '/.*json$/'
        );

        $count = 0;
        foreach ($iterator as $file) {
            /* @var array<string, string> $file */
            $this->repoFilesystem->delete($file['path']);
            ++$count;
        }

        $count > 0 ?
            $output->writeln(sprintf('Deleted %s file(s).', $count))
            : $output->writeln('No metadata files found.');

        return 0;
    }
}
