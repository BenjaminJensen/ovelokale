<?php

declare(strict_types=1);

namespace App\Http;

use App\User\UserRepository;

final class UserController
{
    /**
     * GET /api/users[?id=N]
     *
     * @param array<string, mixed> $query
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function index(array $query): array
    {
        $repository = new UserRepository(db());

        if (isset($query['id']) && $query['id'] !== '') {
            if (!is_numeric($query['id']) || (int) $query['id'] <= 0) {
                return [
                    'status' => 400,
                    'body' => ['error' => 'id must be a positive integer'],
                ];
            }

            $user = $repository->findById((int) $query['id']);

            if ($user === null) {
                return [
                    'status' => 404,
                    'body' => ['error' => 'User not found'],
                ];
            }

            return [
                'status' => 200,
                'body' => ['user' => $user->toArray()],
            ];
        }

        return [
            'status' => 200,
            'body' => ['users' => array_map(
                static fn ($user) => $user->toArray(),
                $repository->findAll(),
            )],
        ];
    }
}
