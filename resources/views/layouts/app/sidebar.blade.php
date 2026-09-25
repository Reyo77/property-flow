<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <livewire:community-switcher />

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Overview')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    @can('viewAny', App\Models\Community::class)
                        <flux:sidebar.item icon="squares-2x2" :href="route('communities.index')" :current="request()->routeIs('communities.index', 'communities.create')" wire:navigate>
                            {{ __('Communities') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('viewAny', App\Models\User::class)
                        <flux:sidebar.item icon="user-group" :href="route('team.index')" :current="request()->routeIs('team.*')" wire:navigate>
                            {{ __('Team') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('viewAny', App\Models\Vendor::class)
                        <flux:sidebar.item icon="wrench-screwdriver" :href="route('vendors.index')" :current="request()->routeIs('vendors.*')" wire:navigate>
                            {{ __('Vendors') }}
                        </flux:sidebar.item>
                    @endcan
                    @if (auth()->user()->vendor !== null)
                        <flux:sidebar.item icon="clipboard-document-check" :href="route('work-orders.mine')" :current="request()->routeIs('work-orders.mine')" wire:navigate>
                            {{ __('My work orders') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>

                @if ($currentCommunity = app(App\Support\Tenancy\CurrentCommunity::class)->get())
                    <flux:sidebar.group :heading="$currentCommunity->name" class="grid">
                        <flux:sidebar.item icon="building-office-2" :href="route('communities.show', $currentCommunity)" :current="request()->routeIs('communities.show', 'communities.edit')" wire:navigate>
                            {{ __('Overview') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="building-office" :href="route('communities.buildings.index', $currentCommunity)" :current="request()->routeIs('communities.buildings.*')" wire:navigate>
                            {{ __('Buildings') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="home-modern" :href="route('communities.units.index', $currentCommunity)" :current="request()->routeIs('communities.units.*')" wire:navigate>
                            {{ __('Units') }}
                        </flux:sidebar.item>
                        @can('viewAny', [App\Models\Resident::class, $currentCommunity])
                            <flux:sidebar.item icon="users" :href="route('communities.residents.index', $currentCommunity)" :current="request()->routeIs('communities.residents.*')" wire:navigate>
                                {{ __('Residents') }}
                            </flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>

                    <flux:sidebar.group :heading="__('Communication')" class="grid">
                        @can('viewAny', [App\Models\Announcement::class, $currentCommunity])
                            <flux:sidebar.item icon="megaphone" :href="route('communities.announcements.index', $currentCommunity)" :current="request()->routeIs('communities.announcements.*')" wire:navigate>
                                {{ __('Announcements') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('viewAny', [App\Models\Document::class, $currentCommunity])
                            <flux:sidebar.item icon="document" :href="route('communities.documents.index', $currentCommunity)" :current="request()->routeIs('communities.documents.*')" wire:navigate>
                                {{ __('Documents') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('viewAny', [App\Models\Event::class, $currentCommunity])
                            <flux:sidebar.item icon="calendar-days" :href="route('communities.events.index', $currentCommunity)" :current="request()->routeIs('communities.events.*')" wire:navigate>
                                {{ __('Events') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('viewAny', [App\Models\Contact::class, $currentCommunity])
                            <flux:sidebar.item icon="phone" :href="route('communities.phone-book.index', $currentCommunity)" :current="request()->routeIs('communities.phone-book.*')" wire:navigate>
                                {{ __('Phone book') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('viewAny', [App\Models\Amenity::class, $currentCommunity])
                            <flux:sidebar.item icon="calendar-date-range" :href="route('communities.amenities.index', $currentCommunity)" :current="request()->routeIs('communities.amenities.*')" wire:navigate>
                                {{ __('Amenities') }}
                            </flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>

                    <flux:sidebar.group :heading="__('Maintenance')" class="grid">
                        @can('viewAny', [App\Models\ServiceRequest::class, $currentCommunity])
                            <flux:sidebar.item icon="wrench" :href="route('communities.service-requests.index', $currentCommunity)" :current="request()->routeIs('communities.service-requests.*')" wire:navigate>
                                {{ __('Service requests') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('viewAny', [App\Models\Task::class, $currentCommunity])
                            <flux:sidebar.item icon="check-circle" :href="route('communities.tasks.index', $currentCommunity)" :current="request()->routeIs('communities.tasks.*')" wire:navigate>
                                {{ __('Tasks') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('viewAny', [App\Models\Asset::class, $currentCommunity])
                            <flux:sidebar.item icon="cog-6-tooth" :href="route('communities.assets.index', $currentCommunity)" :current="request()->routeIs('communities.assets.*')" wire:navigate>
                                {{ __('Assets') }}
                            </flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>

                    @can('viewAny', [App\Models\Invoice::class, $currentCommunity])
                        <flux:sidebar.group :heading="__('Finance')" class="grid">
                            <flux:sidebar.item icon="chart-pie" :href="route('communities.finance.overview', $currentCommunity)" :current="request()->routeIs('communities.finance.overview', 'communities.units.account')" wire:navigate>
                                {{ __('Overview') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="document-text" :href="route('communities.finance.invoices', $currentCommunity)" :current="request()->routeIs('communities.finance.invoices')" wire:navigate>
                                {{ __('Invoices') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="banknotes" :href="route('communities.finance.payments', $currentCommunity)" :current="request()->routeIs('communities.finance.payments')" wire:navigate>
                                {{ __('Payments') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="receipt-percent" :href="route('communities.finance.bills', $currentCommunity)" :current="request()->routeIs('communities.finance.bills')" wire:navigate>
                                {{ __('Vendor bills') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="arrow-path" :href="route('communities.finance.billing', $currentCommunity)" :current="request()->routeIs('communities.finance.billing')" wire:navigate>
                                {{ __('Billing') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="book-open" :href="route('communities.finance.setup', $currentCommunity)" :current="request()->routeIs('communities.finance.setup')" wire:navigate>
                                {{ __('Accounts & charges') }}
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                    @endcan

                    <flux:sidebar.group :heading="__('Front desk')" class="grid">
                        @can('viewAny', [App\Models\Package::class, $currentCommunity])
                            <flux:sidebar.item icon="bolt" :href="route('communities.front-desk.mode', $currentCommunity)" :current="request()->routeIs('communities.front-desk.*')" wire:navigate>
                                {{ __('Front-desk mode') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="archive-box" :href="route('communities.packages.index', $currentCommunity)" :current="request()->routeIs('communities.packages.*')" wire:navigate>
                                {{ __('Packages') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('viewAny', [App\Models\Visitor::class, $currentCommunity])
                            <flux:sidebar.item icon="user-plus" :href="route('communities.visitors.index', $currentCommunity)" :current="request()->routeIs('communities.visitors.*')" wire:navigate>
                                {{ __('Visitors') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('viewAny', [App\Models\GuestPass::class, $currentCommunity])
                            <flux:sidebar.item icon="ticket" :href="route('communities.guest-passes.index', $currentCommunity)" :current="request()->routeIs('communities.guest-passes.*')" wire:navigate>
                                {{ __('Guest passes') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('viewAny', [App\Models\ParkingPermit::class, $currentCommunity])
                            <flux:sidebar.item icon="truck" :href="route('communities.parking-permits.index', $currentCommunity)" :current="request()->routeIs('communities.parking-permits.*')" wire:navigate>
                                {{ __('Parking permits') }}
                            </flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>

                    <flux:sidebar.group :heading="__('Security')" class="grid">
                        @can('viewAny', [App\Models\IncidentReport::class, $currentCommunity])
                            <flux:sidebar.item icon="exclamation-triangle" :href="route('communities.incident-reports.index', $currentCommunity)" :current="request()->routeIs('communities.incident-reports.*')" wire:navigate>
                                {{ __('Incident reports') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('viewAny', [App\Models\AccessKey::class, $currentCommunity])
                            <flux:sidebar.item icon="key" :href="route('communities.access-keys.index', $currentCommunity)" :current="request()->routeIs('communities.access-keys.*')" wire:navigate>
                                {{ __('Keys') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('viewAny', [App\Models\EntryAuthorization::class, $currentCommunity])
                            <flux:sidebar.item icon="identification" :href="route('communities.entry-authorizations.index', $currentCommunity)" :current="request()->routeIs('communities.entry-authorizations.*')" wire:navigate>
                                {{ __('Entry authorizations') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('viewAny', [App\Models\PatrolRoute::class, $currentCommunity])
                            <flux:sidebar.item icon="map" :href="route('communities.patrol-routes.index', $currentCommunity)" :current="request()->routeIs('communities.patrol-routes.*')" wire:navigate>
                                {{ __('Patrols') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('viewAny', [App\Models\ShiftLogEntry::class, $currentCommunity])
                            <flux:sidebar.item icon="clipboard-document-list" :href="route('communities.shift-log.index', $currentCommunity)" :current="request()->routeIs('communities.shift-log.*')" wire:navigate>
                                {{ __('Shift log') }}
                            </flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <div class="hidden items-center gap-2 px-2 lg:flex">
                <livewire:notification-bell />
            </div>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <livewire:notification-bell />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
