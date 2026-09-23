<?php

namespace App\Livewire\PhoneBook;

use App\Concerns\ContactValidationRules;
use App\Enums\ContactCategory;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\Contact;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Phone book')]
class Index extends Component
{
    use ContactValidationRules, InteractsWithCurrentUser;

    public Community $community;

    public string $search = '';

    #[Locked]
    public ?int $editingContactId = null;

    public string $name = '';

    public string $title = '';

    public string $category = '';

    public string $phone = '';

    public string $email = '';

    public string $notes = '';

    public bool $visible_to_residents = true;

    public function mount(): void
    {
        $this->authorize('viewAny', [Contact::class, $this->community]);
    }

    /**
     * @return Collection<int, Contact>
     */
    #[Computed]
    public function contacts(): Collection
    {
        $canManage = $this->currentUser()->can('create', [Contact::class, $this->community]);

        return $this->community->contacts()
            ->when(! $canManage, fn (Builder $query) => $query->where('visible_to_residents', true))
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';

                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', $term)
                    ->orWhere('title', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->orderBy('category')
            ->orderBy('name')
            ->get();
    }

    public function create(): void
    {
        $this->authorize('create', [Contact::class, $this->community]);

        $this->resetForm();

        Flux::modal('contact-form')->show();
    }

    public function edit(int $contactId): void
    {
        $contact = $this->findContact($contactId);

        $this->authorize('update', $contact);

        $this->resetValidation();
        $this->editingContactId = $contact->id;
        $this->name = $contact->name;
        $this->title = (string) $contact->title;
        $this->category = $contact->category->value;
        $this->phone = (string) $contact->phone;
        $this->email = (string) $contact->email;
        $this->notes = (string) $contact->notes;
        $this->visible_to_residents = $contact->visible_to_residents;

        Flux::modal('contact-form')->show();
    }

    public function save(): void
    {
        $contact = $this->editingContactId === null ? null : $this->findContact($this->editingContactId);

        $contact === null
            ? $this->authorize('create', [Contact::class, $this->community])
            : $this->authorize('update', $contact);

        $validated = $this->validate($this->contactRules());
        $validated = array_map(fn (mixed $value) => $value === '' ? null : $value, $validated);
        $validated['visible_to_residents'] = $this->visible_to_residents;

        $contact === null
            ? $this->community->contacts()->create($validated)
            : $contact->update($validated);

        Flux::modal('contact-form')->close();
        Flux::toast(variant: 'success', text: $contact === null ? __('Contact added.') : __('Contact updated.'));

        $this->resetForm();
        unset($this->contacts);
    }

    public function delete(int $contactId): void
    {
        $contact = $this->findContact($contactId);

        $this->authorize('delete', $contact);

        $contact->delete();

        Flux::toast(variant: 'success', text: __('Contact deleted.'));

        unset($this->contacts);
    }

    /**
     * @return list<ContactCategory>
     */
    public function categories(): array
    {
        return ContactCategory::cases();
    }

    public function render(): View
    {
        return view('livewire.phone-book.index');
    }

    private function findContact(int $contactId): Contact
    {
        return $this->community->contacts()->findOrFail($contactId);
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset('editingContactId', 'name', 'title', 'category', 'phone', 'email', 'notes');
        $this->visible_to_residents = true;
    }
}
