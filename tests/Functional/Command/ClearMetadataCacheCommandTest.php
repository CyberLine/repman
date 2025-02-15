<?php

declare(strict_types=1);

namespace Buddy\Repman\Tests\Functional\Command;

use Buddy\Repman\Command\ClearMetadataCacheCommand;
use Buddy\Repman\Tests\Functional\FunctionalTestCase;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;
use function dirname;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function sys_get_temp_dir;

final class ClearMetadataCacheCommandTest extends FunctionalTestCase
{
    public function testClearMetadataCache(): void
    {
        $basePath = sys_get_temp_dir().'/repman/clear-metadata';

        $ignoredFiles = [
            $basePath.'/dist/.svn/foo/packages.json',
            $basePath.'/dist/.git/objects/packages.json',
            $basePath.'/dist/.hg/foo/packages.json',
        ];
        $this->prepareTempFiles(
            $packagesFile = $basePath.'/packagist.org/packages.json',
            $distFile = $basePath.'/dist/a/b/dist.zip',
            $ignoredFiles,
        );

        $this->assertFileExists($packagesFile);
        $this->assertFileExists($distFile);

        $command = new ClearMetadataCacheCommand(new Filesystem(new Local($basePath)));
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertFileDoesNotExist($packagesFile);
        $this->assertFileExists($distFile);

        foreach ($ignoredFiles as $ignoredFile) {
            $this->assertFileExists($ignoredFile);
        }

        $this->assertSame("Deleted 1 file(s).\n", $commandTester->getDisplay());

        $filesystem = new SymfonyFilesystem();
        $filesystem->remove($basePath);
    }

    public function testNoMetadataFilesFound(): void
    {
        $basePath = sys_get_temp_dir().'/repman/clear-metadata';

        $command = new ClearMetadataCacheCommand(new Filesystem(new Local($basePath)));
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertSame("No metadata files found.\n", $commandTester->getDisplay());
    }

    /**
     * @param array<string> $ignoredFiles
     */
    private function prepareTempFiles(
        string $packagesFile,
        string $distFile,
        array $ignoredFiles,
    ): void {
        $this->ensureDirExist($packagesFile);
        $this->ensureDirExist($distFile);
        foreach ($ignoredFiles as $ignoredFile) {
            $this->ensureDirExist($ignoredFile);
            file_put_contents($ignoredFile, '{"packages":[]}');
        }

        file_put_contents($packagesFile, '{"packages":[]}');
        file_put_contents($distFile, 'zip content');
    }

    private function ensureDirExist(string $path): void
    {
        $path = dirname($path);
        if (!is_dir($path)) {
            mkdir($path, 0o777, true);
        }
    }
}
