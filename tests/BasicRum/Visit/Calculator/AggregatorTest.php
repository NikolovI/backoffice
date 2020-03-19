<?php

namespace App\Tests\BasicRum\Visit\Calculator;

use PHPUnit\Framework\TestCase;

use App\BasicRum\Visit\Calculator\Aggregator;


class AggregatorTest extends TestCase
{

    private function _getAggregator()
    {
        $fetchMock = $this
            ->getMockBuilder(\App\BasicRum\Visit\Data\Fetch::class)
            ->disableOriginalConstructor()
            ->getMock();

        return new Aggregator(30, $fetchMock);
    }

    /**
     * @group visit_aggregator
     * @throws \Exception
     */
    public function testAggregatorSameGuidTwoSeparateVisits()
    {
        $aggregator = $this->_getAggregator();

        $pageViews = [
            [
                'guid'       => 'test-2-closed-sessions',
                'createdAt'  => new \DateTime('2018-10-25 13:32:33'),
                'pageViewId' => 2,
                'urlId'      => 2
            ],
            [
                'guid'       => 'test-2-closed-sessions',
                'createdAt'  => new \DateTime('2018-10-28 13:32:33'),
                'pageViewId' => 3,
                'urlId'      => 1
            ],
        ];

        foreach ($pageViews as $view) {
            $aggregator->addPageView($view);
        }

        $res = $aggregator->generateVisits([]);

        $this->assertEquals(
            [
                [
                    'visitId'                => false,
                    'guid'                   => 'test-2-closed-sessions',
                    'pageViewsCount'         => 1,
                    'firstPageViewId'        => 2,
                    'lastPageViewId'         => 2,
                    'firstUrlId'             => 2,
                    'lastUrlId'              => 2,
                    'visitDuration'          => 0,
                    'afterLastVisitDuration' => 0,
                    'completed'              => true
                ],
                [
                    'visitId'                => false,
                    'guid'                   => 'test-2-closed-sessions',
                    'pageViewsCount'         => 1,
                    'firstPageViewId'        => 3,
                    'lastPageViewId'         => 3,
                    'firstUrlId'             => 1,
                    'lastUrlId'              => 1,
                    'visitDuration'          => 0,
                    'afterLastVisitDuration' => 259200,
                    'completed'              => false
                ],
            ],
            $res
        );
    }

    /**
     * @group visit_aggregator
     * @throws \Exception
     */
    public function testAggregatorSameGuidTwoSeparateVisitsAttachPreviouslyNotClosed()
    {
        $aggregator = $this->_getAggregator();

        $pageViews = [
            [
                'guid'       => 'test-2-closed-sessions',
                'createdAt'  => new \DateTime('2018-10-25 13:32:33'),
                'pageViewId' => 2,
                'urlId'      => 2
            ],
            [
                'guid'       => 'test-2-closed-sessions',
                'createdAt'  => new \DateTime('2018-10-28 13:32:33'),
                'pageViewId' => 3,
                'urlId'      => 1
            ],
        ];

        foreach ($pageViews as $view) {
            $aggregator->addPageView($view);
        }

        $aggregator->addPageView(
            [
                'guid'       => 'test-2-closed-sessions',
                'createdAt'  => new \DateTime('2018-10-25 13:27:00'),
                'pageViewId' => 1,
                'urlId'      => 1
            ]
        );

        $notCompletedVisits = [
            1 => [
                'visitId'         => 1,
                'guid'            => 'test-2-closed-sessions',
                'pageViewsCount'  => 1,
                'firstPageViewId' => 1,
                'lastPageViewId'  => 1,
                'firstUrlId'      => 1,
                'lastUrlId'       => 1,
                'completed'       => false
            ]
        ];

        $res = $aggregator->generateVisits($notCompletedVisits);

        $this->assertEquals(
            [
                [
                    'visitId'                => 1,
                    'guid'                   => 'test-2-closed-sessions',
                    'pageViewsCount'         => 2,
                    'firstPageViewId'        => 1,
                    'lastPageViewId'         => 2,
                    'firstUrlId'             => 1,
                    'lastUrlId'              => 2,
                    'completed'              => true,
                    'visitDuration'          => 333,
                    'afterLastVisitDuration' => 0,

                ],
                [
                    'visitId'                => false,
                    'guid'                   => 'test-2-closed-sessions',
                    'pageViewsCount'         => 1,
                    'firstPageViewId'        => 3,
                    'lastPageViewId'         => 3,
                    'firstUrlId'             => 1,
                    'lastUrlId'              => 1,
                    'completed'              => false,
                    'visitDuration'          => 0,
                    'afterLastVisitDuration' => 259200,
                ],
            ],
            $res
        );
    }

