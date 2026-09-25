<?php

namespace App\Support\Finance;

/**
 * A finished report: column headings and rows, where each row is a label, its money columns, and
 * a style ('line', 'section' heading, 'total', or 'grand' total). The same table drives the page,
 * the CSV/Excel export and the tests, so what is shown is exactly what is exported.
 */
final class ReportTable
{
    /**
     * @var list<array{label: string, amounts: list<Money|null>, style: string, meta: array<string, mixed>}>
     */
    private array $rows = [];

    /**
     * @param  list<string>  $columns  Headings for the money columns (the label column is implicit).
     */
    public function __construct(
        public readonly string $title,
        public readonly string $period,
        public readonly array $columns,
        public readonly string $labelHeading = '',
    ) {}

    public function section(string $label): self
    {
        $this->rows[] = ['label' => $label, 'amounts' => [], 'style' => 'section', 'meta' => []];

        return $this;
    }

    /**
     * @param  list<Money|null>  $amounts
     * @param  array<string, mixed>  $meta  Extra data for the page (e.g. a link target); not exported.
     */
    public function line(string $label, array $amounts, array $meta = []): self
    {
        $this->rows[] = ['label' => $label, 'amounts' => $amounts, 'style' => 'line', 'meta' => $meta];

        return $this;
    }

    /**
     * @param  list<Money|null>  $amounts
     */
    public function total(string $label, array $amounts, bool $grand = false): self
    {
        $this->rows[] = ['label' => $label, 'amounts' => $amounts, 'style' => $grand ? 'grand' : 'total', 'meta' => []];

        return $this;
    }

    /**
     * @return list<array{label: string, amounts: list<Money|null>, style: string, meta: array<string, mixed>}>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    /**
     * The amounts of the first row with this label, in cents (null where a column is blank).
     *
     * @return list<int|null>|null
     */
    public function centsFor(string $label): ?array
    {
        foreach ($this->rows as $row) {
            if ($row['label'] === $label) {
                return array_map(fn (?Money $amount) => $amount?->cents, $row['amounts']);
            }
        }

        return null;
    }

    /**
     * Plain rows for a spreadsheet: headings first, amounts as decimals.
     *
     * @return list<list<string>>
     */
    public function toArray(): array
    {
        $data = [[$this->labelHeading, ...$this->columns]];

        foreach ($this->rows as $row) {
            $amounts = array_map(fn (?Money $amount) => $amount?->toDecimal() ?? '', $row['amounts']);
            $data[] = [$row['label'], ...array_pad($amounts, count($this->columns), '')];
        }

        return $data;
    }
}
