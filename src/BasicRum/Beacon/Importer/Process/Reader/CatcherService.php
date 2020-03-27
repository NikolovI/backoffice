<?php

declare(strict_types=1);

namespace App\BasicRum\Beacon\Importer\Process\Reader;

use App\BasicRum\Beacon\Catcher\Storage\File\Sort;
use App\BasicRum\Beacon\Catcher\Storage\File\Time;

class CatcherService
{
    /** @var string */
    private $bundleFile = '';

    /** @var Time */
    private $time;

    /** @var Sort */
    private $sort;

    /**
     * MonolithCatcher constructor.
     */
    public function __construct(string $bundleFile)
    {
        $this->time = new Time();
        $this->sort = new Sort();
        $this->bundleFile = $bundleFile;
    }

    /**
     * @return mixed
     */
    public function read()
    {
        $content = file_get_contents($this->bundleFile);

        $beacons = json_decode($content, true);

        $data = [];

        foreach ($beacons as $bundleEntry) {
            $beaconData = json_decode($bundleEntry['beacon_data'], true);
            $data[] = [
                0 => $this->time->getCreatedAtFromPath($bundleEntry['id']),
                1 => json_encode($beaconData),
            ];
        }

        $this->sort->sortBeacons($data);

        return $data;
    }

    private function _obfuscateBeacon(array $beaconData): array
    {
        $replaceUrl = '.basicrum.com';
        $searchHost = '';

        if (!empty($beaconData['u'])) {
            $realUrlParts = parse_url($beaconData['u']);

            if (!isset($realUrlParts['host'])) {
                return $beaconData;
            }

            $hostParts = explode('.', $realUrlParts['host']);
            unset($hostParts[0]);

            $searchHost = '.'.implode('.', $hostParts);

            $beaconData['u'] = str_replace($searchHost, $replaceUrl, $beaconData['u']);
        }

        if (!empty($beaconData['pgu'])) {
            $realUrlParts = parse_url($beaconData['pgu']);

            if (!isset($realUrlParts['host'])) {
                return $beaconData;
            }

            $hostParts = explode('.', $realUrlParts['host']);
            unset($hostParts[0]);

            $searchHost = '.'.implode('.', $hostParts);

            $beaconData['pgu'] = str_replace($searchHost, $replaceUrl, $beaconData['pgu']);
        }

        if (!empty($beaconData['restiming'])) {
            $beaconData['restiming'] = str_replace($searchHost, $replaceUrl, $beaconData['restiming']);
        }

        return $beaconData;
    }
}
