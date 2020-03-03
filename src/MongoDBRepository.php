<?php

declare(strict_types=1);

/*
 * This file is part of the broadway/broadway-saga package.
 *
 * (c) Qandidate.com <opensource@qandidate.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Broadway\Saga\State\MongoDB;

use Broadway\Saga\State;
use Broadway\Saga\State\Criteria;
use Broadway\Saga\State\RepositoryException;
use Broadway\Saga\State\RepositoryInterface;
use Doctrine\MongoDB\Collection;

class MongoDBRepository implements RepositoryInterface
{
    private $collection;

    public function __construct(Collection $collection)
    {
        $this->collection = $collection;
    }

    /**
     * {@inheritdoc}
     */
    public function findOneBy(Criteria $criteria, $sagaId)
    {
        $query = $this->createQuery($criteria, $sagaId);
        $results = $query->execute();
        $count = count($results);

        if (1 === $count) {
            return State::deserialize(current($results->toArray()));
        }

        if ($count > 1) {
            throw new RepositoryException('Multiple saga state instances found.');
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function save(State $state, $sagaId)
    {
        $serializedState = $state->serialize();
        $serializedState['_id'] = $serializedState['id'];
        $serializedState['sagaId'] = $sagaId;
        $serializedState['removed'] = $state->isDone();

        $this->collection->save($serializedState);
    }

    private function createQuery(Criteria $criteria, $sagaId)
    {
        $comparisons = $criteria->getComparisons();
        $wheres = [];

        foreach ($comparisons as $key => $value) {
            $wheres['values.'.$key] = $value;
        }

        $queryBuilder = $this->collection->createQueryBuilder()
            ->addAnd($wheres)
            ->addAnd(['removed' => false, 'sagaId' => $sagaId]);

        return $queryBuilder->getQuery();
    }
}
