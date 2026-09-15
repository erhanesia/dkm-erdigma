<?php

namespace Tests\Feature\Livewire;

use App\Enums\SessionStatus;
use App\Livewire\PublicSessionCalendar;
use App\Models\AfterHoursSession;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PublicSessionCalendarTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * The public layout shows today's prayer times and generates any that
         * are missing. With no official city configured that stays a local
         * calculation, and no test here may reach the network.
         */
        config(['dkm.official_schedule.city_id' => '']);
        Http::preventStrayRequests();

        // A Monday in September 2026.
        $this->travelTo('2026-09-14 09:00:00');
    }

    public function test_the_after_hours_page_carries_the_calendar(): void
    {
        $response = $this->get(route('portal.sessions'));

        $response->assertOk()->assertSeeLivewire(PublicSessionCalendar::class);
    }

    public function test_a_public_session_appears_and_links_to_its_detail_page(): void
    {
        $session = AfterHoursSession::factory()->create([
            'topic' => 'Kajian Tafsir Al-Kahfi',
            'starts_at' => '2026-09-15 16:30:00',
            'ends_at' => '2026-09-15 18:00:00',
        ]);

        Livewire::test(PublicSessionCalendar::class)
            ->assertSee('Kajian Tafsir Al-Kahfi')
            ->assertSeeHtml(e(route('portal.sessions.show', ['session' => $session->id])));
    }

    public function test_each_calendar_card_shows_the_time_speaker_and_place(): void
    {
        AfterHoursSession::factory()
            ->for(User::factory()->state(['name' => 'Ustadz Abdullah']), 'mentor')
            ->create([
                'topic' => 'Kajian Tafsir Al-Kahfi',
                'starts_at' => '2026-09-15 16:30:00',
                'ends_at' => '2026-09-15 18:00:00',
                'location' => 'Aula Lantai 3',
            ]);

        Livewire::test(PublicSessionCalendar::class)
            ->assertSeeInOrder(['16:30–18:00', 'Kajian Tafsir Al-Kahfi', 'Ustadz Abdullah', 'Aula Lantai 3']);
    }

    public function test_each_day_with_sessions_says_how_many_it_holds(): void
    {
        AfterHoursSession::factory()
            ->count(4)
            ->sequence(
                ['starts_at' => '2026-09-16 09:00:00', 'ends_at' => '2026-09-16 10:00:00'],
                ['starts_at' => '2026-09-16 11:00:00', 'ends_at' => '2026-09-16 12:00:00'],
                ['starts_at' => '2026-09-16 13:00:00', 'ends_at' => '2026-09-16 14:00:00'],
                ['starts_at' => '2026-09-16 16:30:00', 'ends_at' => '2026-09-16 18:00:00'],
            )
            ->create();
        AfterHoursSession::factory()->create([
            'starts_at' => '2026-09-17 16:30:00',
            'ends_at' => '2026-09-17 18:00:00',
        ]);

        Livewire::test(PublicSessionCalendar::class)
            ->assertSeeHtml('<span class="landing-calendar-daycount"><strong>4</strong> kegiatan</span>')
            ->assertSeeHtml('<span class="landing-calendar-daycount"><strong>1</strong> kegiatan</span>');
    }

    public function test_a_moved_session_is_flagged_on_its_calendar_card(): void
    {
        AfterHoursSession::factory()->create([
            'starts_at' => '2026-09-17 19:00:00',
            'ends_at' => '2026-09-17 20:30:00',
            'rescheduled_from' => '2026-09-15 16:30:00',
        ]);

        Livewire::test(PublicSessionCalendar::class)->assertSee('Dijadwal ulang');
    }

    public function test_a_month_without_public_sessions_says_so(): void
    {
        Livewire::test(PublicSessionCalendar::class)
            ->assertSee('Belum ada kegiatan yang diumumkan untuk September 2026.');
    }

    public function test_private_and_cancelled_sessions_are_left_off(): void
    {
        AfterHoursSession::factory()->create([
            'topic' => 'Rapat Internal Pengurus',
            'is_public' => false,
            'starts_at' => '2026-09-16 16:30:00',
            'ends_at' => '2026-09-16 18:00:00',
        ]);
        AfterHoursSession::factory()->create([
            'topic' => 'Kajian Yang Dibatalkan',
            'status' => SessionStatus::Cancelled,
            'starts_at' => '2026-09-17 16:30:00',
            'ends_at' => '2026-09-17 18:00:00',
        ]);

        Livewire::test(PublicSessionCalendar::class)
            ->assertDontSee('Rapat Internal Pengurus')
            ->assertDontSee('Kajian Yang Dibatalkan');
    }

    public function test_sessions_already_held_still_appear_in_their_month(): void
    {
        AfterHoursSession::factory()->completed()->create([
            'topic' => 'Kajian Fiqih Muamalah',
            'starts_at' => '2026-06-10 16:30:00',
            'ends_at' => '2026-06-10 18:00:00',
        ]);

        Livewire::withQueryParams(['bulan' => '2026-06'])
            ->test(PublicSessionCalendar::class)
            ->assertSet('month', '2026-06')
            ->assertSee('Kajian Fiqih Muamalah');
    }

    public function test_the_grid_runs_monday_to_sunday_across_the_month_edges(): void
    {
        Livewire::test(PublicSessionCalendar::class)
            ->assertViewHas('weeks', static fn (array $weeks): bool => $weeks[0][0]->toDateString() === '2026-08-31'
                && end($weeks)[6]->toDateString() === '2026-10-04');
    }

    public function test_the_arrows_step_one_month_at_a_time(): void
    {
        // Something held in June, so the months before this one can be opened.
        AfterHoursSession::factory()->completed()->create([
            'starts_at' => '2026-06-10 16:30:00',
            'ends_at' => '2026-06-10 18:00:00',
        ]);

        Livewire::test(PublicSessionCalendar::class)
            ->call('nextMonth')
            ->assertSet('month', '2026-10')
            ->call('previousMonth')
            ->call('previousMonth')
            ->assertSet('month', '2026-08');
    }

    public function test_the_calendar_opens_as_far_back_as_the_first_public_session(): void
    {
        AfterHoursSession::factory()->completed()->create([
            'starts_at' => '2024-09-03 16:30:00',
            'ends_at' => '2024-09-03 18:00:00',
        ]);

        Livewire::withQueryParams(['bulan' => '2024-09'])
            ->test(PublicSessionCalendar::class)
            ->assertSet('month', '2024-09')
            ->assertViewHas('hasPreviousMonth', false);
    }

    public function test_a_month_before_the_first_public_session_falls_back_to_this_month(): void
    {
        AfterHoursSession::factory()->completed()->create([
            'starts_at' => '2026-03-05 16:30:00',
            'ends_at' => '2026-03-05 18:00:00',
        ]);

        Livewire::withQueryParams(['bulan' => '2026-02'])
            ->test(PublicSessionCalendar::class)
            ->assertSet('month', '2026-09');
    }

    public function test_the_calendar_opens_at_most_six_months_ahead(): void
    {
        Livewire::withQueryParams(['bulan' => '2027-03'])
            ->test(PublicSessionCalendar::class)
            ->assertSet('month', '2027-03')
            ->assertViewHas('hasNextMonth', false);
    }

    public function test_a_month_beyond_six_months_ahead_falls_back_to_this_month(): void
    {
        Livewire::withQueryParams(['bulan' => '2027-04'])
            ->test(PublicSessionCalendar::class)
            ->assertSet('month', '2026-09');
    }

    public function test_picking_a_year_lands_on_the_nearest_month_that_year_can_open(): void
    {
        AfterHoursSession::factory()->completed()->create([
            'starts_at' => '2025-11-04 16:30:00',
            'ends_at' => '2025-11-04 18:00:00',
        ]);

        Livewire::test(PublicSessionCalendar::class)
            ->call('selectYear', 2025)
            ->assertSet('month', '2025-11');
    }
}
