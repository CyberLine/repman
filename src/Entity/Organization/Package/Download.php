<?php

declare(strict_types=1);

namespace Buddy\Repman\Entity\Organization\Package;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity
 *
 * @ORM\Table(
 *     name="organization_package_download",
 *     indexes={
 *
 *      @Index(name="package_id_idx", columns={"package_id"}),
 *      @Index(name="download_date_idx", columns={"date"}),
 *      @Index(name="download_version_idx", columns={"version"})
 *     }
 * )
 */
class Download
{
    public function __construct(
        /**
         * @ORM\Id()
         *
         * @ORM\Column(type="uuid")
         */
        private UuidInterface $id,
        /**
         * @ORM\Column(type="uuid")
         */
        private UuidInterface $packageId,
        /**
         * @ORM\Column(type="date_immutable")
         */
        private DateTimeImmutable $date,
        /**
         * @ORM\Column(type="string")
         */
        private string $version,
        /**
         * @ORM\Column(type="string", length=45, nullable=true)
         */
        private ?string $ip = null,
        /**
         * @ORM\Column(type="string", nullable=true)
         */
        private ?string $userAgent = null,
    ) {
    }
}