    /**
     * @group visit_aggregator
     * @throws \Exception
     */
    public function testCloseMoreThanOneChunkWithSameGuidWhenFirstAndLastScanPageViewAreOutsideExpireRange()
    {
        $aggregator = $this->_getAggregator();

        $pageViews = [
            [
                'guid'       => 'test-2-closed-session',
                'createdAt'  => new \DateTime('2018-10-25 13:32:33'),
                'pageViewId' => 2,
                'urlId'      => 2
            ],
            [
                'guid'       => 'test-2-closed-session',
                'createdAt'  => new \DateTime('2018-10-25 13:37:33'),
                'pageViewId' => 3,
                'urlId'      => 1
            ],
            [
                'guid'       => 'test-2-closed-session',
                'createdAt'  => new \DateTime('2018-10-25 18:37:33'),
                'pageViewId' => 4,
                'urlId'      => 1
            ],
            [
                'guid'       => 'last-in-duration-range',
                'createdAt'  => new \DateTime('2018-10-25 20:40:33'),
                'pageViewId' => 5,
                'urlId'      => 1
            ],
        ];

        foreach ($pageViews as $view) {
            $aggregator->addPageView($view);
        }

        $notCompletedVisits = [];

        $res = $aggregator->generateVisits($notCompletedVisits);

        $this->assertEquals(
            [
                [
                    'visitId'                => false,
                    'guid'                   => 'test-2-closed-session',
                    'pageViewsCount'         => 2,
                    'firstPageViewId'        => 2,
                    'lastPageViewId'         => 3,
                    'firstUrlId'             => 2,
                    'lastUrlId'              => 1,
                    'completed'              => true,
                    'visitDuration'          => 300,
                    'afterLastVisitDuration' => 0,

                ],
                [
                    'visitId'                => false,
                    'guid'                   => 'test-2-closed-session',
                    'pageViewsCount'         => 1,
                    'firstPageViewId'        => 4,
                    'lastPageViewId'         => 4,
                    'firstUrlId'             => 1,
                    'lastUrlId'              => 1,
                    'completed'              => true,
                    'visitDuration'          => 0,
                    'afterLastVisitDuration' => 18000,

                ],
                [
                    'visitId'                => false,
                    'guid'                   => 'last-in-duration-range',
                    'pageViewsCount'         => 1,
                    'firstPageViewId'        => 5,
                    'lastPageViewId'         => 5,
                    'firstUrlId'             => 1,
                    'lastUrlId'              => 1,
                    'completed'              => false,
                    'visitDuration'          => 0,
                    'afterLastVisitDuration' => 0,
                ],
            ],
            $res
        );
    }

