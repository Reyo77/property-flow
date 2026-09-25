<?php

use App\Actions\Governance\CloseMeeting;
use App\Actions\Governance\PublishMinutes;
use App\Actions\Governance\RecordAttendance;
use App\Actions\Governance\SaveMeeting;
use App\Enums\AttendanceMode;
use App\Enums\ResidencyType;
use App\Enums\VotingWeighting;
use App\Models\Community;
use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\Residency;
use App\Models\Resident;
use App\Models\Unit;
use App\Support\Governance\MeetingQuorum;
use Illuminate\Validation\ValidationException;

/**
 * Owner-occupied units with factors 10, 20, 30, 40, plus a tenant-only unit (factor 50) that
 * isn't on the voting roll.
 *
 * @return array{0: Community, 1: list<Unit>, 2: Unit}
 */
function meetingCommunity(): array
{
    $community = Community::factory()->create();
    $owned = [];

    foreach (['10', '20', '30', '40'] as $factor) {
        $unit = Unit::factory()->for($community)->create(['unit_factor' => $factor]);
        Residency::factory()->for($unit)->for(Resident::factory()->for($community->company))->create(['type' => ResidencyType::Owner]);
        $owned[] = $unit;
    }

    $rented = Unit::factory()->for($community)->create(['unit_factor' => '50']);
    Residency::factory()->for($rented)->for(Resident::factory()->for($community->company))->create(['type' => ResidencyType::Tenant]);

    return [$community, $owned, $rented];
}

function attend(Meeting $meeting, Unit $unit, AttendanceMode $mode = AttendanceMode::InPerson): MeetingAttendance
{
    return app(RecordAttendance::class)->handle($meeting, $unit, $mode, null, companyAdmin($meeting->community->company));
}

it('computes quorum from the owners represented, by unit factor', function () {
    [$community, $owned, $rented] = meetingCommunity();
    $meeting = Meeting::factory()->for($community)->create(['weighting' => VotingWeighting::UnitFactor, 'quorum_percent' => 50]);

    attend($meeting, $owned[3], AttendanceMode::Proxy);   // 40
    attend($meeting, $rented);                           // not on the roll: doesn't count

    expect(app(MeetingQuorum::class)->for($meeting))
        ->eligible_units->toBe(4)->represented_units->toBe(1)
        ->percent->toBe('40.00')->met->toBeFalse();

    attend($meeting, $owned[0], AttendanceMode::Online); // + 10 = 50

    expect(app(MeetingQuorum::class)->for($meeting))->percent->toBe('50.00')->met->toBeTrue();
});

it('computes quorum one unit, one vote', function () {
    [$community, $owned] = meetingCommunity();
    $meeting = Meeting::factory()->for($community)->create(['weighting' => VotingWeighting::PerUnit, 'quorum_percent' => 50]);

    attend($meeting, $owned[0]);

    expect(app(MeetingQuorum::class)->for($meeting))->percent->toBe('25.00')->met->toBeFalse();

    attend($meeting, $owned[1]);

    expect(app(MeetingQuorum::class)->for($meeting))->percent->toBe('50.00')->met->toBeTrue();
});

it('counts a unit once however many times it is checked in', function () {
    [$community, $owned] = meetingCommunity();
    $meeting = Meeting::factory()->for($community)->create();

    attend($meeting, $owned[0], AttendanceMode::InPerson);
    attend($meeting, $owned[0], AttendanceMode::Proxy);

    expect(MeetingAttendance::where('meeting_id', $meeting->id)->sole()->represented_by)->toBe(AttendanceMode::Proxy);
});

it('never reaches quorum in a community with no owners', function () {
    $community = Community::factory()->create();

    expect(app(MeetingQuorum::class)->for(Meeting::factory()->for($community)->create(['quorum_percent' => 0])))->met->toBeFalse();
});

it('refuses attendance from another community or after the meeting closes', function () {
    [$community, $owned] = meetingCommunity();
    $meeting = Meeting::factory()->for($community)->create();

    expect(fn () => attend($meeting, Unit::factory()->for(Community::factory()->for($community->company))->create()))
        ->toThrow(ValidationException::class, 'not in this community');

    app(CloseMeeting::class)->handle($meeting, companyAdmin($community->company));

    expect(fn () => attend($meeting->fresh() ?? $meeting, $owned[0]))->toThrow(ValidationException::class, 'closed');
});

it('saves the agenda in order, and freezes a closed meeting', function () {
    [$community] = meetingCommunity();
    $admin = companyAdmin($community->company);
    $attributes = ['title' => 'AGM 2026', 'kind' => 'agm', 'starts_at' => '2026-11-20 19:00', 'location' => 'Party Room', 'description' => null, 'weighting' => 'unit_factor', 'quorum_percent' => 25];

    $meeting = app(SaveMeeting::class)->handle($community, null, $attributes, ['Call to order', 'Budget', 'Elections'], $admin);
    $meeting = app(SaveMeeting::class)->handle($community, $meeting, $attributes, ['Call to order', 'Elections'], $admin);

    expect($meeting->agendaItems()->pluck('title')->all())->toBe(['Call to order', 'Elections']);

    app(CloseMeeting::class)->handle($meeting, $admin);

    expect(fn () => app(SaveMeeting::class)->handle($community, $meeting->fresh(), $attributes, [], $admin))->toThrow(LogicException::class, 'closed');
});

it('publishes minutes only once they are written', function () {
    [$community] = meetingCommunity();
    $admin = companyAdmin($community->company);
    $meeting = Meeting::factory()->for($community)->create();

    expect(fn () => app(PublishMinutes::class)->handle($meeting, '   ', true, $admin))->toThrow(LogicException::class);

    app(PublishMinutes::class)->handle($meeting, 'Quorum present. Budget approved.', true, $admin);

    expect($meeting->fresh())->minutes->toBe('Quorum present. Budget approved.')->hasPublishedMinutes()->toBeTrue();

    app(PublishMinutes::class)->handle($meeting, 'Draft correction', false, $admin);

    expect($meeting->fresh()?->hasPublishedMinutes())->toBeFalse();
});
