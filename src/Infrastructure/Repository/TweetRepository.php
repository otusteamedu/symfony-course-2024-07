<?php

namespace App\Infrastructure\Repository;

use App\Domain\Entity\Tweet;
use App\Domain\Model\TweetModel;

/**
 * @extends AbstractRepository<Tweet>
 */
class TweetRepository extends AbstractRepository
{
    public function create(Tweet $tweet): int
    {
        return $this->store($tweet);
    }

    /**
     * @return TweetModel[]
     */
    public function getTweetsPaginated(int $page, int $perPage): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('t')
            ->from(Tweet::class, 't')
            ->orderBy('t.id', 'DESC')
            ->setFirstResult($perPage * $page)
            ->setMaxResults($perPage);

        return $qb->getQuery()->enableResultCache(null, "tweets_{$page}_{$perPage}")->getResult();
    }

    /**
     * @param int[] $authorIds
     * @return Tweet[]
     */
    public function getTweetsForAuthorIds(array $authorIds, int $count): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('t')
            ->from(Tweet::class, 't')
            ->where($qb->expr()->in('IDENTITY(t.author)', ':authorIds'))
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($count);

        $qb->setParameter('authorIds', $authorIds);

        return $qb->getQuery()->getResult();
    }
}