    /**
     * @group visit_aggregator
     * @throws \Exception
     */
    public function testCloseOnlyOneChunkWithSameGuidWhenFirstAndLastScanPageViewAreInDurationRange()
    {
        $aggregator = $this->_getAggregator();

        $pageViews = [
            [
                'guid'       => 'test-1-closed-session',
                'createdAt'  => new \DateTime('2018-10-25 13:32:33'),
                'pageViewId' => 2,
                'urlId'      => 2
            ],
            [
                'guid'       => 'test-1-closed-session',
                'createdAt'  => new \DateTime('2018-10-25 13:37:33'),
                'pageViewId' => 3,
                'urlId'      => 1
            ],
            [
                'guid'       => 'test-1-closed-session',
                'createdAt'  => new \DateTime('2018-10-25 18:37:33'),
                'pageViewId' => 4,
                'urlId'      => 1
            ],
            [
                'guid'       => 'last-in-duration-range',
                'createdAt'  => new \DateTime('2018-10-25 18:40:33'),
                'pageViewId' => 5,
                'urlId'      => 1
            ],
        ];

        foreach ($pageViews as $view) {
            $aggregator->addPageView($view);
        }

        $notCompletedVisits = [];

        $res = $aggregator->generateVisits($notCompletedVisits);

        $this->assertEquals(
            [
                [
                    'visitId'                => false,
                    'guid'                   => 'test-1-closed-session',
                    'pageViewsCount'         => 2,
                    'firstPageViewId'        => 2,
                    'lastPageViewId'         => 3,
                    'firstUrlId'             => 2,
                    'lastUrlId'              => 1,
                    'completed'              => true,
                    'visitDuration'          => 300,
                    'afterLastVisitDuration' => 0,

                ],
                [
                    'visitId'                => false,
                    'guid'                   => 'test-1-closed-session',
                    'pageViewsCount'         => 1,
                    'firstPageViewId'        => 4,
                    'lastPageViewId'         => 4,
                    'firstUrlId'             => 1,
                    'lastUrlId'              => 1,
                    'completed'              => false,
                    'visitDuration'          => 0,
                    'afterLastVisitDuration' => 18000,

                ],
                [
                    'visitId'                => false,
                    'guid'                   => 'last-in-duration-range',
                    'pageViewsCount'         => 1,
                    'firstPageViewId'        => 5,
                    'lastPageViewId'         => 5,
                    'firstUrlId'             => 1,
                    'lastUrlId'              => 1,
                    'completed'              => false,
                    'visitDuration'          => 0,
                    'afterLastVisitDuration' => 0,
                ],
            ],
            $res
        );
    }

    /**
     * @group visit_aggregator
     * @throws \Exception
     */
    public function testAfterLastVisitDurationCalculatedAgainstPreviouslyCompletedVisit()
    {
        $fetchMock = $this
            ->getMockBuilder(\App\BasicRum\Visit\Data\Fetch::class)
            ->disableOriginalConstructor()
            ->setMethods(['fetchPreviousSessionPageView'])
            ->getMock();

        $counter = 0;

        $fetchMock
            ->expects($this->atLeastOnce())
            ->method('fetchPreviousSessionPageView')
            ->will($this->returnCallback(function () use (&$counter) {
                $counter++;
                if ($counter == 1) {
                    return [
                            'guid'       => 'first-closed-session',
                            'createdAt'  => new \DateTime('2018-10-24 13:32:33'),
                            'pageViewId' => 1,
                            'urlId'      => 1
                        ];
                }
                return [];
            }));


        $aggregator = new Aggregator(30, $fetchMock);

        $pageViews = [
            [
                'guid'       => 'first-closed-session',
                'createdAt'  => new \DateTime('2018-10-25 13:32:33'),
                'pageViewId' => 2,
                'urlId'      => 1
            ],
            [
                'guid'       => 'last-in-duration-range',
                'createdAt'  => new \DateTime('2018-10-25 18:40:33'),
                'pageViewId' => 3,
                'urlId'      => 1
            ],
        ];

        foreach ($pageViews as $view) {
            $aggregator->addPageView($view);
        }

        $res = $aggregator->generateVisits([]);

        $this->assertEquals(
            [
                [
                    'visitId'                => false,
                    'guid'                   => 'first-closed-session',
                    'pageViewsCount'         => 1,
                    'firstPageViewId'        => 2,
                    'lastPageViewId'         => 2,
                    'firstUrlId'             => 1,
                    'lastUrlId'              => 1,
                    'completed'              => true,
                    'visitDuration'          => 0,
                    'afterLastVisitDuration' => 86400,

                ],
                [
                    'visitId'                => false,
                    'guid'                   => 'last-in-duration-range',
                    'pageViewsCount'         => 1,
                    'firstPageViewId'        => 3,
                    'lastPageViewId'         => 3,
                    'firstUrlId'             => 1,
                    'lastUrlId'              => 1,
                    'completed'              => false,
                    'visitDuration'          => 0,
                    'afterLastVisitDuration' => 0
                ],
            ],
            $res
        );
    }

}