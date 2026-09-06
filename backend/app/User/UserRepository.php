<?php

declare(strict_types=1);

namespace App\User;

use PDO;

final readonly class UserRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return list<User>
     */
    public function findAll(): array
    {
        $statement = $this->pdo->prepare('SELECT id, name, email FROM users ORDER BY name');
        $statement->execute();

        $users = [];

        /** @var array{id: string, name: string, email: string} $row */
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $users[] = $this->hydrate($row);
        }

        return $users;
    }

    public function findById(int $id): ?User
    {
        $statement = $this->pdo->prepare('SELECT id, name, email FROM users WHERE id = :id');
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        /** @var array{id: string, name: string, email: string}|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * @param array{id: string, name: string, email: string} $row
     */
    private function hydrate(array $row): User
    {
        return new User(
            id: (int) $row['id'],
            name: $row['name'],
            email: $row['email'],
        );
    }
}
