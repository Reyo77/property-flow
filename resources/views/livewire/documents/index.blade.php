<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Documents') }}</flux:heading>
            <flux:subheading>{{ $community->name }}</flux:subheading>
        </div>

        <div class="flex gap-2">
            @can('create', [App\Models\DocumentFolder::class, $community])
                <flux:button icon="folder-plus" wire:click="createFolder">{{ __('New folder') }}</flux:button>
            @endcan
            @can('create', [App\Models\Document::class, $community])
                <flux:button variant="primary" icon="arrow-up-tray" wire:click="createDocument">{{ __('Upload') }}</flux:button>
            @endcan
        </div>
    </div>

    <nav class="flex flex-wrap items-center gap-1 text-sm">
        <button type="button" wire:click="openFolder(null)" class="text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-100">
            {{ __('Documents') }}
        </button>
        @foreach ($this->breadcrumbs as $crumb)
            <flux:icon name="chevron-right" class="size-3.5 text-zinc-400" />
            <button type="button" wire:click="openFolder({{ $crumb->id }})" class="text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-100">
                {{ $crumb->name }}
            </button>
        @endforeach
    </nav>

    @if ($this->subfolders->isEmpty() && $this->documents->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('Nothing here yet') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Upload a document or create a folder.') }}</flux:text>
        </div>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Visibility') }}</flux:table.column>
                <flux:table.column>{{ __('Updated') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->subfolders as $folder)
                    <flux:table.row :key="'folder-'.$folder->id">
                        <flux:table.cell variant="strong">
                            <button type="button" wire:click="openFolder({{ $folder->id }})" class="flex items-center gap-2">
                                <flux:icon name="folder" class="size-4 text-zinc-400" />
                                {{ $folder->name }}
                            </button>
                        </flux:table.cell>
                        <flux:table.cell><flux:badge size="sm">{{ $folder->visibility->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell>{{ $folder->updated_at?->toFormattedDateString() }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @can('delete', $folder)
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="deleteFolder({{ $folder->id }})" wire:confirm="{{ __('Delete :name?', ['name' => $folder->name]) }}" :aria-label="__('Delete')" />
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach

                @foreach ($this->documents as $document)
                    <flux:table.row :key="'document-'.$document->id">
                        <flux:table.cell variant="strong">
                            <div class="flex items-center gap-2">
                                <flux:icon name="document" class="size-4 text-zinc-400" />
                                @if ($document->currentVersion)
                                    <flux:link :href="route('communities.documents.download', [$community, $document])">{{ $document->title }}</flux:link>
                                @else
                                    {{ $document->title }}
                                @endif
                            </div>
                            @if ($document->currentVersion)
                                <div class="ms-6 text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $document->currentVersion->original_filename }} · {{ $document->currentVersion->humanSize() }}
                                    @if ($document->currentVersion->version_number > 1)
                                        · {{ __('v:number', ['number' => $document->currentVersion->version_number]) }}
                                    @endif
                                </div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell><flux:badge size="sm">{{ $document->visibility->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell>{{ $document->updated_at?->toFormattedDateString() }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @can('update', $document)
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" :aria-label="__('Actions')" />
                                    <flux:menu>
                                        <flux:menu.item icon="pencil-square" wire:click="editDocument({{ $document->id }})">{{ __('Edit') }}</flux:menu.item>
                                        <flux:menu.item icon="arrow-up-tray" wire:click="openNewVersion({{ $document->id }})">{{ __('Upload new version') }}</flux:menu.item>
                                        <flux:menu.item icon="trash" variant="danger" wire:click="deleteDocument({{ $document->id }})" wire:confirm="{{ __('Delete :title?', ['title' => $document->title]) }}">{{ __('Delete') }}</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="folder-form" class="w-full max-w-md">
        <form wire:submit="saveFolder" class="space-y-5">
            <flux:heading size="lg">{{ __('New folder') }}</flux:heading>

            <flux:input wire:model="folder_name" :label="__('Name')" required />

            <flux:select wire:model="folder_visibility" :label="__('Visibility')">
                @foreach (App\Enums\DocumentVisibility::cases() as $option)
                    <flux:select.option :value="$option->value" :description="$option->description()">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="document-form" class="w-full max-w-md">
        <form wire:submit="saveDocument" class="space-y-5">
            <flux:heading size="lg">{{ $editingDocumentId ? __('Edit document') : __('Upload document') }}</flux:heading>

            <flux:input wire:model="title" :label="__('Title')" required />

            <flux:select wire:model="visibility" :label="__('Visibility')">
                @foreach (App\Enums\DocumentVisibility::cases() as $option)
                    <flux:select.option :value="$option->value" :description="$option->description()">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input type="file" wire:model="file" :label="$editingDocumentId ? __('Replace file (optional)') : __('File')" :description="__('PDF, Word, Excel, CSV, text or image, up to 20 MB.')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="file,saveDocument">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="new-version-form" class="w-full max-w-md">
        <form wire:submit="saveNewVersion" class="space-y-5">
            <flux:heading size="lg">{{ __('Upload a new version') }}</flux:heading>
            <flux:text>{{ __('Older versions stay available in the document\'s history.') }}</flux:text>

            <flux:input type="file" wire:model="newVersionFile" :label="__('File')" required />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="newVersionFile,saveNewVersion">{{ __('Upload') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
