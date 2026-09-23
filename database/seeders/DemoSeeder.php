<?php

namespace Database\Seeders;

use App\Enums\CompanyRole;
use App\Models\Building;
use App\Models\Community;
use App\Models\Company;
use App\Models\EmergencyContact;
use App\Models\Pet;
use App\Models\Residency;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

/**
 * A realistic company for trying the app. Every demo login uses the password "password":
 * demo@ (company admin), manager@ (Harbour Towers only), board@, staff@ and resident@propertyflow.test.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::factory()->create(['name' => 'Maple Property Management']);

        User::factory()->for($company)->companyAdmin()->create(['name' => 'Demo Admin', 'email' => 'demo@propertyflow.test']);

        $condo = $this->seedCondo($company);
        $hoa = $this->seedHoa($company);

        $this->seedTeam($company, $condo, $hoa);
        $this->seedResidents($company, $condo, $hoa);
    }

    /**
     * A two-tower condo whose unit factors add up to exactly 100%.
     */
    private function seedCondo(Company $company): Community
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

        return $community;
    }

    /**
     * An HOA of single-family homes, without buildings.
     */
    private function seedHoa(Company $company): Community
    {
        $community = Community::factory()->for($company)->hoa()->create(['name' => 'Maple Grove HOA']);

        foreach (range(1, 40) as $lot) {
            Unit::factory()->for($community)->create([
                'number' => sprintf('%d Maple Grove Lane', $lot * 2),
                'floor' => null,
                'area' => null,
            ]);
        }

        return $community;
    }

    private function seedTeam(Company $company, Community $condo, Community $hoa): void
    {
        $manager = User::factory()->for($company)->withRole(CompanyRole::PropertyManager)
            ->create(['name' => 'Priya Manager', 'email' => 'manager@propertyflow.test']);
        $manager->communities()->attach($condo);

        $board = User::factory()->for($company)->withRole(CompanyRole::BoardMember)
            ->create(['name' => 'Ben Board', 'email' => 'board@propertyflow.test']);
        $board->communities()->attach($condo);

        $staff = User::factory()->for($company)->withRole(CompanyRole::Staff)
            ->create(['name' => 'Sam Concierge', 'email' => 'staff@propertyflow.test']);
        $staff->communities()->attach([$condo->id, $hoa->id]);
    }

    /**
     * Residents in about 80% of units, with a few tenants, past residents, vehicles, pets and emergency contacts.
     */
    private function seedResidents(Company $company, Community $condo, Community $hoa): void
    {
        $units = Unit::query()->whereIn('community_id', [$condo->id, $hoa->id])->orderBy('id')->get();

        foreach ($units as $index => $unit) {
            if ($index % 5 === 4) {
                continue;
            }

            $owner = $index === 0
                ? Resident::factory()->for($company)->withLogin()->create(['name' => 'Rita Resident', 'email' => 'resident@propertyflow.test'])
                : Resident::factory()->for($company)->create();

            Residency::factory()->for($unit)->for($owner)->create([
                'moved_in_on' => now()->subMonths(6 + $index % 60)->toDateString(),
            ]);

            if ($index % 7 === 0) {
                Residency::factory()->for($unit)->tenant()->create(['is_primary' => false]);
            }

            if ($index % 9 === 0) {
                Residency::factory()->for($unit)->movedOut()->create([
                    'is_primary' => false,
                    'moved_in_on' => now()->subYears(5)->toDateString(),
                    'moved_out_on' => now()->subYears(1)->toDateString(),
                ]);
            }

            if ($index % 3 === 0) {
                Vehicle::factory()->for($owner)->create();
            }

            if ($index % 4 === 0) {
                Pet::factory()->for($owner)->create();
                EmergencyContact::factory()->for($owner)->create();
            }
        }
    }
}
