<?php

namespace App\Enums;

use App\Models\EmergencyContact;
use App\Models\Pet;
use App\Models\Resident;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The small lists kept for each resident. Each defines its fields and rules for one shared editing component.
 */
enum ResidentRecord: string
{
    case Vehicles = 'vehicles';
    case Pets = 'pets';
    case EmergencyContacts = 'emergency-contacts';

    public function title(): string
    {
        return match ($this) {
            self::Vehicles => __('Vehicles'),
            self::Pets => __('Pets'),
            self::EmergencyContacts => __('Emergency contacts'),
        };
    }

    public function singular(): string
    {
        return match ($this) {
            self::Vehicles => __('vehicle'),
            self::Pets => __('pet'),
            self::EmergencyContacts => __('emergency contact'),
        };
    }

    /**
     * Field names with their labels, in display order.
     *
     * @return array<string, string>
     */
    public function fields(): array
    {
        return match ($this) {
            self::Vehicles => ['plate' => __('Plate'), 'make' => __('Make'), 'model' => __('Model'), 'colour' => __('Colour')],
            self::Pets => ['name' => __('Name'), 'species' => __('Species'), 'breed' => __('Breed')],
            self::EmergencyContacts => ['name' => __('Name'), 'relationship' => __('Relationship'), 'phone' => __('Phone')],
        };
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return match ($this) {
            self::Vehicles => [
                'plate' => ['required', 'string', 'max:20'],
                'make' => ['nullable', 'string', 'max:255'],
                'model' => ['nullable', 'string', 'max:255'],
                'colour' => ['nullable', 'string', 'max:255'],
            ],
            self::Pets => [
                'name' => ['required', 'string', 'max:255'],
                'species' => ['required', 'string', 'max:255'],
                'breed' => ['nullable', 'string', 'max:255'],
            ],
            self::EmergencyContacts => [
                'name' => ['required', 'string', 'max:255'],
                'relationship' => ['nullable', 'string', 'max:255'],
                'phone' => ['required', 'string', 'max:50'],
            ],
        };
    }

    /**
     * @return HasMany<Vehicle, Resident>|HasMany<Pet, Resident>|HasMany<EmergencyContact, Resident>
     */
    public function relation(Resident $resident): HasMany
    {
        return match ($this) {
            self::Vehicles => $resident->vehicles(),
            self::Pets => $resident->pets(),
            self::EmergencyContacts => $resident->emergencyContacts(),
        };
    }
}
