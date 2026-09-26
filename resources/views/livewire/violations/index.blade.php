<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ $this->isStaff() ? __('Violations') : __('Bylaw notices') }}</flux:heading>
            <flux:subheading>{{ $community->name }}</flux:subheading>
        </div>
        @if ($this->canReport())
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Report violation') }}</flux:button>
        @endif
    </div>

    <flux:radio.group wire:model.live="status" variant="segmented" class="max-w-md">
        <flux:radio value="open" :label="__('Open')" />
        <flux:radio value="resolved" :label="__('Resolved')" />
        <flux:radio value="dismissed" :label="__('Dismissed')" />
        <flux:radio value="all" :label="__('All')" />
    </flux:radio.group>

    @if ($this->violations->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('Nothing here') }}</flux:heading>
        </div>
    @else
        <flux:table :paginate="$this->violations">
            <flux:table.columns>
                <flux:table.column>{{ __('Violation') }}</flux:table.column>
                <flux:table.column>{{ __('Unit') }}</flux:table.column>
                <flux:table.column>{{ __('Observed') }}</flux:table.column>
                <flux:table.column>{{ __('Stage') }}</flux:table.column>
                <flux:table.column>{{ __('Next step') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->violations as $violation)
                    <flux:table.row :key="$violation->id">
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('communities.violations.show', [$community, $violation])" wire:navigate>{{ $violation->rule->title }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $violation->unit->label() }}</flux:table.cell>
                        <flux:table.cell>{{ App\Support\LocalTime::display($violation->observed_at, $community) }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $violation->stage?->label() }}
                            @if ($violation->fines_issued > 0)
                                <flux:text size="sm">{{ trans_choice(':count fine|:count fines', $violation->fines_issued) }}</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $violation->next_action_on?->toFormattedDateString() ?? ($violation->isOpen() ? __('Board review') : '—') }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$violation->status->color()">{{ $violation->status->label() }}</flux:badge></flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    @if ($this->isStaff())
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('Rules') }}</flux:heading>
                @if ($this->canManageRules())
                    <flux:button size="sm" icon="plus" wire:click="editRule">{{ __('Add rule') }}</flux:button>
                @endif
            </div>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Rule') }}</flux:table.column>
                    <flux:table.column>{{ __('Time to fix') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Fine') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->rules as $rule)
                        <flux:table.row :key="'rule-'.$rule->id">
                            <flux:table.cell variant="strong">
                                {{ $rule->title }}
                                @unless ($rule->is_active)
                                    <flux:badge size="sm">{{ __('Inactive') }}</flux:badge>
                                @endunless
                                <flux:text size="sm">{{ $rule->reference }}</flux:text>
                            </flux:table.cell>
                            <flux:table.cell>{{ trans_choice(':count day|:count days', $rule->cure_days) }}</flux:table.cell>
                            <flux:table.cell align="end">
                                {{ $rule->fine_cents === null ? __('No fine') : App\Support\Finance\Money::of($rule->fine_cents)->format().' × '.$rule->max_fines }}
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                @can('update', $rule)
                                    <flux:button size="sm" variant="ghost" wire:click="editRule({{ $rule->id }})">{{ __('Edit') }}</flux:button>
                                    <flux:button size="sm" variant="ghost" wire:click="toggleRule({{ $rule->id }})">{{ $rule->is_active ? __('Retire') : __('Reinstate') }}</flux:button>
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif

    @if ($this->canReport())
    <flux:modal name="violation-form" class="w-full max-w-lg">
        <form wire:submit="report" class="space-y-5">
            <flux:heading size="lg">{{ __('Report violation') }}</flux:heading>
            <flux:select wire:model="violation_rule_id" :label="__('Rule')">
                <flux:select.option value="">{{ __('Choose a rule') }}</flux:select.option>
                @foreach ($this->rules->where('is_active', true) as $rule)
                    <flux:select.option :value="$rule->id">{{ $rule->title }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model="unit_id" :label="__('Unit')">
                <flux:select.option value="">{{ __('Choose a unit') }}</flux:select.option>
                @foreach ($this->units as $unit)
                    <flux:select.option :value="$unit->id">{{ $unit->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="location" :label="__('Where (optional)')" />
            <flux:textarea wire:model="description" :label="__('What was observed')" rows="3" required />
            <flux:input type="file" wire:model="photos" :label="__('Photos (optional)')" accept="image/*" multiple />
            <flux:error name="photos.*" />
            <flux:text size="sm">{{ __('A courtesy notice goes to the unit\'s owners as soon as you report.') }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Report and notify') }}</flux:button>
            </div>
        </form>
    </flux:modal>
    @endif

    @if ($this->canManageRules())
    <flux:modal name="rule-form" class="w-full max-w-md">
        <form wire:submit="saveRule" class="space-y-5">
            <flux:heading size="lg">{{ $editingRuleId ? __('Edit rule') : __('Add rule') }}</flux:heading>
            <flux:input wire:model="rule_title" :label="__('Rule')" :placeholder="__('e.g. No items stored on balconies')" required />
            <flux:input wire:model="rule_reference" :label="__('Bylaw reference (optional)')" :placeholder="__('e.g. Rules, s. 12')" />
            <div class="grid grid-cols-3 gap-4">
                <flux:input wire:model="rule_cure_days" type="number" min="1" :label="__('Days to fix')" required />
                <flux:input wire:model="rule_fine" :label="__('Fine')" placeholder="0.00" inputmode="decimal" />
                <flux:input wire:model="rule_max_fines" type="number" min="1" :label="__('Max fines')" required />
            </div>
            <flux:text size="sm">{{ __('Courtesy notice → warning → fine, each after the days to fix. Leave the fine empty to stop at the warning.') }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
    @endif
</section>
