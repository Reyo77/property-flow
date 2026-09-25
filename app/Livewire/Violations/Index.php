<?php

namespace App\Livewire\Violations;

use App\Actions\Violations\ReportViolation;
use App\Enums\ResidencyType;
use App\Enums\ViolationStatus;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\Unit;
use App\Models\Violation;
use App\Models\ViolationRule;
use App\Support\Finance\Money;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Staff: every violation, reporting new ones, and the rule library. Owners: the notices
 * issued against their own units.
 */
#[Title('Violations')]
class Index extends Component
{
    use InteractsWithCurrentUser, WithFileUploads, WithPagination;

    public Community $community;

    #[Url]
    public string $status = 'open';

    public string $violation_rule_id = '';

    public string $unit_id = '';

    public string $description = '';

    public string $location = '';

    /**
     * @var list<TemporaryUploadedFile>
     */
    public array $photos = [];

    public ?int $editingRuleId = null;

    public string $rule_title = '';

    public string $rule_reference = '';

    public int $rule_cure_days = 14;

    public string $rule_fine = '';

    public int $rule_max_fines = 3;

    public function mount(): void
    {
        $this->authorize('viewAny', [Violation::class, $this->community]);
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function isStaff(): bool
    {
        return $this->currentUser()->can('viewAny', [ViolationRule::class, $this->community]);
    }

    #[Computed]
    public function canReport(): bool
    {
        return $this->currentUser()->can('create', [Violation::class, $this->community]);
    }

    #[Computed]
    public function canManageRules(): bool
    {
        return $this->currentUser()->can('create', [ViolationRule::class, $this->community]);
    }

    /**
     * @return LengthAwarePaginator<int, Violation>
     */
    #[Computed]
    public function violations(): LengthAwarePaginator
    {
        $query = $this->community->violations()->with(['rule', 'unit.building'])->latest('observed_at');

        if (ViolationStatus::tryFrom($this->status) !== null) {
            $query->where('status', $this->status);
        }

        if (! $this->isStaff()) {
            $residentId = $this->currentUser()->resident->id ?? 0;
            $query->whereHas('unit.residencies', fn (Builder $residencies) => $residencies->active()->where('resident_id', $residentId)->where('type', ResidencyType::Owner));
        }

        return $query->paginate(25);
    }

    /**
     * @return Collection<int, ViolationRule>
     */
    #[Computed]
    public function rules(): Collection
    {
        return $this->community->violationRules()->orderByDesc('is_active')->orderBy('title')->get();
    }

    /**
     * @return Collection<int, Unit>
     */
    #[Computed]
    public function units(): Collection
    {
        return $this->community->units()->with('building')->orderBy('number')->get();
    }

    public function create(): void
    {
        $this->authorize('create', [Violation::class, $this->community]);

        $this->resetValidation();
        $this->reset('violation_rule_id', 'unit_id', 'description', 'location', 'photos');

        Flux::modal('violation-form')->show();
    }

    public function report(ReportViolation $reportViolation): void
    {
        $this->authorize('create', [Violation::class, $this->community]);

        $validated = $this->validate([
            'violation_rule_id' => ['required', 'integer', Rule::exists(ViolationRule::class, 'id')->where('community_id', $this->community->id)->where('is_active', true)],
            'unit_id' => ['required', 'integer', Rule::exists(Unit::class, 'id')->where('community_id', $this->community->id)->withoutTrashed()],
            'description' => ['required', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:255'],
            'photos' => ['array', 'max:6'],
            'photos.*' => ['image', 'max:8192'],
        ]);

        try {
            $violation = $reportViolation->handle(
                $this->community->violationRules()->findOrFail((int) $validated['violation_rule_id']),
                $this->community->units()->findOrFail((int) $validated['unit_id']),
                CarbonImmutable::now(),
                $validated['description'],
                $validated['location'] ?: null,
                $this->photos,
                $this->currentUser(),
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        Flux::modal('violation-form')->close();
        $this->redirectRoute('communities.violations.show', [$this->community, $violation], navigate: true);
    }

    public function editRule(?int $ruleId = null): void
    {
        $this->authorize('create', [ViolationRule::class, $this->community]);

        $rule = $ruleId === null ? null : $this->community->violationRules()->findOrFail($ruleId);

        $this->resetValidation();
        $this->editingRuleId = $rule?->id;
        $this->rule_title = $rule->title ?? '';
        $this->rule_reference = $rule->reference ?? '';
        $this->rule_cure_days = $rule->cure_days ?? 14;
        $this->rule_fine = $rule?->fine_cents !== null ? Money::of($rule->fine_cents)->toDecimal() : '';
        $this->rule_max_fines = $rule->max_fines ?? 3;

        Flux::modal('rule-form')->show();
    }

    public function saveRule(): void
    {
        $this->authorize('create', [ViolationRule::class, $this->community]);

        $validated = $this->validate([
            'rule_title' => ['required', 'string', 'max:255'],
            'rule_reference' => ['nullable', 'string', 'max:255'],
            'rule_cure_days' => ['required', 'integer', 'between:1,365'],
            'rule_fine' => ['nullable', 'string', 'regex:/^[\d,]{1,9}(\.\d{1,2})?$/'],
            'rule_max_fines' => ['required', 'integer', 'between:1,20'],
        ]);

        $attributes = [
            'title' => $validated['rule_title'],
            'reference' => $validated['rule_reference'] ?: null,
            'cure_days' => $validated['rule_cure_days'],
            'fine_cents' => $validated['rule_fine'] ? Money::parse($validated['rule_fine'])->cents : null,
            'max_fines' => $validated['rule_max_fines'],
        ];

        $rule = $this->editingRuleId === null ? null : $this->community->violationRules()->findOrFail($this->editingRuleId);

        if ($rule === null) {
            $rule = new ViolationRule([...$attributes, 'is_active' => true]);
            $rule->forceFill(['company_id' => $this->community->company_id, 'community_id' => $this->community->id])->save();
        } else {
            $rule->update($attributes);
        }

        Flux::modal('rule-form')->close();
        Flux::toast(variant: 'success', text: __('Rule saved.'));
        unset($this->rules);
    }

    public function toggleRule(int $ruleId): void
    {
        $rule = $this->community->violationRules()->findOrFail($ruleId);

        $this->authorize('update', $rule);

        $rule->update(['is_active' => ! $rule->is_active]);
        unset($this->rules);
    }

    public function render(): View
    {
        return view('livewire.violations.index');
    }
}
