<?php

declare(strict_types=1);

namespace App\Http;

use App\Band\BandRepository;

final class BandController
{
    /**
     * GET /api/bands[?id=N|?user_id=N]
     *
     * @param array<string, mixed> $query
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function index(array $query): array
    {
        $repository = new BandRepository(db());

        if (isset($query['id']) && $query['id'] !== '') {
            if (!is_numeric($query['id']) || (int) $query['id'] <= 0) {
                return [
                    'status' => 400,
                    'body' => ['error' => 'id must be a positive integer'],
                ];
            }

            $band = $repository->findById((int) $query['id']);

            if ($band === null) {
                return [
                    'status' => 404,
                    'body' => ['error' => 'Band not found'],
                ];
            }

            return [
                'status' => 200,
                'body' => [
                    'band' => $band->toArray(),
                    'members' => array_map(
                        static fn ($user) => $user->toArray(),
                        $repository->findMembers($band->id),
                    ),
                ],
            ];
        }

        if (isset($query['user_id']) && $query['user_id'] !== '') {
            if (!is_numeric($query['user_id']) || (int) $query['user_id'] <= 0) {
                return [
                    'status' => 400,
                    'body' => ['error' => 'user_id must be a positive integer'],
                ];
            }

            return [
                'status' => 200,
                'body' => ['bands' => array_map(
                    static fn ($band) => $band->toArray(),
                    $repository->findByUserId((int) $query['user_id']),
                )],
            ];
        }

        return [
            'status' => 200,
            'body' => ['bands' => array_map(
                static fn ($band) => $band->toArray(),
                $repository->findAll(),
            )],
        ];
    }
}
