<?php

namespace App\Livewire\Residents;

use App\Enums\ResidencyType;
use App\Models\Community;
use App\Models\Residency;
use App\Models\Resident;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Residents')]
class Index extends Component
{
    use WithPagination;

    public Community $community;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: 'current')]
    public string $status = 'current';

    public function mount(): void
    {
        $this->authorize('viewAny', [Resident::class, $this->community]);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'type', 'status'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, Resident>
     */
    #[Computed]
    public function residents(): LengthAwarePaginator
    {
        $residents = Resident::query()
            ->whereHas('residencies', fn (Builder $query) => $this->filterResidencies($query))
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';

                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhereHas('residencies', fn (Builder $query) => $query
                        ->where('community_id', $this->community->id)
                        ->whereHas('unit', fn (Builder $query) => $query->where('number', 'like', $term))));
            })
            ->orderBy('name')
            ->paginate(50);

        // Show only the residencies that matched, loaded in one query for the whole page.
        $residencies = Residency::query()->whereIn('resident_id', $residents->pluck('id'))->with('unit.building');
        $this->filterResidencies($residencies);
        $residenciesByResident = $residencies->get()->groupBy('resident_id');

        foreach ($residents as $resident) {
            $resident->setRelation('residencies', $residenciesByResident->get($resident->id) ?? new Collection);
        }

        return $residents;
    }

    /**
     * Residencies in this community that match the status and type filters.
     *
     * @param  Builder<Residency>  $query
     */
    private function filterResidencies(Builder $query): void
    {
        $query->where('community_id', $this->community->id);

        match ($this->status) {
            'current' => $query->active(),
            'past' => $query->past(),
            default => null,
        };

        if ($type = ResidencyType::tryFrom($this->type)) {
            $query->where('type', $type);
        }
    }

    public function unitLabel(Residency $residency): string
    {
        $unit = $residency->unit;

        return $unit->building === null ? $unit->number : $unit->building->name.' · '.$unit->number;
    }

    public function render(): View
    {
        return view('livewire.residents.index');
    }
}
