<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Community;
use App\Models\Company;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A realistic company for trying the app: sign in as demo@propertyflow.test / password.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::factory()->create(['name' => 'Maple Property Management']);

        User::factory()->for($company)->companyAdmin()->create([
            'name' => 'Demo Admin',
            'email' => 'demo@propertyflow.test',
        ]);

        $this->seedCondo($company);
        $this->seedHoa($company);
    }

    /**
     * A two-tower condo whose unit factors add up to exactly 100%.
     */
    private function seedCondo(Company $company): void
    {
        $community = Community::factory()->for($company)->create(['name' => 'Harbour Towers']);

        $towers = collect(['North Tower', 'South Tower'])->map(
            fn (string $name) => Building::factory()->for($community)->create(['name' => $name, 'floors' => 15]),
        );

        $unitsPerFloor = 4;
        $floors = 15;
        $unitCount = $towers->count() * $floors * $unitsPerFloor;
        $factor = bcdiv('100', (string) $unitCount, 6);
        $remainder = bcsub('100', bcmul($factor, (string) ($unitCount - 1), 6), 6);
        $created = 0;

        foreach ($towers as $tower) {
            foreach (range(1, $floors) as $floor) {
                foreach (range(1, $unitsPerFloor) as $position) {
                    $created++;

                    Unit::factory()->inBuilding($tower)->create([
                        'number' => sprintf('%d%02d', $floor, $position),
                        'floor' => $floor,
                        'unit_factor' => $created === $unitCount ? $remainder : $factor,
                        'parking' => 'P'.(($created % 3) + 1).'-'.$created,
                    ]);
                }
            }
        }
    }

    /**
     * An HOA of single-family homes, without buildings.
     */
    private function seedHoa(Company $company): void
    {
        $community = Community::factory()->for($company)->hoa()->create(['name' => 'Maple Grove HOA']);

        foreach (range(1, 40) as $lot) {
            Unit::factory()->for($community)->create([
                'number' => sprintf('%d Maple Grove Lane', $lot * 2),
                'floor' => null,
                'area' => null,
            ]);
        }
    }
}
