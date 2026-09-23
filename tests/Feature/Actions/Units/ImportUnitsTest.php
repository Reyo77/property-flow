<?php

use App\Actions\Units\ImportUnits;
use App\Actions\Units\UnitImportResult;
use App\Models\Building;
use App\Models\Community;
use App\Models\Unit;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use function Pest\Laravel\actingAs;

/**
 * @param  list<list<string>>  $rows
 */
function unitsCsv(array $rows, string $heading = 'building,number,floor,area,unit_factor,parking,locker'): UploadedFile
{
    $lines = array_map(fn (array $row) => implode(',', $row), $rows);

    return UploadedFile::fake()->createWithContent('units.csv', $heading."\n".implode("\n", $lines)."\n");
}

function importInto(Community $community, UploadedFile $file): UnitImportResult
{
    actingAs(companyAdmin($community->company));

    return app(ImportUnits::class)->handle($community, $file);
}

it('imports units and creates missing buildings', function () {
    $community = Community::factory()->create();

    $result = importInto($community, unitsCsv([
        ['Tower A', '101', '1', '850.50', '50', 'P1-1', 'L1'],
        ['Tower A', '102', '1', '', '25', '', ''],
        ['Tower B', '101', '1', '', '25', '', ''],
    ]));

    expect($result)
        ->failed()->toBeFalse()
        ->unitsCreated->toBe(3)
        ->buildingsCreated->toBe(2)
        ->and(Building::query()->pluck('name')->sort()->values()->all())->toBe(['Tower A', 'Tower B'])
        ->and($community->totalUnitFactor())->toBe('100.000000');

    expect(Unit::query()->where('number', '101')->whereRelation('building', 'name', 'Tower A')->sole())
        ->company_id->toBe($community->company_id)
        ->area->toBe('850.50')
        ->parking->toBe('P1-1')
        ->locker->toBe('L1');
});

it('matches existing buildings regardless of letter case', function () {
    $community = Community::factory()->create();
    $building = Building::factory()->for($community)->create(['name' => 'Tower A']);

    $result = importInto($community, unitsCsv([['tower a', '101', '', '', '', '', '']]));

    expect($result->buildingsCreated)->toBe(0)
        ->and(Unit::sole()->building_id)->toBe($building->id);
});

it('imports units without a building', function () {
    $community = Community::factory()->create();

    importInto($community, unitsCsv([['', '12 Maple Lane', '', '', '', '', '']]));

    expect(Unit::sole()->building_id)->toBeNull();
});

it('imports a hundred units', function () {
    $community = Community::factory()->create();
    $rows = array_map(fn (int $i) => ['Tower A', (string) (100 + $i), '1', '', '', '', ''], range(1, 100));

    $result = importInto($community, unitsCsv($rows));

    expect($result->unitsCreated)->toBe(100)
        ->and($community->units()->count())->toBe(100);
});

it('reports invalid rows by row number and imports nothing', function () {
    $community = Community::factory()->create();

    $result = importInto($community, unitsCsv([
        ['Tower A', '101', '1', '', '', '', ''],
        ['Tower A', '', '1', '', '', '', ''],
        ['Tower A', '103', 'first', '', '150', '', ''],
    ]));

    expect($result->failed())->toBeTrue()
        ->and(array_keys($result->errors))->toBe([3, 4])
        ->and($result->errors[3])->toBe(['The number field is required.'])
        ->and($result->errors[4])->toContain('The floor field must be an integer.', 'The unit factor field must not be greater than 100.')
        ->and(Unit::count())->toBe(0)
        ->and(Building::count())->toBe(0);
});

it('rejects units repeated in the file', function () {
    $community = Community::factory()->create();

    $result = importInto($community, unitsCsv([
        ['Tower A', '101', '', '', '', '', ''],
        ['TOWER A', '101', '', '', '', '', ''],
    ]));

    expect($result->errors)->toBe([3 => ['Unit 101 is also on row 2.']])
        ->and(Unit::count())->toBe(0);
});

it('rejects units that already exist in the community', function () {
    $community = Community::factory()->create();
    $building = Building::factory()->for($community)->create(['name' => 'Tower A']);
    Unit::factory()->inBuilding($building)->create(['number' => '101']);

    $result = importInto($community, unitsCsv([['Tower A', '101', '', '', '', '', '']]));

    expect($result->errors)->toBe([2 => ['Unit 101 already exists in this community.']]);
});

it('skips blank rows but keeps row numbers accurate', function () {
    $community = Community::factory()->create();

    $result = importInto($community, unitsCsv([
        ['Tower A', '101', '', '', '', '', ''],
        ['', '', '', '', '', '', ''],
        ['Tower A', '', '2', '', '', '', ''],
    ]));

    expect(array_keys($result->errors))->toBe([4]);
});

it('rejects a file without unit rows', function () {
    $community = Community::factory()->create();

    $result = importInto($community, unitsCsv([]));

    expect($result->errors)->toBe([1 => ['The file has no unit rows.']]);
});

it('imports Excel files', function () {
    $community = Community::factory()->create();

    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([
        ['Building', 'Number', 'Floor', 'Unit Factor'],
        ['Tower A', 101, 1, 0.5],
    ]);
    $path = sys_get_temp_dir().'/'.uniqid('units-', true).'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    $result = importInto($community, new UploadedFile($path, 'units.xlsx', null, null, true));
    unlink($path);

    expect($result->unitsCreated)->toBe(1)
        ->and(Unit::sole())
        ->number->toBe('101')
        ->unit_factor->toBe('0.500000');
});
