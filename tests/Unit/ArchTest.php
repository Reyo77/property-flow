<?php

arch()->preset()->php();

arch()->preset()->security();

arch()->preset()->laravel();

arch('debugging helpers are not left in the code')
    ->expect(['dd', 'dump', 'ddd', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();

arch('env is only read inside config files')
    ->expect('env')
    ->toOnlyBeUsedIn('config');

arch('models extend the Eloquent base model')
    ->expect('App\Models')
    ->classes()
    ->toExtend('Illuminate\Database\Eloquent\Model');

arch('livewire components extend the Livewire base component')
    ->expect('App\Livewire')
    ->classes()
    ->toExtend('Livewire\Component')
    ->ignoring(['App\Livewire\Actions', 'App\Livewire\Concerns', 'App\Livewire\Forms']);

arch('livewire forms extend the Livewire base form')
    ->expect('App\Livewire\Forms')
    ->classes()
    ->toExtend('Livewire\Form');

arch('concerns are traits')
    ->expect(['App\Concerns', 'App\Livewire\Concerns'])
    ->toBeTraits();

arch('tenant models are scoped to a company')
    ->expect('App\Models')
    ->classes()
    ->toUseTrait('App\Models\Concerns\BelongsToCompany')
    ->ignoring([
        'App\Models\Company',
        'App\Models\User',
        // Owned via user_id, which already ties it to exactly one company; it has no company_id column.
        'App\Models\NotificationPreference',
    ]);

arch('no email is sent until an email provider is set up in Phase 12')
    ->expect(['Illuminate\Support\Facades\Mail', 'Illuminate\Mail', 'Illuminate\Notifications\Messages\MailMessage'])
    ->not->toBeUsed();
