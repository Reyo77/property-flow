<?php

namespace App\Livewire\PublicSite;

use App\Models\Announcement;
use App\Models\Community;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.public')]
class News extends Component
{
    use WithPagination;

    public Community $community;

    /**
     * @return LengthAwarePaginator<int, Announcement>
     */
    #[Computed]
    public function news(): LengthAwarePaginator
    {
        return $this->community->announcements()
            ->publicNews()
            ->latest('published_at')
            ->paginate(10);
    }

    public function render(): View
    {
        return view('livewire.public-site.news');
    }
}
