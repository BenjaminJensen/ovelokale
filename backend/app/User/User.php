<?php

declare(strict_types=1);

namespace App\User;

final readonly class User
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
