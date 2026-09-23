<?php

namespace App\Actions\Units;

use App\Concerns\UnitValidationRules;
use App\Imports\UnitRowsImport;
use App\Models\Building;
use App\Models\Community;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Imports units from a CSV or Excel file.
 *
 * The file is imported only when every row is valid, so a failed import never leaves half the units behind.
 */
class ImportUnits
{
    use UnitValidationRules;

    public const int MAX_ROWS = 5000;

    public const array COLUMNS = ['building', 'number', 'floor', 'area', 'unit_factor', 'parking', 'locker'];

    /** The first data row, after the heading row. */
    private const int FIRST_DATA_ROW = 2;

    public function handle(Community $community, UploadedFile $file): UnitImportResult
    {
        $rows = $this->readRows($file);

        if ($rows->isEmpty()) {
            return new UnitImportResult(errors: [1 => [__('The file has no unit rows.')]]);
        }

        if ($rows->count() > self::MAX_ROWS) {
            return new UnitImportResult(errors: [1 => [__('The file has more than :max rows.', ['max' => self::MAX_ROWS])]]);
        }

        $errors = $this->validateRows($community, $rows);

        if ($errors !== []) {
            return new UnitImportResult(errors: $errors);
        }

        return DB::transaction(fn () => $this->createUnits($community, $rows));
    }

    /**
     * Read the non-empty rows, keyed by their row number in the spreadsheet.
     *
     * @return Collection<int, array<string, string|null>>
     */
    private function readRows(UploadedFile $file): Collection
    {
        $sheet = Excel::toArray(new UnitRowsImport, $file)[0] ?? [];

        return collect($sheet)
            ->mapWithKeys(fn (array $row, int $index) => [$index + self::FIRST_DATA_ROW => $this->normalizeRow($row)])
            ->reject(fn (array $row) => array_filter($row, fn (?string $value) => $value !== null) === []);
    }

    /**
     * Keep the known columns and turn blank cells into nulls.
     *
     * @param  array<array-key, mixed>  $row
     * @return array<string, string|null>
     */
    private function normalizeRow(array $row): array
    {
        return collect(self::COLUMNS)
            ->mapWithKeys(function (string $column) use ($row) {
                $value = $row[$column] ?? null;
                $value = is_scalar($value) ? trim((string) $value) : null;

                return [$column => $value === '' ? null : $value];
            })
            ->all();
    }

    /**
     * @param  Collection<int, array<string, string|null>>  $rows
     * @return array<int, list<string>>
     */
    private function validateRows(Community $community, Collection $rows): array
    {
        $errors = [];
        $existingKeys = $this->existingUnitKeys($community);
        $seenKeys = [];

        foreach ($rows as $rowNumber => $row) {
            $validator = Validator::make($row, [
                'building' => ['nullable', 'string', 'max:255'],
                'number' => ['required', 'string', 'max:50'],
                ...$this->unitDetailRules(),
            ]);

            $messages = $validator->errors()->all();

            if ($row['number'] !== null) {
                $key = $this->unitKey($row['building'], $row['number']);

                if (isset($existingKeys[$key])) {
                    $messages[] = __('Unit :number already exists in this community.', ['number' => $row['number']]);
                } elseif (isset($seenKeys[$key])) {
                    $messages[] = __('Unit :number is also on row :row.', ['number' => $row['number'], 'row' => $seenKeys[$key]]);
                } else {
                    $seenKeys[$key] = $rowNumber;
                }
            }

            if ($messages !== []) {
                $errors[$rowNumber] = array_values($messages);
            }
        }

        return $errors;
    }

    /**
     * @param  Collection<int, array<string, string|null>>  $rows
     */
    private function createUnits(Community $community, Collection $rows): UnitImportResult
    {
        $buildings = $community->buildings()->get()->keyBy(fn (Building $building) => Str::lower($building->name));
        $buildingsCreated = 0;

        foreach ($rows as $row) {
            $buildingId = null;

            if ($row['building'] !== null) {
                $buildingKey = Str::lower($row['building']);

                if (! $buildings->has($buildingKey)) {
                    $buildings->put($buildingKey, $community->buildings()->create(['name' => $row['building']]));
                    $buildingsCreated++;
                }

                $buildingId = $buildings->get($buildingKey)?->id;
            }

            $community->units()->create([
                'building_id' => $buildingId,
                'number' => $row['number'],
                'floor' => $row['floor'],
                'area' => $row['area'],
                'unit_factor' => $row['unit_factor'],
                'parking' => $row['parking'],
                'locker' => $row['locker'],
            ]);
        }

        return new UnitImportResult(unitsCreated: $rows->count(), buildingsCreated: $buildingsCreated);
    }

    /**
     * @return array<string, true>
     */
    private function existingUnitKeys(Community $community): array
    {
        return $community->units()
            ->with('building')
            ->get()
            ->mapWithKeys(fn ($unit) => [$this->unitKey($unit->building?->name, $unit->number) => true])
            ->all();
    }

    private function unitKey(?string $buildingName, string $number): string
    {
        return Str::lower(trim((string) $buildingName)).'|'.Str::lower($number);
    }
}
