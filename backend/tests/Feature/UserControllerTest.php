<?php

declare(strict_types=1);

use App\Http\UserController;

test('lists all users ordered by name', function () {
    db()->exec("INSERT INTO users (id, name, email) VALUES (1, 'Bob', 'bob@example.com')");
    db()->exec("INSERT INTO users (id, name, email) VALUES (2, 'Alice', 'alice@example.com')");

    $result = UserController::index([]);

    expect($result['status'])->toBe(200);
    expect($result['body']['users'])->toHaveCount(2);
    expect($result['body']['users'][0]['name'])->toBe('Alice');
    expect($result['body']['users'][1]['name'])->toBe('Bob');
});

test('returns a single user by id', function () {
    db()->exec("INSERT INTO users (id, name, email) VALUES (1, 'Alice', 'alice@example.com')");

    $result = UserController::index(['id' => '1']);

    expect($result['status'])->toBe(200);
    expect($result['body']['user'])->toMatchArray([
        'id' => 1,
        'name' => 'Alice',
        'email' => 'alice@example.com',
    ]);
});

test('returns 404 for an unknown id', function () {
    $result = UserController::index(['id' => '999']);

    expect($result['status'])->toBe(404);
});

test('rejects a non-numeric id', function () {
    $result = UserController::index(['id' => 'not-a-number']);

    expect($result['status'])->toBe(400);
});
