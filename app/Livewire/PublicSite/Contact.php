<?php

namespace App\Livewire\PublicSite;

use App\Actions\PublicSite\SubmitContactMessage;
use App\Models\Community;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
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
        $throttleKey = 'contact-form:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->addError('message', __('Too many messages sent. Please try again later.'));

            return;
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        RateLimiter::hit($throttleKey, 3600);

        $submitContactMessage->handle($this->community, $validated['name'], $validated['email'], $validated['message']);

        $this->reset('name', 'email', 'message');
        $this->submitted = true;
    }

    public function render(): View
    {
        return view('livewire.public-site.contact');
    }
}
