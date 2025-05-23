<?php

declare(strict_types=1);

namespace Buddy\Repman\Command;

use Buddy\Repman\Service\Downloader;
use Buddy\Repman\Service\Proxy\ProxyRegister;
use Buddy\Repman\Service\Stream;
use DateMalformedStringException;
use DateTimeImmutable;
use League\Flysystem\FilesystemException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use RuntimeException;
use SimpleXMLElement;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Throwable;

final class ProxySyncReleasesCommand extends Command
{
    public const LOCK_TTL = 30;

    protected static $defaultName = 'repman:proxy:sync-releases';

    private LockInterface $lock;

    public function __construct(private readonly ProxyRegister $register, private readonly Downloader $downloader, private readonly CacheItemPoolInterface $cache, private readonly LockFactory $lockFactory)
    {
        parent::__construct();
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Sync proxy releases with packagist.org')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->lock = $this
            ->lockFactory
            ->createLock('packagist_releases_feed', self::LOCK_TTL);
        if (!$this->lock->acquire()) {
            return 0;
        }

        try {
            $feed = $this->loadFeed();
            if (!$this->alreadySynced((string) $feed->channel->pubDate)) {
                $this->syncPackages($feed);
            }
        } catch (FilesystemException|Throwable) {
            return 1;
        } finally {
            $this->lock->release();
        }

        return 0;
    }

    /**
     * @throws FilesystemException
     * @throws Throwable
     */
    private function syncPackages(SimpleXMLElement $feed): void
    {
        $proxy = $this
            ->register
            ->getByHost('packagist.org');

        $syncedPackages = [];
        foreach ($proxy->syncedPackages() as $name) {
            $syncedPackages[$name] = true;
        }

        foreach ($feed->channel->item as $item) {
            [$name, $version] = explode(' ', (string) $item->guid);
            if (isset($syncedPackages[$name])) {
                $this->lock->refresh();
                $proxy->download($name, $version);
            }
        }
    }

    /**
     * @throws DateMalformedStringException
     * @throws InvalidArgumentException
     */
    private function alreadySynced(string $pubDate): bool
    {
        $lastPubDateCashed = $this->cache->getItem('pub_date');
        if (!$lastPubDateCashed->isHit()) {
            $lastPubDateCashed->set($pubDate);
            $this->cache->save($lastPubDateCashed);

            return false;
        }

        $lastPubDate = $lastPubDateCashed->get();

        return new DateTimeImmutable($pubDate) <= new DateTimeImmutable($lastPubDate);
    }

    private function loadFeed(): SimpleXMLElement
    {
        $stream = $this
            ->downloader
            ->getContents('https://packagist.org/feeds/releases.rss')
            ->getOrElse(Stream::fromString(''));

        $xml = @simplexml_load_string((string) stream_get_contents($stream));
        if ($xml === false) {
            throw new RuntimeException('Unable to parse RSS feed');
        }

        return $xml;
    }
}
