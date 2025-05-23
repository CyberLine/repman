<?php

declare(strict_types=1);

namespace Buddy\Repman\Service\Security\PackageScanner;

use Buddy\Repman\Entity\Organization\Package;
use Buddy\Repman\Entity\Organization\Package\ScanResult;
use Buddy\Repman\Message\Security\SendScanResult;
use Buddy\Repman\Query\User\Model\PackageName;
use Buddy\Repman\Repository\ScanResultRepository;
use Buddy\Repman\Service\Dist\Storage;
use Buddy\Repman\Service\Organization\PackageManager;
use Buddy\Repman\Service\Security\PackageScanner;
use Buddy\Repman\Service\Security\SecurityChecker;
use Composer\Semver\VersionParser;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;
use ZipArchive;
use function array_shift;
use function explode;
use function implode;
use function preg_match;
use function unlink;

final class SensioLabsPackageScanner implements PackageScanner
{
    private readonly VersionParser $versionParser;

    public function __construct(
        private readonly SecurityChecker $checker,
        private readonly PackageManager $packageManager,
        private readonly ScanResultRepository $results,
        private readonly MessageBusInterface $messageBus,
        private readonly Storage $distStorage,
    ) {
        $this->versionParser = new VersionParser();
    }

    public function scan(Package $package): void
    {
        $packageName = $package->name();
        $result = [];
        $status = ScanResult::STATUS_OK;

        if ($packageName === null) {
            return;
        }

        try {
            $lockFiles = $this->extractLockFiles($this->findDistribution($package));
            if ($lockFiles === []) {
                $status = ScanResult::STATUS_NOT_AVAILABLE;
            }

            foreach ($lockFiles as $lockFileName => $content) {
                $scanResults = $this->checker->check($content);
                if ($scanResults !== []) {
                    $status = ScanResult::STATUS_WARNING;
                }

                $result[$lockFileName] = $scanResults;
            }
        } catch (Throwable $throwable) {
            $this->saveResult(
                $package,
                ScanResult::STATUS_ERROR,
                [
                    'exception' => [
                        $throwable::class => $throwable->getMessage(),
                    ],
                ],
            );

            return;
        }

        $this->saveResult($package, $status, $result);
    }

    /**
     * @param mixed[] $result
     */
    private function saveResult(Package $package, string $status, array $result): void
    {
        $date = new DateTimeImmutable();
        $package->setScanResult($status, $date, $result);
        $this->results->add(new ScanResult(Uuid::uuid4(), $package, $date, $status, $result));

        if ($status === ScanResult::STATUS_WARNING) {
            $this->messageBus->dispatch(
                new SendScanResult(
                    $package->scanResultEmails(),
                    $package->organizationAlias(),
                    (string) $package->name(),
                    $package->id()->toString(),
                    $result
                )
            );
        }
    }

    private function findDistribution(Package $package): string
    {
        $packageName = $package->name();
        $latestReleasedVersion = $package->latestReleasedVersion();

        $normalizedVersion = $latestReleasedVersion === 'no stable release' ?
            '9999999-dev' : $this->versionParser->normalize((string) $latestReleasedVersion);

        [, $providerData] = $this->packageManager->findProviders(
            $package->organizationAlias(),
            [new PackageName($package->id()->toString(), (string) $package->name())]
        );

        foreach ($providerData[$packageName] ?? [] as $packageData) {
            $packageVersion = $packageData['version_normalized']
                ??
                $this->versionParser->normalize($packageData['version']);
            $packageDist = $packageData['dist'];
            $reference = $packageDist['reference'] ?? $packageDist['shasum'];

            if ($packageVersion === $normalizedVersion && isset($packageDist['url'], $reference)) {
                $archiveType = $packageDist['type'];
                $filename = $this->packageManager->distFilename(
                    $package->organizationAlias(),
                    (string) $packageName,
                    $normalizedVersion,
                    $reference,
                    $archiveType
                );

                return $filename->getOrElseThrow(
                    new RuntimeException(sprintf('Distribution file for version %s not found', $packageVersion))
                );
            }
        }

        throw new RuntimeException(sprintf('Version %s for package %s not found', $normalizedVersion, $packageName));
    }

    /**
     * @return array<string,string>
     */
    private function extractLockFiles(string $distFilename): array
    {
        $tmpZipFile = $this->distStorage->getLocalFileForDistUrl($distFilename);
        $zip = new ZipArchive();
        $result = $zip->open($tmpZipFile->get());
        if ($result !== true) {
            throw new RuntimeException(sprintf("Error while opening ZIP file '%s', code: %s", $distFilename, $result));
        }

        $lockFiles = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $filename = (string) $zip->getNameIndex($i);
            if (preg_match('/\/composer.lock$/', $filename) === 1) {
                $lockFileContent = $zip->getFromIndex($i);
                $trimmed = explode('/', $filename);
                array_shift($trimmed);
                $lockFiles['/'.implode('/', $trimmed)] = (string) $lockFileContent;
            }
        }

        $zip->close();

        @unlink($tmpZipFile->get());

        return $lockFiles;
    }
}
