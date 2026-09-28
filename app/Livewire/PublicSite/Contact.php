<?php

namespace App\Livewire\PublicSite;

use App\Actions\PublicSite\SubmitContactMessage;
use App\Models\Community;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
class Contact extends Component
{
    public Community $community;

    public string $name = '';

    public string $email = '';

    public string $message = '';

    public bool $submitted = false;

    public function submit(SubmitContactMessage $submitContactMessage): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $submitContactMessage->handle($this->community, $validated['name'], $validated['email'], $validated['message']);

        $this->reset('name', 'email', 'message');
        $this->submitted = true;
    }

    public function render(): View
    {
        return view('livewire.public-site.contact');
    }
}
