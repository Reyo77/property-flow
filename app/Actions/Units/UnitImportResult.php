<?php

namespace App\Actions\Units;

final readonly class UnitImportResult
{
    /**
     * @param  array<int, list<string>>  $errors  Error messages keyed by spreadsheet row number.
     */
    public function __construct(
        public int $unitsCreated = 0,
        public int $buildingsCreated = 0,
        public array $errors = [],
    ) {}

    public function failed(): bool
    {
        return $this->errors !== [];
    }
}
