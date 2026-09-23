<?php

use App\Enums\CompanyRole;
use App\Enums\TaskStatus;
use App\Livewire\Tasks\Index;
use App\Models\Community;
use App\Models\Task;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('lists open tasks by default', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    Task::factory()->for($community)->create(['title' => 'Open one']);
    Task::factory()->for($community)->done()->create(['title' => 'Done one']);

    actingAs($admin);

    get(route('communities.tasks.index', $community))
        ->assertOk()
        ->assertSee('Open one')
        ->assertDontSee('Done one');
});

it('filters between open, done and all', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    Task::factory()->for($community)->create(['title' => 'Open one']);
    Task::factory()->for($community)->done()->create(['title' => 'Done one']);

    actingAs($admin);

    $component = Livewire::test(Index::class, ['community' => $community]);
    $titles = fn () => $component->instance()->tasks()->pluck('title')->all();

    expect($titles())->toBe(['Open one']);

    $component->set('statusFilter', 'done');
    expect($titles())->toBe(['Done one']);

    $component->set('statusFilter', '');
    expect($titles())->toHaveCount(2);
});

it('creates a task assigned to a team member', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $staff = teamMember(CompanyRole::Staff, $admin->company, [$community]);

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('create')
        ->set('title', 'Replace lobby bulb')
        ->set('due_on', now()->addWeek()->toDateString())
        ->set('assigned_to_id', (string) $staff->id)
        ->call('save')
        ->assertHasNoErrors();

    $task = Task::sole();

    expect($task)
        ->community_id->toBe($community->id)
        ->title->toBe('Replace lobby bulb')
        ->assigned_to_id->toBe($staff->id)
        ->created_by_id->toBe($admin->id)
        ->status->toBe(TaskStatus::Open);
});

it('toggles a task done and back to open', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $task = Task::factory()->for($community)->create();

    actingAs($admin);

    $component = Livewire::test(Index::class, ['community' => $community]);

    $component->call('toggleDone', $task->id);
    expect($task->refresh()->status)->toBe(TaskStatus::Done);

    $component->call('toggleDone', $task->id);
    expect($task->refresh()->status)->toBe(TaskStatus::Open);
});

it('updates and deletes a task', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $task = Task::factory()->for($community)->create(['title' => 'Old title']);

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('edit', $task->id)
        ->assertSet('title', 'Old title')
        ->set('title', 'New title')
        ->call('save')
        ->assertHasNoErrors();

    expect($task->refresh()->title)->toBe('New title');

    Livewire::test(Index::class, ['community' => $community])->call('delete', $task->id);

    expect($task->refresh()->trashed())->toBeTrue();
});

it('flags an overdue open task but not a done one', function () {
    $overdueOpen = Task::factory()->create(['due_on' => now()->subDay()->toDateString(), 'status' => TaskStatus::Open]);
    $overdueDone = Task::factory()->create(['due_on' => now()->subDay()->toDateString(), 'status' => TaskStatus::Done]);

    expect($overdueOpen->isOverdue())->toBeTrue()
        ->and($overdueDone->isOverdue())->toBeFalse();
});

it('forbids staff without manage-tasks from creating, but staff role can by default', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $board = teamMember(CompanyRole::BoardMember, $admin->company, [$community]);

    actingAs($board);

    get(route('communities.tasks.index', $community))->assertOk();
    Livewire::test(Index::class, ['community' => $community])->call('create')->assertForbidden();
});

it('cannot manage a task from another company', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $foreign = Task::factory()->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])->call('edit', $foreign->id)->assertNotFound();
});
