<?php

declare(strict_types=1);

namespace App\Band;

use App\User\User;
use PDO;

final readonly class BandRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return list<Band>
     */
    public function findAll(): array
    {
        $statement = $this->pdo->prepare('SELECT id, name, color FROM bands ORDER BY name');
        $statement->execute();

        $bands = [];

        /** @var array{id: string, name: string, color: ?string} $row */
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $bands[] = $this->hydrate($row);
        }

        return $bands;
    }

    public function findById(int $id): ?Band
    {
        $statement = $this->pdo->prepare('SELECT id, name, color FROM bands WHERE id = :id');
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        /** @var array{id: string, name: string, color: ?string}|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * @return list<Band>
     */
    public function findByUserId(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT bands.id, bands.name, bands.color
             FROM bands
             INNER JOIN band_members ON band_members.band_id = bands.id
             WHERE band_members.user_id = :user_id
             ORDER BY bands.name'
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->execute();

        $bands = [];

        /** @var array{id: string, name: string, color: ?string} $row */
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $bands[] = $this->hydrate($row);
        }

        return $bands;
    }

    /**
     * @return list<User>
     */
    public function findMembers(int $bandId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT users.id, users.name, users.email
             FROM users
             INNER JOIN band_members ON band_members.user_id = users.id
             WHERE band_members.band_id = :band_id
             ORDER BY users.name'
        );
        $statement->bindValue(':band_id', $bandId, PDO::PARAM_INT);
        $statement->execute();

        $members = [];

        /** @var array{id: string, name: string, email: string} $row */
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $members[] = new User(
                id: (int) $row['id'],
                name: $row['name'],
                email: $row['email'],
            );
        }

        return $members;
    }

    /**
     * @param array{id: string, name: string, color: ?string} $row
     */
    private function hydrate(array $row): Band
    {
        return new Band(
            id: (int) $row['id'],
            name: $row['name'],
            color: $row['color'],
        );
    }
}
