<?php

namespace Tests\Feature\Http\Controllers\Web\Mentoring;

use App\Enums\SessionStatus;
use App\Enums\UserRole;
use App\Models\AfterHoursSession;
use App\Models\AfterHoursSessionSeries;
use App\Models\Location;
use App\Models\MentoringGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SessionControllerTest extends TestCase
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

        // A Monday. Every session below is set for the days after it.
        $this->travelTo('2026-09-14 09:00:00');
    }

    public function test_a_new_place_typed_into_the_form_is_saved_for_reuse(): void
    {
        $group = MentoringGroup::factory()->create();

        $response = $this->actingAs($this->boardMember())
            ->post(route('sessions.store'), $this->sessionPayload($group, ['location' => 'Aula Lantai 3']));

        $session = AfterHoursSession::query()->sole();
        $location = Location::query()->sole();

        $response->assertRedirect(route('sessions.show', $session));

        $this->assertSame('Aula Lantai 3', $location->name);
        $this->assertSame($location->id, $session->location_id);
        $this->assertSame('Aula Lantai 3', $session->location);
    }

    public function test_a_place_typed_with_different_case_and_spacing_reuses_the_existing_one(): void
    {
        $group = MentoringGroup::factory()->create();
        $existing = Location::factory()->create(['name' => 'Musholla Erdigma']);

        $this->actingAs($this->boardMember())
            ->post(route('sessions.store'), $this->sessionPayload($group, ['location' => '  musholla   erdigma ']));

        $this->assertDatabaseCount('locations', 1);
        $this->assertDatabaseHas('after_hours_sessions', [
            'location_id' => $existing->id,
            'location' => 'Musholla Erdigma',
        ]);
    }

    public function test_a_recurring_session_is_scheduled_every_two_weeks_until_the_end_date(): void
    {
        $group = MentoringGroup::factory()->create();

        $this->actingAs($this->boardMember())->post(route('sessions.store'), $this->sessionPayload($group, [
            'is_recurring' => '1',
            'repeat_every_weeks' => '2',
            'repeat_until' => '2026-11-10',
        ]));

        $series = AfterHoursSessionSeries::query()->sole();

        $this->assertSame(2, $series->weekday);
        $this->assertSame(2, $series->interval_weeks);
        $this->assertSame(
            [
                '2026-09-15 16:30:00 – 18:00',
                '2026-09-29 16:30:00 – 18:00',
                '2026-10-13 16:30:00 – 18:00',
                '2026-10-27 16:30:00 – 18:00',
                '2026-11-10 16:30:00 – 18:00',
            ],
            $series->sessions()->orderBy('starts_at')->get()->map(
                static fn (AfterHoursSession $session): string => $session->starts_at->toDateTimeString().' – '.$session->ends_at->format('H:i'),
            )->all(),
        );
    }

    public function test_weekly_recurrence_skips_dates_the_halaqah_already_meets(): void
    {
        $group = MentoringGroup::factory()->create();
        AfterHoursSession::factory()->for($group, 'group')->create([
            'starts_at' => '2026-09-22 16:30:00',
            'ends_at' => '2026-09-22 18:00:00',
        ]);

        $this->actingAs($this->boardMember())->post(route('sessions.store'), $this->sessionPayload($group, [
            'is_recurring' => '1',
            'repeat_every_weeks' => '1',
            'repeat_until' => '2026-10-06',
        ]));

        $this->assertSame(
            ['2026-09-15', '2026-09-29', '2026-10-06'],
            AfterHoursSession::query()->whereNotNull('series_id')->orderBy('starts_at')->get()->map(
                static fn (AfterHoursSession $session): string => $session->starts_at->toDateString(),
            )->all(),
        );
    }

    public function test_every_meeting_of_a_series_gets_its_own_attendance_list(): void
    {
        $group = MentoringGroup::factory()->create();
        User::factory()->count(2)->create()->each(
            static fn (User $member) => $group->memberships()->create([
                'user_id' => $member->id,
                'joined_at' => '2026-09-01',
                'is_active' => true,
            ]),
        );

        $this->actingAs($this->boardMember())->post(route('sessions.store'), $this->sessionPayload($group, [
            'is_recurring' => '1',
            'repeat_every_weeks' => '2',
            'repeat_until' => '2026-09-29',
        ]));

        $this->assertDatabaseCount('after_hours_sessions', 2);
        $this->assertDatabaseCount('attendances', 4);
    }

    public function test_a_series_longer_than_six_months_is_refused(): void
    {
        $group = MentoringGroup::factory()->create();

        $response = $this->actingAs($this->boardMember())
            ->from(route('sessions.create'))
            ->post(route('sessions.store'), $this->sessionPayload($group, [
                'is_recurring' => '1',
                'repeat_every_weeks' => '2',
                'repeat_until' => '2027-03-16',
            ]));

        $response->assertRedirect(route('sessions.create'))
            ->assertSessionHasErrors(['repeat_until' => 'Kegiatan berulang paling lama 6 bulan dari kegiatan pertama.']);

        $this->assertDatabaseCount('after_hours_sessions', 0);
    }

    public function test_a_series_of_exactly_six_months_is_accepted(): void
    {
        $group = MentoringGroup::factory()->create();

        $response = $this->actingAs($this->boardMember())
            ->post(route('sessions.store'), $this->sessionPayload($group, [
                'is_recurring' => '1',
                'repeat_every_weeks' => '2',
                'repeat_until' => '2027-03-15',
            ]));

        $response->assertSessionHasNoErrors();

        $this->assertSame('2027-03-15', AfterHoursSessionSeries::query()->sole()->ends_on->toDateString());
    }

    public function test_a_recurring_session_must_end_on_the_day_it_starts(): void
    {
        $group = MentoringGroup::factory()->create();

        $response = $this->actingAs($this->boardMember())
            ->from(route('sessions.create'))
            ->post(route('sessions.store'), $this->sessionPayload($group, [
                'ends_at' => '2026-09-16T01:00',
                'is_recurring' => '1',
                'repeat_every_weeks' => '2',
                'repeat_until' => '2026-10-13',
            ]));

        $response->assertSessionHasErrors(['ends_at' => 'Kegiatan berulang harus selesai di hari yang sama dengan mulainya.']);

        $this->assertDatabaseCount('after_hours_sessions', 0);
    }

    public function test_editing_a_session_does_not_change_its_time(): void
    {
        $session = AfterHoursSession::factory()->create([
            'starts_at' => '2026-09-15 16:30:00',
            'ends_at' => '2026-09-15 18:00:00',
        ]);

        $this->actingAs($this->boardMember())->put(route('sessions.update', $session), $this->sessionPayload($session->group, [
            'topic' => 'Materi baru',
            'starts_at' => '2026-09-20T08:00',
            'ends_at' => '2026-09-20T09:00',
        ]));

        $session->refresh();

        $this->assertSame('Materi baru', $session->topic);
        $this->assertSame('2026-09-15 16:30:00', $session->starts_at->toDateTimeString());
    }

    public function test_the_schedule_form_offers_saved_places_and_repetition(): void
    {
        Location::factory()->create(['name' => 'Musholla Erdigma']);

        $response = $this->actingAs($this->boardMember())->get(route('sessions.create'));

        $response->assertOk()
            ->assertSee('<option value="Musholla Erdigma"', false)
            ->assertSee('data-creatable', false)
            ->assertSee('name="is_recurring"', false);
    }

    public function test_the_edit_form_leaves_moving_the_session_to_jadwal_ulang(): void
    {
        $session = AfterHoursSession::factory()->create([
            'starts_at' => '2026-09-15 16:30:00',
            'ends_at' => '2026-09-15 18:00:00',
        ]);

        $response = $this->actingAs($this->boardMember())->get(route('sessions.edit', $session));

        $response->assertOk()
            ->assertSee(route('sessions.reschedule.edit', $session))
            ->assertDontSee('name="starts_at"', false);
    }

    public function test_session_details_show_where_it_moved_from_and_how_it_repeats(): void
    {
        $series = AfterHoursSessionSeries::factory()->create([
            'weekday' => 2,
            'interval_weeks' => 2,
            'ends_on' => '2026-12-15',
        ]);
        $session = AfterHoursSession::factory()->create([
            'mentoring_group_id' => $series->mentoring_group_id,
            'series_id' => $series->id,
            'starts_at' => '2026-09-17 19:00:00',
            'ends_at' => '2026-09-17 20:30:00',
            'rescheduled_from' => '2026-09-15 16:30:00',
            'reschedule_reason' => 'Mentor berhalangan hadir',
        ]);

        $response = $this->actingAs($this->boardMember())->get(route('sessions.show', $session));

        $response->assertOk()
            ->assertSee('Dijadwal ulang')
            ->assertSee('Mentor berhalangan hadir')
            ->assertSee('Setiap 2 minggu, hari Selasa');
    }

    public function test_a_completed_session_still_takes_its_last_reading(): void
    {
        $session = $this->completedSession();

        $response = $this->actingAs($this->boardMember())->put(route('sessions.update', $session), [
            'status' => SessionStatus::Completed->value,
            'summary' => 'Al-Baqarah ayat 24',
        ]);

        $response->assertRedirect(route('sessions.show', $session))->assertSessionHasNoErrors();

        $this->assertSame('Al-Baqarah ayat 24', $session->refresh()->summary);
    }

    public function test_a_session_completed_by_mistake_can_be_reopened(): void
    {
        $session = $this->completedSession();

        $this->actingAs($this->boardMember())->put(route('sessions.update', $session), [
            'status' => SessionStatus::Ongoing->value,
        ])->assertSessionHasNoErrors();

        $this->assertSame(SessionStatus::Ongoing, $session->refresh()->status);
    }

    public function test_a_completed_session_keeps_its_other_details_even_when_they_are_sent(): void
    {
        $session = $this->completedSession(['topic' => 'Tahsin Al-Quran', 'location' => 'Musholla Erdigma']);

        $this->actingAs($this->boardMember())->put(route('sessions.update', $session), $this->sessionPayload($session->group, [
            'topic' => 'Materi baru',
            'location' => 'Aula Lantai 3',
            'is_public' => '0',
            'status' => SessionStatus::Completed->value,
            'summary' => 'An-Nisa ayat 11',
        ]))->assertSessionHasNoErrors();

        $session->refresh();

        $this->assertSame('Tahsin Al-Quran', $session->topic);
        $this->assertSame('Musholla Erdigma', $session->location);
        $this->assertTrue($session->is_public);
        $this->assertSame('An-Nisa ayat 11', $session->summary);
    }

    public function test_the_halaqahs_mentor_can_write_the_reading_of_a_completed_session(): void
    {
        $session = $this->completedSession();

        $this->actingAs($session->group->mentor)->put(route('sessions.update', $session), [
            'status' => SessionStatus::Completed->value,
            'summary' => 'Al-Kahfi ayat 10',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Al-Kahfi ayat 10', $session->refresh()->summary);
    }

    public function test_a_mentor_cannot_write_the_reading_of_another_halaqahs_session(): void
    {
        $session = $this->completedSession();

        $response = $this->actingAs(User::factory()->mentor()->create())->put(route('sessions.update', $session), [
            'status' => SessionStatus::Completed->value,
            'summary' => 'Al-Kahfi ayat 10',
        ]);

        $response->assertForbidden();

        $this->assertNull($session->refresh()->summary);
    }

    public function test_the_edit_form_of_a_completed_session_leaves_only_status_and_reading_open(): void
    {
        $session = $this->completedSession();

        $response = $this->actingAs($this->boardMember())->get(route('sessions.edit', $session));

        $response->assertOk()
            ->assertSee('Kegiatan ini sudah selesai.')
            ->assertSee('Bacaan Terakhir')
            ->assertDontSee('data-confirm-if', false);

        $html = $response->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*name="topic"[^>]*disabled/', $html);
        $this->assertDoesNotMatchRegularExpression('/<textarea[^>]*name="summary"[^>]*disabled/', $html);
        $this->assertDoesNotMatchRegularExpression('/<select[^>]*name="status"[^>]*disabled/', $html);
    }

    public function test_the_edit_form_asks_before_a_session_is_completed(): void
    {
        $session = AfterHoursSession::factory()->create();

        $response = $this->actingAs($this->boardMember())->get(route('sessions.edit', $session));

        $response->assertOk()
            ->assertSee('data-confirm-if="status=completed"', false)
            ->assertDontSee('Kegiatan ini sudah selesai.');
    }

    public function test_session_details_show_the_last_reading(): void
    {
        $session = $this->completedSession(['summary' => 'Al-Baqarah ayat 24']);

        $response = $this->actingAs($this->boardMember())->get(route('sessions.show', $session));

        $response->assertOk()
            ->assertSee('Bacaan Terakhir')
            ->assertSee('Al-Baqarah ayat 24');
    }

    /**
     * Last Tuesday's meeting, already marked Selesai.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function completedSession(array $attributes = []): AfterHoursSession
    {
        return AfterHoursSession::factory()->completed()->create([
            'starts_at' => '2026-09-08 16:30:00',
            'ends_at' => '2026-09-08 18:00:00',
            ...$attributes,
        ]);
    }

    /**
     * A Tuesday meeting, 16:30 to 18:00, as the create form would post it.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function sessionPayload(MentoringGroup $group, array $overrides = []): array
    {
        return [
            'mentoring_group_id' => $group->id,
            'topic' => 'Kajian Tafsir Al-Kahfi',
            'starts_at' => '2026-09-15T16:30',
            'ends_at' => '2026-09-15T18:00',
            'status' => SessionStatus::Scheduled->value,
            'is_qr_enabled' => '1',
            'is_public' => '1',
            ...$overrides,
        ];
    }

    private function boardMember(): User
    {
        return User::factory()->withRole(UserRole::DkmAdmin)->create();
    }
}
