<?php

declare(strict_types=1);

namespace App\BasicRum\Stats;

use App\BasicRum\Beacon\RumData\ResourceTiming;
use App\Entity\LastBlockingResources;
use App\Entity\NavigationTimings;
use App\Entity\NavigationTimingsUserAgents;

class LastBlockingResourceCalculator
{
    /** @var int */
    private $scannedChunkSize = 1000;

    private $batchSize = 500;

    /** @var \Doctrine\Bundle\DoctrineBundle\Registry */
    private $registry;

    public function __construct(\Doctrine\Bundle\DoctrineBundle\Registry $registry)
    {
        $this->registry = $registry;
    }

    public function calculate()
    {
        $lastPageViewId = $this->_getPreviousLastScannedPageViewId();

        $navTimingsRes = $this->_getNavTimingsInRange($lastPageViewId + 1, $lastPageViewId + $this->scannedChunkSize);

        $resourceTiming = new ResourceTiming();

        $lastBlockingResources = [];

        foreach ($navTimingsRes as $nav) {
            $pageViewId = $nav['pageViewId'];

            $resourceTimingsData = $resourceTiming->fetchResources($pageViewId, $this->registry);

            $finalName = '';
            $tmpEndTime = 0;

            foreach ($this->_getBlockingResources($resourceTimingsData) as $resource) {
                if (($resource['startTime'] + $resource['duration']) > $tmpEndTime) {
                    $finalName = $resource['name'];
                    $tmpEndTime = $resource['startTime'] + $resource['duration'];
                }
            }

            if (!empty($finalName)) {
                if ($tmpEndTime > 65535) {
                    $tmpEndTime = 65535;
                }

                $lastBlockingResources[] = [
                    'page_view_id' => $pageViewId,
                    'url' => $finalName,
                    'time' => $tmpEndTime,
                    'first_paint' => $nav['firstPaint'],
                ];
            }
        }

        $this->_saveBlockingResources($lastBlockingResources);

        return \count($lastBlockingResources);
    }

    private function _getBlockingResources(array $resources): array
    {
        $blocking = [];

        foreach ($resources as $resource) {
            $name = basename($resource['name']);

            if (false !== strpos($name, '.js')) {
                if (!isset($resource['scriptBody']) || !isset($resource['scriptDefer']) || !isset($resource['scriptAsync'])) {
                    continue;
                }

                if (true === $resource['scriptBody'] || true === $resource['scriptDefer'] || true === $resource['scriptAsync']) {
                    continue;
                }

                $blocking[] = $resource;
            }

            if (false !== strpos($name, '.css')) {
                $blocking[] = $resource;
            }
        }

        return $blocking;
    }

    /**
     * @return bool|int
     */
    private function _getPreviousLastScannedPageViewId()
    {
        $repository = $this->registry
            ->getRepository(LastBlockingResources::class);

        $pageViewId = (int) $repository->createQueryBuilder('lbr')
            ->select('MAX(lbr.pageViewId)')
            ->getQuery()
            ->getSingleScalarResult();

        return 0 === $pageViewId ? 0 : $pageViewId;
    }

    private function _saveBlockingResources(array $resources)
    {
        $cnt = 0;

        foreach ($resources as $resource) {
            ++$cnt;

            $entity = new LastBlockingResources();

            $entity->setPageViewId($resource['page_view_id']);
            $entity->setTime($resource['time']);
            $entity->setUrl($resource['url']);
            $entity->setFirstPaint($resource['first_paint']);

            $this->registry->getManager()->persist($entity);

            if (0 === ($cnt % $this->batchSize)) {
                $this->registry->getManager()->flush();
                $this->registry->getManager()->clear();
            }
        }

        $this->registry->getManager()->flush();
        $this->registry->getManager()->clear();
    }

    /**
     * @return mixed
     */
    private function _getNavTimingsInRange(int $startId, int $endId)
    {
        $repository = $this->registry
            ->getRepository(NavigationTimings::class);

        $query = $repository->createQueryBuilder('nt')
            ->where("nt.pageViewId >= '".$startId."' AND nt.pageViewId <= '".$endId."'")
            ->andWhere('nt.userAgentId NOT IN (:userAgentId)')
            ->setParameter('userAgentId', $this->_botUserAgentsIds())
            ->select(['nt.pageViewId', 'nt.firstPaint'])
            ->getQuery();

        return $query->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);
    }

    /**
     * @return array
     */
    private function _botUserAgentsIds()
    {
        $userAgentRepo = $this->registry
            ->getRepository(NavigationTimingsUserAgents::class);

        $query = $userAgentRepo->createQueryBuilder('ua')
            ->where("ua.deviceType = 'bot'")
            ->select('ua.id')
            ->getQuery();

        $userAgents = $query->getResult();

        $userAgentsArr = [];

        foreach ($userAgents as $agent) {
            $userAgentsArr[] = $agent['id'];
        }

        return $userAgentsArr;
    }
}
