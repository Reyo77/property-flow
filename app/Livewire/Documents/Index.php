<?php

namespace App\Livewire\Documents;

use App\Actions\Documents\UploadDocumentVersion;
use App\Concerns\DocumentValidationRules;
use App\Enums\DocumentVisibility;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\Document;
use App\Models\DocumentFolder;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Title('Documents')]
class Index extends Component
{
    use DocumentValidationRules, InteractsWithCurrentUser, WithFileUploads;

    public Community $community;

    #[Url(as: 'folder', except: null)]
    public ?int $folderId = null;

    public string $folder_name = '';

    public string $folder_visibility = '';

    #[Locked]
    public ?int $editingDocumentId = null;

    public string $title = '';

    public string $visibility = '';

    public ?TemporaryUploadedFile $file = null;

    #[Locked]
    public ?int $versioningDocumentId = null;

    public ?TemporaryUploadedFile $newVersionFile = null;

    public function mount(): void
    {
        $this->authorize('viewAny', [Document::class, $this->community]);
    }

    #[Computed]
    public function currentFolder(): ?DocumentFolder
    {
        if ($this->folderId === null) {
            return null;
        }

        $folder = $this->community->documentFolders()->find($this->folderId);

        return $folder !== null && $this->currentUser()->can('view', $folder) ? $folder : null;
    }

    /**
     * The folder's ancestors, root first, for the breadcrumb trail.
     *
     * @return list<DocumentFolder>
     */
    #[Computed]
    public function breadcrumbs(): array
    {
        $trail = [];
        $folder = $this->currentFolder();

        while ($folder !== null) {
            array_unshift($trail, $folder);
            $folder = $folder->parent;
        }

        return $trail;
    }

    /**
     * @return Collection<int, DocumentFolder>
     */
    #[Computed]
    public function subfolders(): Collection
    {
        return $this->community->documentFolders()
            ->where('parent_id', $this->folderId)
            ->orderBy('name')
            ->get()
            ->filter(fn (DocumentFolder $folder) => $this->currentUser()->can('view', $folder))
            ->values();
    }

    /**
     * @return Collection<int, Document>
     */
    #[Computed]
    public function documents(): Collection
    {
        return $this->community->documents()
            ->where('folder_id', $this->folderId)
            ->with('currentVersion')
            ->orderBy('title')
            ->get()
            ->filter(fn (Document $document) => $this->currentUser()->can('view', $document))
            ->values();
    }

    public function openFolder(?int $folderId): void
    {
        $this->folderId = $folderId;
    }

    public function createFolder(): void
    {
        $this->authorize('create', [DocumentFolder::class, $this->community]);

        $this->resetValidation();
        $this->folder_name = '';
        $this->folder_visibility = $this->currentFolder()?->visibility->value ?? DocumentVisibility::Residents->value;

        Flux::modal('folder-form')->show();
    }

    public function saveFolder(): void
    {
        $this->authorize('create', [DocumentFolder::class, $this->community]);

        $validated = $this->validate([
            'folder_name' => ['required', 'string', 'max:255'],
            'folder_visibility' => $this->documentFolderRules()['visibility'],
        ]);

        $this->community->documentFolders()->create([
            'parent_id' => $this->folderId,
            'name' => $validated['folder_name'],
            'visibility' => $validated['folder_visibility'],
        ]);

        Flux::modal('folder-form')->close();
        Flux::toast(variant: 'success', text: __('Folder created.'));

        unset($this->subfolders);
    }

    public function deleteFolder(int $folderId): void
    {
        $folder = $this->findFolder($folderId);

        $this->authorize('delete', $folder);

        if ($folder->children()->exists() || $folder->documents()->exists()) {
            Flux::toast(variant: 'danger', text: __('Move or delete the contents of :name before deleting it.', ['name' => $folder->name]));

            return;
        }

        $folder->delete();

        Flux::toast(variant: 'success', text: __('Folder deleted.'));

        unset($this->subfolders);
    }

    public function createDocument(): void
    {
        $this->authorize('create', [Document::class, $this->community]);

        $this->resetValidation();
        $this->reset('editingDocumentId', 'title', 'file');
        $this->visibility = $this->currentFolder()?->visibility->value ?? DocumentVisibility::Residents->value;

        Flux::modal('document-form')->show();
    }

    public function editDocument(int $documentId): void
    {
        $document = $this->findDocument($documentId);

        $this->authorize('update', $document);

        $this->resetValidation();
        $this->editingDocumentId = $document->id;
        $this->title = $document->title;
        $this->visibility = $document->visibility->value;
        $this->file = null;

        Flux::modal('document-form')->show();
    }

    public function saveDocument(UploadDocumentVersion $uploadDocumentVersion): void
    {
        $document = $this->editingDocumentId === null ? null : $this->findDocument($this->editingDocumentId);

        $document === null
            ? $this->authorize('create', [Document::class, $this->community])
            : $this->authorize('update', $document);

        $validated = $this->validate([
            ...$this->documentRules(),
            ...$this->documentFileRules(required: $document === null),
        ]);

        if ($document === null) {
            $document = $this->community->documents()->create([
                'folder_id' => $this->folderId,
                'title' => $validated['title'],
                'visibility' => $validated['visibility'],
                'uploaded_by_id' => $this->currentUser()->id,
            ]);
        } else {
            $document->update(['title' => $validated['title'], 'visibility' => $validated['visibility']]);
        }

        if ($this->file instanceof TemporaryUploadedFile) {
            $uploadDocumentVersion->handle($document, $this->file, $this->currentUser());
        }

        Flux::modal('document-form')->close();
        Flux::toast(variant: 'success', text: __('Document saved.'));

        $this->reset('editingDocumentId', 'title', 'file');
        unset($this->documents);
    }

    public function openNewVersion(int $documentId): void
    {
        $document = $this->findDocument($documentId);

        $this->authorize('upload', $document);

        $this->resetValidation();
        $this->versioningDocumentId = $document->id;
        $this->newVersionFile = null;

        Flux::modal('new-version-form')->show();
    }

    public function saveNewVersion(UploadDocumentVersion $uploadDocumentVersion): void
    {
        $document = $this->findDocument((int) $this->versioningDocumentId);

        $this->authorize('upload', $document);

        $this->validate(['newVersionFile' => $this->documentFileRules()['file']]);

        if ($this->newVersionFile instanceof TemporaryUploadedFile) {
            $uploadDocumentVersion->handle($document, $this->newVersionFile, $this->currentUser());
        }

        Flux::modal('new-version-form')->close();
        Flux::toast(variant: 'success', text: __('New version uploaded.'));

        $this->reset('versioningDocumentId', 'newVersionFile');
        unset($this->documents);
    }

    public function deleteDocument(int $documentId): void
    {
        $document = $this->findDocument($documentId);

        $this->authorize('delete', $document);

        $document->delete();

        Flux::toast(variant: 'success', text: __('Document deleted.'));

        unset($this->documents);
    }

    public function render(): View
    {
        return view('livewire.documents.index');
    }

    private function findFolder(int $folderId): DocumentFolder
    {
        return $this->community->documentFolders()->findOrFail($folderId);
    }

    private function findDocument(int $documentId): Document
    {
        return $this->community->documents()->findOrFail($documentId);
    }
}
