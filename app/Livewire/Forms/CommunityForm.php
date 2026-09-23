<?php

namespace App\Livewire\Forms;

use App\Concerns\CommunityValidationRules;
use App\Enums\AreaUnit;
use App\Enums\CommunityType;
use App\Models\Community;
use Illuminate\Contracts\Validation\ValidationRule;
use Livewire\Form;

class CommunityForm extends Form
{
    use CommunityValidationRules;

    public string $name = '';

    public string $type = CommunityType::Condominium->value;

    public string $address_line_1 = '';

    public string $address_line_2 = '';

    public string $city = '';

    public string $region = '';

    public string $postal_code = '';

    public string $country = 'CA';

    public string $timezone = 'America/Toronto';

    public string $currency = 'CAD';

    public string $area_unit = AreaUnit::SquareFeet->value;

    public function fillFrom(Community $community): void
    {
        $this->name = $community->name;
        $this->type = $community->type->value;
        $this->address_line_1 = (string) $community->address_line_1;
        $this->address_line_2 = (string) $community->address_line_2;
        $this->city = (string) $community->city;
        $this->region = (string) $community->region;
        $this->postal_code = (string) $community->postal_code;
        $this->country = $community->country;
        $this->timezone = $community->timezone;
        $this->currency = $community->currency;
        $this->area_unit = $community->area_unit->value;
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function rules(): array
    {
        return $this->communityRules();
    }

    /**
     * Validate the form and return the attributes to save, with blank optional fields stored as null.
     *
     * @return array<string, mixed>
     */
    public function validatedAttributes(): array
    {
        $this->country = strtoupper(trim($this->country));
        $this->currency = strtoupper(trim($this->currency));

        return array_map(
            fn (mixed $value) => $value === '' ? null : $value,
            $this->validate(),
        );
    }
}
