<?php

declare(strict_types=1);

namespace Buddy\Repman\Repository;

use Buddy\Repman\Entity\User;
use Buddy\Repman\Security\Model\User as SecurityUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use function mb_strtolower;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function emailExist(string $email): bool
    {
        return false !== $this->_em->getConnection()->fetchOne('SELECT id FROM "user" WHERE email = :email', [
            'email' => mb_strtolower($email),
        ]);
    }

    public function getByEmail(string $email): User
    {
        $user = $this->findOneBy(['email' => mb_strtolower($email)]);
        if (!$user instanceof User) {
            throw new InvalidArgumentException(sprintf('User with email %s not found', mb_strtolower($email)));
        }

        return $user;
    }

    public function getByResetPasswordToken(string $token): User
    {
        $user = $this->findOneBy(['resetPasswordToken' => $token]);
        if (!$user instanceof User) {
            throw new InvalidArgumentException(sprintf('User with reset password token %s not found', $token));
        }

        return $user;
    }

    public function getByConfirmEmailToken(string $token): User
    {
        $user = $this->findOneBy(['emailConfirmToken' => $token]);
        if (!$user instanceof User) {
            throw new InvalidArgumentException(sprintf('User with email confirm token %s not found', $token));
        }

        return $user;
    }

    public function getById(UuidInterface $id): User
    {
        $user = $this->find($id);
        if (!$user instanceof User) {
            throw new InvalidArgumentException(sprintf('User with id %s not found', $id->toString()));
        }

        return $user;
    }

    public function add(User $user): void
    {
        $this->_em->persist($user);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof SecurityUser) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user = $this->getByEmail($user->getUserIdentifier());
        $user->setPassword($newHashedPassword);

        $this->_em->persist($user);
        $this->_em->flush();
    }

    public function remove(UuidInterface $id): void
    {
        $this->_em->remove($this->getById($id));
    }

    public function setEmailScanResult(UuidInterface $id, bool $value): void
    {
        $user = $this->getById($id);
        $user->setEmailScanResult($value);

        $this->_em->persist($user);
        $this->_em->flush();
    }
}
