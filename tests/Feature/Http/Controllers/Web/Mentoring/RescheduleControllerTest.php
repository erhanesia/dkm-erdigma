<?php

namespace Tests\Feature\Http\Controllers\Web\Mentoring;

use App\Enums\SessionStatus;
use App\Enums\UserRole;
use App\Models\AfterHoursSession;
use App\Models\AfterHoursSessionSeries;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RescheduleControllerTest extends TestCase
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

        // A Monday. The sessions below are set for the days after it.
        $this->travelTo('2026-09-14 09:00:00');
    }

    public function test_employees_are_forbidden_from_rescheduling(): void
    {
        $session = $this->tuesdaySession();
        $employee = User::factory()->withRole(UserRole::Employee)->create();

        $response = $this->actingAs($employee)->get(route('sessions.reschedule.edit', $session));

        $response->assertForbidden();
    }

    public function test_a_mentor_cannot_reschedule_another_halaqahs_session(): void
    {
        $session = $this->tuesdaySession();
        $otherMentor = User::factory()->mentor()->create();

        $response = $this->actingAs($otherMentor)
            ->put(route('sessions.reschedule.update', $session), $this->thursdayEvening());

        $response->assertForbidden();

        $this->assertDatabaseHas('after_hours_sessions', ['id' => $session->id, 'starts_at' => '2026-09-15 16:30:00']);
    }

    public function test_rescheduling_moves_the_session_and_keeps_its_original_time(): void
    {
        $session = $this->tuesdaySession();

        $response = $this->actingAs($this->boardMember())
            ->put(route('sessions.reschedule.update', $session), [
                ...$this->thursdayEvening(),
                'reason' => 'Mentor berhalangan hadir',
            ]);

        $response->assertRedirect(route('sessions.show', $session));

        $this->assertDatabaseHas('after_hours_sessions', [
            'id' => $session->id,
            'starts_at' => '2026-09-17 19:00:00',
            'ends_at' => '2026-09-17 20:30:00',
            'rescheduled_from' => '2026-09-15 16:30:00',
            'reschedule_reason' => 'Mentor berhalangan hadir',
        ]);
    }

    public function test_a_second_move_keeps_the_first_original_time(): void
    {
        $session = AfterHoursSession::factory()->create([
            'starts_at' => '2026-09-17 19:00:00',
            'ends_at' => '2026-09-17 20:30:00',
            'rescheduled_from' => '2026-09-15 16:30:00',
        ]);

        $this->actingAs($this->boardMember())->put(route('sessions.reschedule.update', $session), [
            'starts_at' => '2026-09-18T16:30',
            'ends_at' => '2026-09-18T18:00',
        ]);

        $this->assertDatabaseHas('after_hours_sessions', [
            'id' => $session->id,
            'starts_at' => '2026-09-18 16:30:00',
            'rescheduled_from' => '2026-09-15 16:30:00',
        ]);
    }

    public function test_rescheduling_keeps_the_attendance_list(): void
    {
        $session = $this->tuesdaySession();
        $attendance = Attendance::factory()->create(['after_hours_session_id' => $session->id]);

        $this->actingAs($this->boardMember())
            ->put(route('sessions.reschedule.update', $session), $this->thursdayEvening());

        $this->assertModelExists($attendance);
    }

    public function test_moving_the_following_meetings_shifts_only_those_that_can_still_move(): void
    {
        $series = AfterHoursSessionSeries::factory()->create(['weekday' => 2]);
        [$held, $anchor, $next, $attended] = AfterHoursSession::factory()
            ->count(4)
            ->sequence(
                ['starts_at' => '2026-09-01 16:30:00', 'ends_at' => '2026-09-01 18:00:00', 'status' => SessionStatus::Completed],
                ['starts_at' => '2026-09-15 16:30:00', 'ends_at' => '2026-09-15 18:00:00', 'status' => SessionStatus::Scheduled],
                ['starts_at' => '2026-09-29 16:30:00', 'ends_at' => '2026-09-29 18:00:00', 'status' => SessionStatus::Scheduled],
                ['starts_at' => '2026-10-13 16:30:00', 'ends_at' => '2026-10-13 18:00:00', 'status' => SessionStatus::Scheduled],
            )
            ->create(['mentoring_group_id' => $series->mentoring_group_id, 'series_id' => $series->id])
            ->all();
        Attendance::factory()->present()->create(['after_hours_session_id' => $attended->id]);

        $response = $this->actingAs($this->boardMember())
            ->put(route('sessions.reschedule.update', $anchor), [
                'starts_at' => '2026-09-16T16:30',
                'ends_at' => '2026-09-16T18:00',
                'with_following' => '1',
            ]);

        $response->assertRedirect(route('sessions.show', $anchor));

        $this->assertSame('2026-09-01 16:30:00', $held->fresh()->starts_at->toDateTimeString());
        $this->assertSame('2026-09-16 16:30:00', $anchor->fresh()->starts_at->toDateTimeString());
        $this->assertSame('2026-09-30 16:30:00', $next->fresh()->starts_at->toDateTimeString());
        $this->assertSame('2026-10-13 16:30:00', $attended->fresh()->starts_at->toDateTimeString());
        $this->assertSame(3, $series->fresh()->weekday);
    }

    public function test_a_completed_session_cannot_be_rescheduled(): void
    {
        $session = AfterHoursSession::factory()->completed()->create([
            'starts_at' => '2026-09-15 16:30:00',
            'ends_at' => '2026-09-15 18:00:00',
        ]);

        $response = $this->actingAs($this->boardMember())
            ->from(route('sessions.reschedule.edit', $session))
            ->put(route('sessions.reschedule.update', $session), $this->thursdayEvening());

        $response->assertRedirect(route('sessions.reschedule.edit', $session))
            ->assertSessionHas('flash_notification.message', 'Hanya kegiatan berstatus Terjadwal yang bisa dijadwal ulang.');

        $this->assertDatabaseHas('after_hours_sessions', ['id' => $session->id, 'starts_at' => '2026-09-15 16:30:00']);
    }

    public function test_a_new_time_that_has_already_passed_is_refused(): void
    {
        $session = $this->tuesdaySession();

        $response = $this->actingAs($this->boardMember())
            ->from(route('sessions.reschedule.edit', $session))
            ->put(route('sessions.reschedule.update', $session), [
                'starts_at' => '2026-09-13T16:30',
                'ends_at' => '2026-09-13T18:00',
            ]);

        $response->assertSessionHasErrors(['starts_at' => 'Jadwal baru harus di waktu yang belum lewat.']);

        $this->assertDatabaseHas('after_hours_sessions', ['id' => $session->id, 'starts_at' => '2026-09-15 16:30:00']);
    }

    public function test_the_reschedule_page_offers_to_move_the_following_meetings_of_a_series(): void
    {
        $series = AfterHoursSessionSeries::factory()->create(['weekday' => 2]);
        $session = AfterHoursSession::factory()->create([
            'mentoring_group_id' => $series->mentoring_group_id,
            'series_id' => $series->id,
            'starts_at' => '2026-09-15 16:30:00',
            'ends_at' => '2026-09-15 18:00:00',
        ]);

        $response = $this->actingAs($this->boardMember())->get(route('sessions.reschedule.edit', $session));

        $response->assertOk()->assertSee('name="with_following"', false);
    }

    private function tuesdaySession(): AfterHoursSession
    {
        return AfterHoursSession::factory()->create([
            'starts_at' => '2026-09-15 16:30:00',
            'ends_at' => '2026-09-15 18:00:00',
        ]);
    }

    /**
     * @return array{starts_at: string, ends_at: string}
     */
    private function thursdayEvening(): array
    {
        return ['starts_at' => '2026-09-17T19:00', 'ends_at' => '2026-09-17T20:30'];
    }

    private function boardMember(): User
    {
        return User::factory()->withRole(UserRole::DkmAdmin)->create();
    }
}
