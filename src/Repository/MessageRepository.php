<?php

namespace App\Repository;

use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    // 1. La clause WITH est inutile/redondante. message.user est déjà la relation user. La clause WITH sert à ajouter une 
    // condition supplémentaire au join (ex : WITH user.active = true). Là tu lui demandes user = message.user, ce qui est tautologique.
    // 2. Surtout : sans addSelect("user"), le join ne sert à rien. Par défaut Doctrine n'hydrate que l'entité racine. Donc quand 
    // ton frontend lira $message->getUser()->getPseudo() pour les 50 messages, Doctrine fera 50 requêtes pour récupérer chaque user (N+1). Le but du join était justement d'éviter ça.
    public function getLastMessages($channel){
        return $this->createQueryBuilder("message")
            ->select("message.id, message.attachment, message.content, message.timestamp")
            ->innerJoin("message.user", "user")
            ->addSelect("user.pseudo AS authorPseudo, user.avatar_url AS authorAvatar, user.id as authorId")
            ->where("message.channel = :channel")
            ->setParameter("channel", $channel)
            ->orderBy("message.id", 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getArrayResult();
            // getArrayResult() au lieu de getResult() → renvoie un tableau associatif plat, directement json_encode-able
    }

    //    /**
    //     * @return Message[] Returns an array of Message objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('m.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Message
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
