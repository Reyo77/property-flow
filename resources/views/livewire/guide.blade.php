<section class="w-full max-w-5xl space-y-10">
    <div class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-gradient-to-br from-zinc-50 via-white to-white p-6 dark:border-zinc-700 dark:from-zinc-800/60 dark:via-zinc-900 dark:to-zinc-900 sm:p-8">
        <div class="absolute -end-16 -top-16 size-56 rounded-full bg-emerald-500/5 blur-2xl"></div>
        <div class="relative flex flex-wrap items-start gap-4">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                <flux:icon name="book-open" class="size-6" />
            </div>
            <div>
                <flux:heading size="xl" level="1">{{ __('Guide') }}</flux:heading>
                <flux:subheading>{{ __('A quick tour of what PropertyFlow can do, and where to find it.') }}</flux:subheading>
                <flux:badge size="sm" color="blue" class="mt-3">{{ __('Signed in as :role', ['role' => $this->accountLabel]) }}</flux:badge>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __("What's inside") }}</flux:heading>
            <flux:text class="mt-1">{{ __('Every part of running a community, grouped the way you actually use it.') }}</flux:text>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <div class="flex size-10 items-center justify-center rounded-full bg-blue-500/10 text-blue-600 dark:text-blue-400">
                    <flux:icon name="users" class="size-5" />
                </div>
                <flux:heading class="mt-3">{{ __('Residents & units') }}</flux:heading>
                <flux:text class="mt-1">{{ __('The full directory of buildings, units, owners and tenants — plus a self-service portal for residents.') }}</flux:text>
            </div>
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <div class="flex size-10 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                    <flux:icon name="banknotes" class="size-5" />
                </div>
                <flux:heading class="mt-3">{{ __('Money & finance') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Dues, invoices, vendor bills and budgets, with live income-vs-expenses charts instead of a spreadsheet.') }}</flux:text>
            </div>
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <div class="flex size-10 items-center justify-center rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400">
                    <flux:icon name="check-badge" class="size-5" />
                </div>
                <flux:heading class="mt-3">{{ __('Governance & voting') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Weighted online ballots, proxy voting, and a board portal with a real financial snapshot.') }}</flux:text>
            </div>
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <div class="flex size-10 items-center justify-center rounded-full bg-violet-500/10 text-violet-600 dark:text-violet-400">
                    <flux:icon name="wrench" class="size-5" />
                </div>
                <flux:heading class="mt-3">{{ __('Maintenance & operations') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Service requests route to staff or outside vendors, with a trail from reported to resolved.') }}</flux:text>
            </div>
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <div class="flex size-10 items-center justify-center rounded-full bg-cyan-500/10 text-cyan-600 dark:text-cyan-400">
                    <flux:icon name="archive-box" class="size-5" />
                </div>
                <flux:heading class="mt-3">{{ __('Front desk & security') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Packages, visitors, guest passes, parking permits and incident reports, all in reach.') }}</flux:text>
            </div>
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <div class="flex size-10 items-center justify-center rounded-full bg-rose-500/10 text-rose-600 dark:text-rose-400">
                    <flux:icon name="megaphone" class="size-5" />
                </div>
                <flux:heading class="mt-3">{{ __('Communication') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Announcements, events and shared documents that reach the right people.') }}</flux:text>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('Who sees what') }}</flux:heading>
            <flux:text class="mt-1">{{ __('PropertyFlow opens to a different, simpler view depending on your account.') }}</flux:text>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['label' => App\Enums\CompanyRole::CompanyAdmin->label(), 'desc' => __('Sees everything the company owns.')],
                ['label' => App\Enums\CompanyRole::PropertyManager->label(), 'desc' => __('Runs the communities they are assigned.')],
                ['label' => App\Enums\CompanyRole::BoardMember->label(), 'desc' => __('Reviews finances and votes on decisions.')],
                ['label' => App\Enums\CompanyRole::Staff->label(), 'desc' => __('Runs the front desk day to day.')],
                ['label' => __('Resident'), 'desc' => __('Self-service for their own home.')],
                ['label' => __('Vendor'), 'desc' => __('Sees only their assigned work orders.')],
                ['label' => __('Platform admin'), 'desc' => __('Oversees companies at a platform level.')],
            ] as $role)
                <div @class([
                    'rounded-xl border p-4',
                    'border-emerald-400 bg-emerald-500/5 dark:border-emerald-500/60' => $this->accountLabel === $role['label'],
                    'border-zinc-200 dark:border-zinc-700' => $this->accountLabel !== $role['label'],
                ])>
                    <div class="flex items-center justify-between gap-2">
                        <flux:heading size="sm">{{ $role['label'] }}</flux:heading>
                        @if ($this->accountLabel === $role['label'])
                            <flux:badge size="sm" color="green">{{ __('You') }}</flux:badge>
                        @endif
                    </div>
                    <flux:text class="mt-1 text-xs">{{ $role['desc'] }}</flux:text>
                </div>
            @endforeach
        </div>
    </div>

    <div class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('How a community runs') }}</flux:heading>
            <flux:text class="mt-1">{{ __('The same five steps, every time.') }}</flux:text>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ([
                __('Set up buildings, units and a budget.'),
                __('Invite the team and link residents to their unit.'),
                __('Requests, payments and messages flow through one trail.'),
                __('The board reviews real numbers and approves spending.'),
                __('Big decisions go to a community-wide vote.'),
            ] as $index => $step)
                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="flex size-8 items-center justify-center rounded-full border border-emerald-400 text-sm font-semibold text-emerald-600 dark:border-emerald-500/60 dark:text-emerald-400">
                        {{ $index + 1 }}
                    </div>
                    <flux:text class="mt-3 text-sm">{{ $step }}</flux:text>
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-xl border border-dashed border-zinc-300 p-6 text-center dark:border-zinc-600">
        <flux:text>{{ __("Can't find something? Ask your property manager or company admin.") }}</flux:text>
    </div>
</section>
