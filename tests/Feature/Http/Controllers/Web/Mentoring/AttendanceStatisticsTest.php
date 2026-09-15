<?php

namespace Tests\Feature\Http\Controllers\Web\Mentoring;

use App\Enums\AttendanceStatus;
use App\Enums\SessionStatus;
use App\Enums\UserRole;
use App\Models\AfterHoursSession;
use App\Models\Attendance;
use App\Models\MentoringGroup;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AttendanceStatisticsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Panel pages can generate missing prayer times. With no official city
         * configured that stays a local calculation, and no test here may reach
         * the network.
         */
        config(['dkm.official_schedule.city_id' => '']);
        Http::preventStrayRequests();

        // A Monday in September 2026.
        $this->travelTo('2026-09-14 09:00:00');
    }

    public function test_the_halaqah_page_counts_each_status_by_month_for_the_chosen_year(): void
    {
        $group = MentoringGroup::factory()->create();

        $august = $this->heldSession($group, '2026-08-11 16:30:00');
        $this->record($august, AttendanceStatus::Present);
        $this->record($august, AttendanceStatus::Late);
        $this->record($august, AttendanceStatus::Absent);
        $this->record($this->heldSession($group, '2026-09-08 16:30:00'), AttendanceStatus::Excused);
        $this->record($this->heldSession($group, '2025-11-04 16:30:00'), AttendanceStatus::Present);

        $response = $this->actingAs($this->boardMember())
            ->get(route('mentoring-groups.show', [$group, 'tahun' => 2026]));

        $response->assertOk()
            ->assertViewHas('year', 2026)
            ->assertViewHas('years', [2026, 2025])
            ->assertViewHas('statistics', static fn (array $statistics): bool => $statistics['months'][8] === ['present' => 1, 'late' => 1, 'excused' => 0, 'sick' => 0, 'absent' => 1]
                && $statistics['months'][9]['excused'] === 1
                && $statistics['recorded'] === 4
                && $statistics['attended'] === 2
                && $statistics['rate'] === 50.0)
            ->assertSee('data-chart="bar"', false)
            ->assertSee('data-chart="doughnut"', false);
    }

    public function test_cancelled_upcoming_and_other_halaqahs_sessions_are_left_out(): void
    {
        $group = MentoringGroup::factory()->create();
        $this->record($this->heldSession($group, '2026-09-08 16:30:00'), AttendanceStatus::Present);

        $cancelled = AfterHoursSession::factory()->for($group, 'group')->create([
            'status' => SessionStatus::Cancelled,
            'starts_at' => '2026-09-10 16:30:00',
            'ends_at' => '2026-09-10 18:00:00',
        ]);
        $this->record($cancelled, AttendanceStatus::Absent);

        // Scheduled for tomorrow: its members already carry an absent row.
        $upcoming = AfterHoursSession::factory()->for($group, 'group')->create([
            'starts_at' => '2026-09-15 16:30:00',
            'ends_at' => '2026-09-15 18:00:00',
        ]);
        $this->record($upcoming, AttendanceStatus::Absent);

        $this->record($this->heldSession(MentoringGroup::factory()->create(), '2026-09-09 16:30:00'), AttendanceStatus::Absent);

        $response = $this->actingAs($this->boardMember())->get(route('mentoring-groups.show', $group));

        $response->assertOk()->assertViewHas('statistics', static fn (array $statistics): bool => $statistics['recorded'] === 1
            && $statistics['totals']['absent'] === 0);
    }

    public function test_a_year_that_cannot_be_opened_falls_back_to_this_year(): void
    {
        $group = MentoringGroup::factory()->create();

        $response = $this->actingAs($this->boardMember())
            ->get(route('mentoring-groups.show', [$group, 'tahun' => 1999]));

        $response->assertOk()
            ->assertViewHas('year', 2026)
            ->assertViewHas('years', [2026])
            ->assertSee('Belum ada data kehadiran di 2026');
    }

    public function test_session_details_chart_attendance_once_the_session_has_started(): void
    {
        $session = $this->heldSession(MentoringGroup::factory()->create(), '2026-09-08 16:30:00');
        $this->record($session, AttendanceStatus::Present);
        $this->record($session, AttendanceStatus::Absent);

        $response = $this->actingAs($this->boardMember())->get(route('sessions.show', $session));

        $response->assertOk()->assertSee('data-chart="doughnut"', false);
    }

    public function test_an_upcoming_session_has_no_attendance_chart_yet(): void
    {
        $session = AfterHoursSession::factory()->create([
            'starts_at' => '2026-09-15 16:30:00',
            'ends_at' => '2026-09-15 18:00:00',
        ]);
        $this->record($session, AttendanceStatus::Absent);

        $response = $this->actingAs($this->boardMember())->get(route('sessions.show', $session));

        $response->assertOk()
            ->assertDontSee('data-chart="doughnut"', false)
            ->assertSee('Grafik muncul setelah kegiatan dimulai.');
    }

    public function test_a_member_sees_a_chart_of_their_own_attendance_only(): void
    {
        $group = MentoringGroup::factory()->create();
        $member = $this->memberOf($group);
        $session = $this->heldSession($group, '2026-09-08 16:30:00');
        $this->record($session, AttendanceStatus::Present, $member);
        $this->record($session, AttendanceStatus::Absent, $this->memberOf($group));

        $response = $this->actingAs($member)->get(route('my-after-hours.index'));

        $response->assertOk()
            ->assertViewHas('personalStatistics', static fn (array $statistics): bool => $statistics['recorded'] === 1
                && $statistics['totals']['present'] === 1)
            ->assertViewHas('mentoredStatistics', static fn (?array $statistics): bool => $statistics === null)
            ->assertSee('Kehadiran Saya')
            ->assertDontSee('Halaqah yang Saya Bina');
    }

    public function test_a_mentor_also_sees_how_the_halaqah_they_lead_attends(): void
    {
        $group = MentoringGroup::factory()->create();
        $session = $this->heldSession($group, '2026-09-08 16:30:00');
        $this->record($session, AttendanceStatus::Present, $this->memberOf($group));
        $this->record($session, AttendanceStatus::Sick, $this->memberOf($group));

        $response = $this->actingAs($group->mentor)->get(route('my-after-hours.index'));

        $response->assertOk()
            ->assertViewHas('mentoredStatistics', static fn (?array $statistics): bool => $statistics !== null
                && $statistics['recorded'] === 2
                && $statistics['totals']['sick'] === 1)
            ->assertSee('Halaqah yang Saya Bina');
    }

    private function heldSession(MentoringGroup $group, string $startsAt): AfterHoursSession
    {
        $start = CarbonImmutable::parse($startsAt);

        return AfterHoursSession::factory()->completed()->for($group, 'group')->create([
            'starts_at' => $start->toDateTimeString(),
            'ends_at' => $start->addMinutes(90)->toDateTimeString(),
        ]);
    }

    private function record(AfterHoursSession $session, AttendanceStatus $status, ?User $member = null): Attendance
    {
        return Attendance::factory()->for($session, 'session')->create([
            'user_id' => ($member ?? User::factory()->create())->id,
            'status' => $status,
        ]);
    }

    private function memberOf(MentoringGroup $group): User
    {
        $member = User::factory()->withRole(UserRole::Employee)->create();

        $group->memberships()->create([
            'user_id' => $member->id,
            'joined_at' => '2026-09-01',
            'is_active' => true,
        ]);

        return $member;
    }

    private function boardMember(): User
    {
        return User::factory()->withRole(UserRole::DkmAdmin)->create();
    }
}
