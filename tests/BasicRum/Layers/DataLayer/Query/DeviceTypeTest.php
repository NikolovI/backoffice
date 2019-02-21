<?php

namespace App\Tests\BasicRum\Layers\DataLayer\Query;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use App\BasicRum\Layers\DataLayer;
use App\BasicRum\Periods\Period;
use App\BasicRum\Filters\Secondary\DeviceType;

class DeviceTypeTest extends KernelTestCase
{

    protected function setUp()
    {
        static::bootKernel();
    }

    /**
     * @return \Doctrine\Bundle\DoctrineBundle\Registry $doctrine
     */
    private function _getDoctrine() : \Doctrine\Bundle\DoctrineBundle\Registry
    {
        return static::$kernel->getContainer()->get('doctrine');
    }

    public function testDeviceTypeMobile()
    {
        $period = new Period();
        $period->setPeriod('10/24/2018', '10/24/2018');

        $deviceType = new DeviceType(
            'is',
            'mobile'
        );

        $dataLayer = new DataLayer(
            $this->_getDoctrine(),
            $period,
            [$deviceType]
        );

        $res = $dataLayer->process();

        $this->assertEquals(
            [
                [
                    [
                        'pageViewId' => 1
                    ]
                ]
            ],
            $res
        );
    }

    public function testDeviceTypeDesktop()
    {
        $period = new Period();
        $period->setPeriod('10/24/2018', '10/24/2018');

        $deviceType = new DeviceType(
            'is',
            'desktop'
        );

        $dataLayer = new DataLayer(
            $this->_getDoctrine(),
            $period,
            [$deviceType]
        );

        $res = $dataLayer->process();

        $this->assertEquals(
            [
                [
                    [
                        'pageViewId' => 2
                    ]
                ]
            ],
            $res
        );
    }

}