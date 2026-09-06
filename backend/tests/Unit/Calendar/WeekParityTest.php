<?php

declare(strict_types=1);

use App\Calendar\WeekParity;

test('odd iso week numbers resolve to Odd', function () {
    expect(WeekParity::forIsoWeekNumber(37))->toBe(WeekParity::Odd);
});

test('even iso week numbers resolve to Even', function () {
    expect(WeekParity::forIsoWeekNumber(38))->toBe(WeekParity::Even);
});
