<?php

namespace App\Livewire\Units;

use App\Actions\Units\ImportUnits;
use App\Actions\Units\UnitImportResult;
use App\Models\Community;
use App\Models\Unit;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Title('Import units')]
class Import extends Component
{
    use WithFileUploads;

    public Community $community;

    public ?TemporaryUploadedFile $file = null;

    /** @var array<int, list<string>> */
    #[Locked]
    public array $rowErrors = [];

    public function mount(): void
    {
        $this->authorize('import', [Unit::class, $this->community]);
    }

    public function import(ImportUnits $importUnits): void
    {
        $this->authorize('import', [Unit::class, $this->community]);

        $this->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120'],
        ]);

        $this->rowErrors = [];

        if (! $this->file instanceof TemporaryUploadedFile) {
            return;
        }

        $result = $importUnits->handle($this->community, $this->file);

        if ($result->failed()) {
            $this->rowErrors = $result->errors;

            return;
        }

        Flux::toast(variant: 'success', text: $this->summary($result));

        $this->redirectRoute('communities.units.index', $this->community, navigate: true);
    }

    public function downloadTemplate(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            echo implode(',', ImportUnits::COLUMNS).PHP_EOL;
            echo 'Tower A,101,1,850.50,0.512300,P1-12,L-4'.PHP_EOL;
        }, 'units-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function render(): View
    {
        return view('livewire.units.import');
    }

    private function summary(UnitImportResult $result): string
    {
        return $result->buildingsCreated > 0
            ? __(':units units imported and :buildings buildings created.', ['units' => $result->unitsCreated, 'buildings' => $result->buildingsCreated])
            : __(':units units imported.', ['units' => $result->unitsCreated]);
    }
}
