<?php

declare(strict_types=1);

use App\Http\BandController;

test('lists all bands ordered by name', function () {
    db()->exec("INSERT INTO bands (id, name) VALUES (1, 'The Wailers')");
    db()->exec("INSERT INTO bands (id, name) VALUES (2, 'ABBA')");

    $result = BandController::index([]);

    expect($result['status'])->toBe(200);
    expect($result['body']['bands'])->toHaveCount(2);
    expect($result['body']['bands'][0]['name'])->toBe('ABBA');
    expect($result['body']['bands'][1]['name'])->toBe('The Wailers');
});

test('returns a single band by id with its members', function () {
    db()->exec("INSERT INTO bands (id, name, color) VALUES (1, 'The Wailers', '#ff0000')");
    db()->exec("INSERT INTO users (id, name, email) VALUES (1, 'Bob', 'bob@example.com')");
    db()->exec("INSERT INTO users (id, name, email) VALUES (2, 'Alice', 'alice@example.com')");
    db()->exec('INSERT INTO band_members (band_id, user_id) VALUES (1, 1), (1, 2)');

    $result = BandController::index(['id' => '1']);

    expect($result['status'])->toBe(200);
    expect($result['body']['band'])->toMatchArray([
        'id' => 1,
        'name' => 'The Wailers',
        'color' => '#ff0000',
    ]);
    expect($result['body']['members'])->toHaveCount(2);
    expect($result['body']['members'][0]['name'])->toBe('Alice');
    expect($result['body']['members'][1]['name'])->toBe('Bob');
});

test('returns bands filtered by user_id', function () {
    db()->exec("INSERT INTO users (id, name, email) VALUES (1, 'Bob', 'bob@example.com')");
    db()->exec("INSERT INTO bands (id, name) VALUES (1, 'The Wailers')");
    db()->exec("INSERT INTO bands (id, name) VALUES (2, 'ABBA')");
    db()->exec('INSERT INTO band_members (band_id, user_id) VALUES (1, 1)');

    $result = BandController::index(['user_id' => '1']);

    expect($result['status'])->toBe(200);
    expect($result['body']['bands'])->toHaveCount(1);
    expect($result['body']['bands'][0]['name'])->toBe('The Wailers');
});

test('returns 404 for an unknown id', function () {
    $result = BandController::index(['id' => '999']);

    expect($result['status'])->toBe(404);
});

test('rejects a non-numeric id', function () {
    $result = BandController::index(['id' => 'not-a-number']);

    expect($result['status'])->toBe(400);
});

test('rejects a non-numeric user_id', function () {
    $result = BandController::index(['user_id' => 'not-a-number']);

    expect($result['status'])->toBe(400);
});
