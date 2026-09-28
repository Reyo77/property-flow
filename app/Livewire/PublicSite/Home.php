<?php

namespace App\Livewire\PublicSite;

use App\Models\Announcement;
use App\Models\Community;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
class Home extends Component
{
    public Community $community;

    /**
     * @return Collection<int, Announcement>
     */
    #[Computed]
    public function latestNews(): Collection
    {
        return $this->community->announcements()
            ->publicNews()
            ->latest('published_at')
            ->limit(3)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.public-site.home');
    }
}
