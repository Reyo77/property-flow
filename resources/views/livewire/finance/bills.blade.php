<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Vendor bills') }}</flux:heading>
            <flux:subheading>{{ $community->name }} · {{ __(':amount approved and unpaid', ['amount' => $this->outstanding->format()]) }}</flux:subheading>
        </div>

        @if ($this->canCreate())
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Enter bill') }}</flux:button>
        @endif
    </div>

    @include('livewire.finance.partials.nav')

    <flux:select wire:model.live="status" class="max-w-56">
        <flux:select.option value="">{{ __('All bills') }}</flux:select.option>
        @foreach (App\Enums\VendorBillStatus::cases() as $option)
            <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
        @endforeach
    </flux:select>

    @if ($this->bills->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No vendor bills') }}</flux:heading>
        </div>
    @else
        <flux:table :paginate="$this->bills">
            <flux:table.columns>
                <flux:table.column>{{ __('Bill') }}</flux:table.column>
                <flux:table.column>{{ __('Vendor') }}</flux:table.column>
                <flux:table.column>{{ __('Account') }}</flux:table.column>
                <flux:table.column>{{ __('Due') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->bills as $bill)
                    <flux:table.row :key="$bill->id">
                        <flux:table.cell variant="strong">
                            {{ $bill->displayNumber() }}
                            <flux:text size="sm">{{ $bill->description }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $bill->vendor->name }}
                            @if ($bill->vendor_reference)
                                <flux:text size="sm">{{ __('Their ref. :ref', ['ref' => $bill->vendor_reference]) }}</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $bill->account->label() }}</flux:table.cell>
                        <flux:table.cell>{{ $bill->due_on->toFormattedDateString() }}</flux:table.cell>
                        <flux:table.cell align="end">
                            {{ App\Support\Finance\Money::of($bill->amount_cents)->format() }}
                            @if ($bill->status === App\Enums\VendorBillStatus::Pending && $bill->needsLargeBillApproval())
                                <flux:text size="sm">{{ __('Needs board') }}</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$bill->status->color()">{{ $bill->status->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell align="end">
                            @can('approve', $bill)
                                <flux:button size="sm" variant="ghost" wire:click="review({{ $bill->id }})">{{ __('Review') }}</flux:button>
                            @endcan
                            @can('pay', $bill)
                                <flux:button size="sm" variant="ghost" wire:click="startPayment({{ $bill->id }})">{{ __('Mark paid') }}</flux:button>
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="bill-form" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ __('Enter vendor bill') }}</flux:heading>

            <flux:select wire:model="vendor_id" :label="__('Vendor')">
                <flux:select.option value="">{{ __('Choose a vendor') }}</flux:select.option>
                @foreach ($this->vendors as $vendor)
                    <flux:select.option :value="$vendor->id">{{ $vendor->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model="account_id" :label="__('Expense account')">
                <flux:select.option value="">{{ __('Choose an account') }}</flux:select.option>
                @foreach ($this->expenseAccounts as $account)
                    <flux:select.option :value="$account->id">{{ $account->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="description" :label="__('What it is for')" required />
            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="amount" :label="__('Amount')" placeholder="0.00" inputmode="decimal" required />
                <flux:input wire:model="vendor_reference" :label="__('Vendor\'s invoice # (optional)')" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="billed_on" type="date" :label="__('Bill date')" required />
                <flux:input wire:model="due_on" type="date" :label="__('Due')" required />
            </div>
            <flux:text size="sm">{{ __('Bills up to :limit can be approved by a manager; above that, by a board member.', ['limit' => App\Support\Finance\Money::of($community->bill_approval_limit_cents)->format()]) }}</flux:text>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Submit for approval') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="bill-decision" class="w-full max-w-md">
        @if ($this->actingOn)
            <div class="space-y-5">
                <flux:heading size="lg">{{ __('Review :number', ['number' => $this->actingOn->displayNumber()]) }}</flux:heading>
                <flux:text>{{ $this->actingOn->vendor->name }} · {{ $this->actingOn->description }} · <strong>{{ App\Support\Finance\Money::of($this->actingOn->amount_cents)->format() }}</strong></flux:text>
                <flux:textarea wire:model="decision_notes" :label="__('Notes (required to reject)')" rows="2" />
                <div class="flex justify-end gap-2">
                    <flux:button variant="danger" wire:click="reject">{{ __('Reject') }}</flux:button>
                    <flux:button variant="primary" wire:click="approve">{{ __('Approve') }}</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <flux:modal name="bill-payment" class="w-full max-w-md">
        @if ($this->actingOn)
            <form wire:submit="pay" class="space-y-5">
                <flux:heading size="lg">{{ __('Pay :number', ['number' => $this->actingOn->displayNumber()]) }}</flux:heading>
                <flux:text>{{ $this->actingOn->vendor->name }} · <strong>{{ App\Support\Finance\Money::of($this->actingOn->amount_cents)->format() }}</strong></flux:text>
                <div class="grid grid-cols-2 gap-4">
                    <flux:select wire:model="payment_method" :label="__('Method')">
                        @foreach (App\Enums\PaymentMethod::manual() as $option)
                            <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="paid_on" type="date" :label="__('Paid on')" required />
                </div>
                <flux:input wire:model="payment_reference" :label="__('Cheque # or reference (optional)')" />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Mark paid') }}</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</section>
