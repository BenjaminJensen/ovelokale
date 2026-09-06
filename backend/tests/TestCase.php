<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

require_once __DIR__ . '/../app/bootstrap.php';

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        db()->exec('SET FOREIGN_KEY_CHECKS = 0');
        db()->exec('TRUNCATE TABLE bookings');
        db()->exec('TRUNCATE TABLE recurring_slots');
        db()->exec('TRUNCATE TABLE band_members');
        db()->exec('TRUNCATE TABLE bands');
        db()->exec('TRUNCATE TABLE users');
        db()->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
