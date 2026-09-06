<?php

declare(strict_types=1);

namespace App\Band;

final readonly class Band
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $color,
    ) {
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
        ];
    }
}
