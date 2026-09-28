<?php

namespace App\Livewire\PublicSite;

use App\Enums\DocumentVisibility;
use App\Models\Community;
use App\Models\Document;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
class Documents extends Component
{
    public Community $community;

    /**
     * @return Collection<int, Document>
     */
    #[Computed]
    public function documents(): Collection
    {
        return $this->community->documents()
            ->where('visibility', DocumentVisibility::Public)
            ->orderBy('title')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.public-site.documents');
    }
}
