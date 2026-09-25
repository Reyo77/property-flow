<flux:navbar scrollable class="-mb-px border-b border-zinc-200 dark:border-zinc-700">
    <flux:navbar.item :href="route('communities.finance.overview', $community)" :current="request()->routeIs('communities.finance.overview')" wire:navigate>{{ __('Overview') }}</flux:navbar.item>
    <flux:navbar.item :href="route('communities.finance.invoices', $community)" :current="request()->routeIs('communities.finance.invoices')" wire:navigate>{{ __('Invoices') }}</flux:navbar.item>
    <flux:navbar.item :href="route('communities.finance.payments', $community)" :current="request()->routeIs('communities.finance.payments')" wire:navigate>{{ __('Payments') }}</flux:navbar.item>
    <flux:navbar.item :href="route('communities.finance.bills', $community)" :current="request()->routeIs('communities.finance.bills')" wire:navigate>{{ __('Vendor bills') }}</flux:navbar.item>
    <flux:navbar.item :href="route('communities.finance.billing', $community)" :current="request()->routeIs('communities.finance.billing')" wire:navigate>{{ __('Billing') }}</flux:navbar.item>
    <flux:navbar.item :href="route('communities.finance.budget', $community)" :current="request()->routeIs('communities.finance.budget')" wire:navigate>{{ __('Budget') }}</flux:navbar.item>
    <flux:navbar.item :href="route('communities.finance.reports', $community)" :current="request()->routeIs('communities.finance.reports')" wire:navigate>{{ __('Reports') }}</flux:navbar.item>
    <flux:navbar.item :href="route('communities.finance.reconciliation', $community)" :current="request()->routeIs('communities.finance.reconciliation*')" wire:navigate>{{ __('Reconciliation') }}</flux:navbar.item>
    <flux:navbar.item :href="route('communities.finance.setup', $community)" :current="request()->routeIs('communities.finance.setup')" wire:navigate>{{ __('Accounts & charges') }}</flux:navbar.item>
</flux:navbar>
