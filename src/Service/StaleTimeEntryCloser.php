<?php

namespace App\Service;

use App\Entity\TimeEntry;
use App\Repository\TimeEntryRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Closes time entries that were left open past midnight.
 *
 * An entry left active overnight is closed at the midnight following its
 * start time, so its duration never spans more than one calendar day.
 */
class StaleTimeEntryCloser
{
    public function __construct(
        private readonly TimeEntryRepository $timeEntryRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    public function isStale(TimeEntry $entry): bool
    {
        return $entry->isActive() && $entry->getStartTime() < new \DateTime('today');
    }

    /**
     * Close the entry at the midnight following its start time.
     * The caller is responsible for flushing.
     */
    public function closeAtMidnight(TimeEntry $entry): void
    {
        $midnight = \DateTime::createFromInterface($entry->getStartTime())->modify('tomorrow');

        $entry->setEndTime($midnight);
        $entry->setMeta(array_merge($entry->getMeta(), ['auto_closed' => true]));
    }

    /**
     * Close every entry left open past midnight.
     *
     * @return int number of entries closed
     */
    public function closeAllStale(): int
    {
        $stale = $this->timeEntryRepository->findStaleActiveEntries(new \DateTime('today'));

        foreach ($stale as $entry) {
            $this->closeAtMidnight($entry);
        }
        $this->em->flush();

        return count($stale);
    }
}
