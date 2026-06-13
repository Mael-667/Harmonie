<?php

namespace App\Repository;

use App\Entity\Channel;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findWithChannelAccess(int $userId, int $channelId): ?array
    {
        /** @var Channel|null $channel */
        $channel = $this->getEntityManager()->createQueryBuilder()
                  ->select("channel", "server")
                  ->from(Channel::class, "channel")
                  ->innerJoin("channel.server", "server")
                  ->innerJoin("server.users", "membre")
                  ->where("channel.id = :channelId AND membre.id = :userId")
                  ->setParameter('channelId', $channelId)
                  ->setParameter('userId', $userId)
                  ->setMaxResults(1)
                  ->getQuery()
                  ->getOneOrNullResult();

        if ($channel === null) {
            return null;
        }

        $server = $channel->getServer();

        return [
            "server" => $server,
            "channel" => $channel,
            "roles" => $server->getRoles(),
        ];
    }

    //    /**
    //     * @return User[] Returns an array of User objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneByToken($value): ?User
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.token = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
