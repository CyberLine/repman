<?php

declare(strict_types=1);

namespace Buddy\Repman\Service\Organization;

use Buddy\Repman\Query\User\Model\PackageName;
use Buddy\Repman\Service\Dist;
use Buddy\Repman\Service\Dist\Storage;
use Composer\Semver\VersionParser;
use DateTimeImmutable;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToReadFile;
use Munus\Control\Option;
use function array_filter;
use function array_merge;
use function glob;
use function serialize;
use function unserialize;

class PackageManager
{
    private readonly VersionParser $versionParser;

    public function __construct(private readonly Storage $distStorage, private readonly FilesystemOperator $repoFilesystem)
    {
        $this->versionParser = new VersionParser();
    }

    /**
     * @param PackageName[] $packages
     *
     * @throws FilesystemException
     *
     * @return array{(DateTimeImmutable | null), mixed[]}
     */
    public function findProviders(string $organizationAlias, array $packages): array
    {
        $data = [];
        $lastModified = null;

        foreach ($packages as $package) {
            $filepath = $this->filepath($organizationAlias, $package->name());
            if (!$this->repoFilesystem->fileExists($filepath)) {
                continue;
            }

            $fileModifyDate = (new DateTimeImmutable())->setTimestamp((int) $this->repoFilesystem->lastModified($filepath));

            if ($fileModifyDate > $lastModified) {
                $lastModified = $fileModifyDate;
            }

            $json = unserialize(
                (string) $this->repoFilesystem->read($filepath), ['allowed_classes' => false]
            );
            $data[] = $json['packages'] ?? [];
        }

        return [
            $lastModified,
            array_merge(...$data),
        ];
    }

    /**
     * @param array $json
     * @param string $organizationAlias
     * @param string $packageName
     * @throws FilesystemException
     */
    public function saveProvider(array $json, string $organizationAlias, string $packageName): void
    {
        $this->repoFilesystem->write($this->filepath($organizationAlias, $packageName), serialize($json));
    }

    public function removeProvider(string $organizationAlias, string $packageName): self
    {
        $file = $this->filepath($organizationAlias, $packageName);
        $this->removeFile($file);

        return $this;
    }

    /**
     * @throws FilesystemException
     */
    public function removeDist(string $organizationAlias, string $packageName): self
    {
        $distDir = $organizationAlias.'/dist/'.$packageName;
        $this->repoFilesystem->deleteDirectory($distDir);

        return $this;
    }

    public function removeVersionDists(string $organizationAlias, string $packageName, string $version, string $format, string $excludeRef): self
    {
        $baseFilename = $organizationAlias.'/dist/'.$packageName.'/'.$this->versionParser->normalize($version).'_';

        $filesToDelete = array_filter(
            (array) glob($baseFilename.'*.'.$format),
            fn ($file) => $file !== $baseFilename.$excludeRef.'.'.$format
        );

        foreach ($filesToDelete as $fileName) {
            if (false === $fileName) {
                continue;
            }

            $this->removeFile($fileName);
        }

        return $this;
    }

    /**
     * @throws FilesystemException
     */
    public function removeOrganizationDir(string $organizationAlias): self
    {
        $this->repoFilesystem->deleteDirectory($organizationAlias);

        return $this;
    }

    /**
     * @return Option<string>
     */
    public function distFilename(string $organizationAlias, string $package, string $version, string $ref, string $format): Option
    {
        $dist = new Dist($organizationAlias, $package, $version, $ref, $format);
        if (!$this->distStorage->has($dist)) {
            return Option::none();
        }

        return Option::of($this->distStorage->filename($dist));
    }

    /**
     * @throws FilesystemException
     *
     * @return Option<resource> Handle for a file
     */
    public function getDistFileReference(
        string $fileName,
    ): Option {
        $fileResource = $this->repoFilesystem->readStream($fileName);
        if (false === $fileResource) {
            return Option::none();
        }

        return Option::some($fileResource);
    }

    private function filepath(string $organizationAlias, string $packageName): string
    {
        return $organizationAlias.'/p/'.$packageName.'.json';
    }

    private function removeFile(string $fileName): void
    {
        try {
            $this->repoFilesystem->delete($fileName);
        } catch (UnableToReadFile) {
        }
    }
}
