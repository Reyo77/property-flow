<?php

namespace App\Support\Finance;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * A {@see ReportTable} as a spreadsheet (CSV or Excel), with the title and period above it.
 */
class ReportExport implements FromArray, ShouldAutoSize, WithTitle
{
    public function __construct(private readonly ReportTable $table, private readonly string $communityName) {}

    /**
     * @return list<list<string>>
     */
    public function array(): array
    {
        return [
            [$this->communityName],
            [$this->table->title],
            [$this->table->period],
            [],
            ...$this->table->toArray(),
        ];
    }

    public function title(): string
    {
        return mb_substr($this->table->title, 0, 31);
    }
}
