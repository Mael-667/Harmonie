<?php

namespace App\Repository;

use App\Entity\Server;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Server>
 */
class ServerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Server::class);
    }

    public function getUsersInfo($serverId){
        // On ne sélectionne que les champs d'affichage : surtout PAS password / token / email.
        return $this->createQueryBuilder("server")
                    ->select("users.id", "users.pseudo", "users.handle", "users.avatar_url", "users.status")
                    ->innerJoin("server.users", "users")
                    ->where("server.id = :serverId")
                    ->setParameter("serverId", $serverId)
                    ->getQuery()
                    ->getArrayResult();
    }

//    /**
//     * @return Server[] Returns an array of Server objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('s.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Server
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
