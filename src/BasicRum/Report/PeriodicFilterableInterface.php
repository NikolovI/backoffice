<?php

declare(strict_types=1);

namespace App\BasicRum\Report;

interface PeriodicFilterableInterface
{
    /**
     * @return PeriodicFilterableInterface
     */
    public function setPeriod(string $startPeriod, string $endPeriod): self;

    public function getDataField(): string;

    public function requestPeriodInterval(): \App\BasicRum\Periods\PeriodInterval;

    public function hasPeriods(): bool;
}
