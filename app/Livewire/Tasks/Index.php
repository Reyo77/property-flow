<?php

namespace App\Livewire\Tasks;

use App\Concerns\TaskValidationRules;
use App\Enums\TaskStatus;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\Task;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Tasks')]
class Index extends Component
{
    use InteractsWithCurrentUser, TaskValidationRules;

    public Community $community;

    #[Url(as: 'status', except: 'open')]
    public string $statusFilter = 'open';

    #[Locked]
    public ?int $editingTaskId = null;

    public string $title = '';

    public string $description = '';

    public string $due_on = '';

    public string $assigned_to_id = '';

    public function mount(): void
    {
        $this->authorize('viewAny', [Task::class, $this->community]);
    }

    /**
     * @return Collection<int, Task>
     */
    #[Computed]
    public function tasks(): Collection
    {
        return $this->community->tasks()
            ->with('assignedTo')
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->orderByRaw('due_on IS NULL')
            ->orderBy('due_on')
            ->latest('id')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function teamOptions(): Collection
    {
        return $this->community->users()->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->authorize('create', [Task::class, $this->community]);

        $this->resetForm();

        Flux::modal('task-form')->show();
    }

    public function edit(int $taskId): void
    {
        $task = $this->findTask($taskId);

        $this->authorize('update', $task);

        $this->resetValidation();
        $this->editingTaskId = $task->id;
        $this->title = $task->title;
        $this->description = (string) $task->description;
        $this->due_on = $task->due_on?->toDateString() ?? '';
        $this->assigned_to_id = (string) $task->assigned_to_id;

        Flux::modal('task-form')->show();
    }

    public function save(): void
    {
        $task = $this->editingTaskId === null ? null : $this->findTask($this->editingTaskId);

        $task === null
            ? $this->authorize('create', [Task::class, $this->community])
            : $this->authorize('update', $task);

        $validated = $this->validate($this->taskRules($this->community));
        $validated = array_map(fn (mixed $value) => $value === '' ? null : $value, $validated);
        $isNew = $task === null;

        if ($isNew) {
            $task = $this->community->tasks()->make($validated);
            $task->forceFill(['created_by_id' => $this->currentUser()->id])->save();
        } else {
            $task->update($validated);
        }

        Flux::modal('task-form')->close();
        Flux::toast(variant: 'success', text: $isNew ? __('Task added.') : __('Task updated.'));

        $this->resetForm();
        unset($this->tasks);
    }

    public function toggleDone(int $taskId): void
    {
        $task = $this->findTask($taskId);

        $this->authorize('update', $task);

        $task->forceFill(['status' => $task->status === TaskStatus::Done ? TaskStatus::Open : TaskStatus::Done])->save();

        unset($this->tasks);
    }

    public function delete(int $taskId): void
    {
        $task = $this->findTask($taskId);

        $this->authorize('delete', $task);

        $task->delete();

        Flux::toast(variant: 'success', text: __('Task deleted.'));
        unset($this->tasks);
    }

    public function render(): View
    {
        return view('livewire.tasks.index');
    }

    private function findTask(int $taskId): Task
    {
        return $this->community->tasks()->findOrFail($taskId);
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset('editingTaskId', 'title', 'description', 'due_on', 'assigned_to_id');
    }
}
