<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Phone book') }}</flux:heading>
            <flux:subheading>{{ $community->name }}</flux:subheading>
        </div>

        @can('create', [App\Models\Contact::class, $community])
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add contact') }}</flux:button>
        @endcan
    </div>

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search name, title, phone or email')" class="max-w-xs" />

    @if ($this->contacts->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No contacts yet') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Add staff, emergency and vendor contacts residents may need.') }}</flux:text>
        </div>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Category') }}</flux:table.column>
                <flux:table.column>{{ __('Phone') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
                @can('create', [App\Models\Contact::class, $community])
                    <flux:table.column>{{ __('Visible to residents') }}</flux:table.column>
                @endcan
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->contacts as $contact)
                    <flux:table.row :key="$contact->id">
                        <flux:table.cell variant="strong">
                            {{ $contact->name }}
                            @if ($contact->title)
                                <div class="text-xs font-normal text-zinc-500 dark:text-zinc-400">{{ $contact->title }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell><flux:badge size="sm">{{ $contact->category->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell>{{ $contact->phone }}</flux:table.cell>
                        <flux:table.cell>{{ $contact->email }}</flux:table.cell>
                        @can('create', [App\Models\Contact::class, $community])
                            <flux:table.cell>
                                @if ($contact->visible_to_residents)
                                    <flux:badge size="sm" color="green">{{ __('Yes') }}</flux:badge>
                                @else
                                    <flux:badge size="sm">{{ __('No') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                        @endcan
                        <flux:table.cell align="end">
                            @can('update', $contact)
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" :aria-label="__('Actions')" />
                                    <flux:menu>
                                        <flux:menu.item icon="pencil-square" wire:click="edit({{ $contact->id }})">{{ __('Edit') }}</flux:menu.item>
                                        <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $contact->id }})" wire:confirm="{{ __('Delete :name?', ['name' => $contact->name]) }}">{{ __('Delete') }}</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="contact-form" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingContactId ? __('Edit contact') : __('Add contact') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:input wire:model="title" :label="__('Title')" :placeholder="__('e.g. Concierge, Superintendent')" />

            <flux:select wire:model="category" :label="__('Category')">
                <flux:select.option value="">{{ __('Choose a category') }}</flux:select.option>
                @foreach ($this->categories() as $category)
                    <flux:select.option :value="$category->value">{{ $category->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="phone" :label="__('Phone')" />
            <flux:input wire:model="email" :label="__('Email')" type="email" />
            <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" />
            <flux:checkbox wire:model="visible_to_residents" :label="__('Visible to residents')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
