<?php

namespace Tests\Feature\Http\Controllers\Web\Schedule;

use App\Enums\PrayerName;
use App\Enums\UserRole;
use App\Models\PrayerDuty;
use App\Models\PrayerSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PrayerDutyControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Rendering the roster generates any prayer times it is missing. With
         * no official city configured that stays a local calculation, and no
         * test here may reach the network.
         */
        config(['dkm.official_schedule.city_id' => '']);
        Http::preventStrayRequests();

        // A Wednesday, so the working week runs from Monday 14 to Friday 18 September.
        $this->travelTo('2026-09-16 09:00:00');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function printoutRoutes(): array
    {
        return [
            'preview' => ['prayer-duties.print'],
            'pdf' => ['prayer-duties.pdf'],
        ];
    }

    #[DataProvider('printoutRoutes')]
    public function test_guests_are_redirected_to_login_from_the_printout(string $routeName): void
    {
        $response = $this->get(route($routeName));

        $response->assertRedirect(route('login'));
    }

    #[DataProvider('printoutRoutes')]
    public function test_employees_are_forbidden_from_the_printout(string $routeName): void
    {
        $employee = User::factory()->withRole(UserRole::Employee)->create();

        $response = $this->actingAs($employee)->get(route($routeName));

        $response->assertForbidden();
    }

    public function test_roster_offers_an_imam_and_a_muadzin_for_dhuhr_and_asr_only(): void
    {
        $response = $this->actingAs($this->boardMember())
            ->get(route('prayer-duties.index', ['week' => '2026-09-14']));

        $response->assertOk()
            ->assertSee('name="duties[2026-09-14][dhuhr][imam_id]"', false)
            ->assertSee('name="duties[2026-09-14][dhuhr][muadzin_id]"', false)
            ->assertSee('name="duties[2026-09-14][asr][imam_id]"', false)
            ->assertSee('name="duties[2026-09-14][asr][muadzin_id]"', false)
            ->assertDontSee('duties[2026-09-14][fajr]', false)
            ->assertDontSee('duties[2026-09-14][maghrib]', false)
            ->assertDontSee('duties[2026-09-14][isha]', false);
    }

    public function test_roster_leaves_out_saturday_and_sunday(): void
    {
        $response = $this->actingAs($this->boardMember())
            ->get(route('prayer-duties.index', ['week' => '2026-09-14']));

        $response->assertOk()
            ->assertSee('duties[2026-09-18]', false)
            ->assertDontSee('duties[2026-09-19]', false)
            ->assertDontSee('duties[2026-09-20]', false);
    }

    public function test_print_button_opens_the_sheet_on_the_week_being_shown(): void
    {
        $response = $this->actingAs($this->boardMember())
            ->get(route('prayer-duties.index', ['week' => '2026-09-30']));

        $response->assertOk()->assertSee(
            e(route('prayer-duties.print', ['from' => '2026-09-28', 'to' => '2026-10-02'])),
            false,
        );
    }

    public function test_saving_the_week_stores_the_imam_and_the_muadzin(): void
    {
        $imam = User::factory()->create();
        $muadzin = User::factory()->create();

        $response = $this->actingAs($this->boardMember())->put(route('prayer-duties.update'), [
            'week_start' => '2026-09-14',
            'duties' => [
                '2026-09-14' => [
                    'dhuhr' => ['imam_id' => $imam->id, 'muadzin_id' => $muadzin->id],
                ],
            ],
        ]);

        $response->assertRedirect(route('prayer-duties.index', ['week' => '2026-09-14']));

        $this->assertDatabaseHas('prayer_duties', [
            'date' => '2026-09-14',
            'prayer' => PrayerName::Dhuhr->value,
            'imam_id' => $imam->id,
            'muadzin_id' => $muadzin->id,
        ]);
    }

    public function test_saving_ignores_prayers_the_roster_does_not_cover(): void
    {
        $muadzin = User::factory()->create();

        $this->actingAs($this->boardMember())->put(route('prayer-duties.update'), [
            'week_start' => '2026-09-14',
            'duties' => [
                '2026-09-14' => [
                    'fajr' => ['muadzin_id' => $muadzin->id],
                    'asr' => ['muadzin_id' => $muadzin->id],
                ],
            ],
        ]);

        $this->assertDatabaseMissing('prayer_duties', ['prayer' => PrayerName::Fajr->value]);
        $this->assertDatabaseHas('prayer_duties', [
            'prayer' => PrayerName::Asr->value,
            'muadzin_id' => $muadzin->id,
        ]);
    }

    public function test_saving_ignores_saturday_and_sunday(): void
    {
        $muadzin = User::factory()->create();

        $this->actingAs($this->boardMember())->put(route('prayer-duties.update'), [
            'week_start' => '2026-09-14',
            'duties' => [
                '2026-09-18' => ['dhuhr' => ['muadzin_id' => $muadzin->id]],
                '2026-09-19' => ['dhuhr' => ['muadzin_id' => $muadzin->id]],
                '2026-09-20' => ['dhuhr' => ['muadzin_id' => $muadzin->id]],
            ],
        ]);

        $this->assertDatabaseHas('prayer_duties', ['date' => '2026-09-18', 'muadzin_id' => $muadzin->id]);
        $this->assertDatabaseMissing('prayer_duties', ['date' => '2026-09-19']);
        $this->assertDatabaseMissing('prayer_duties', ['date' => '2026-09-20']);
    }

    public function test_clearing_only_the_muadzin_keeps_the_imam(): void
    {
        $duty = PrayerDuty::factory()->create(['date' => '2026-09-14', 'prayer' => PrayerName::Dhuhr]);

        $this->actingAs($this->boardMember())->put(route('prayer-duties.update'), [
            'week_start' => '2026-09-14',
            'duties' => [
                '2026-09-14' => [
                    'dhuhr' => ['imam_id' => $duty->imam_id, 'muadzin_id' => null],
                ],
            ],
        ]);

        $this->assertDatabaseHas('prayer_duties', [
            'id' => $duty->id,
            'imam_id' => $duty->imam_id,
            'muadzin_id' => null,
        ]);
    }

    public function test_clearing_both_roles_removes_the_duty(): void
    {
        $duty = PrayerDuty::factory()->create(['date' => '2026-09-14', 'prayer' => PrayerName::Dhuhr]);

        $this->actingAs($this->boardMember())->put(route('prayer-duties.update'), [
            'week_start' => '2026-09-14',
            'duties' => [
                '2026-09-14' => [
                    'dhuhr' => ['imam_id' => null, 'muadzin_id' => null],
                ],
            ],
        ]);

        $this->assertModelMissing($duty);
    }

    public function test_print_defaults_to_the_current_week(): void
    {
        $response = $this->actingAs($this->boardMember())->get(route('prayer-duties.print'));

        $response->assertOk()
            ->assertSeeInOrder(['14 September 2026', '18 September 2026'])
            ->assertDontSee('19 September 2026')
            ->assertDontSee('20 September 2026')
            ->assertDontSee('21 September 2026');
    }

    public function test_print_lists_each_rostered_prayer_with_its_time_imam_and_muadzin(): void
    {
        PrayerSchedule::factory()->create([
            'date' => '2026-09-14',
            'fajr' => '04:21:00',
            'dhuhr' => '11:52:00',
            'asr' => '15:08:00',
        ]);
        PrayerDuty::factory()
            ->for(User::factory()->state(['name' => 'Ustadz Abdullah']), 'imam')
            ->for(User::factory()->state(['name' => 'Ahmad Fauzi']), 'muadzin')
            ->create(['date' => '2026-09-14', 'prayer' => PrayerName::Dhuhr]);

        $response = $this->actingAs($this->boardMember())
            ->get(route('prayer-duties.print', ['from' => '2026-09-14', 'to' => '2026-09-14']));

        $response->assertOk()->assertSeeInOrder([
            'Dzuhur', 'Ashar',
            'Senin', '14 September 2026', '11:52', 'Ustadz Abdullah', 'Ahmad Fauzi', '15:08',
        ])->assertDontSee('04:21');
    }

    public function test_print_shows_prayer_times_on_both_sides_of_a_month_boundary(): void
    {
        PrayerSchedule::factory()
            ->count(2)
            ->sequence(
                ['date' => '2026-09-30', 'asr' => '15:05:00'],
                ['date' => '2026-10-01', 'asr' => '15:06:00'],
            )
            ->create();

        $response = $this->actingAs($this->boardMember())
            ->get(route('prayer-duties.print', ['from' => '2026-09-30', 'to' => '2026-10-01']));

        $response->assertOk()
            ->assertSeeInOrder(['30 September 2026', '15:05', '1 Oktober 2026', '15:06']);
    }

    public function test_print_turns_a_backwards_range_around(): void
    {
        $response = $this->actingAs($this->boardMember())
            ->get(route('prayer-duties.print', ['from' => '2026-09-18', 'to' => '2026-09-14']));

        $response->assertOk()
            ->assertSeeInOrder(['14 September 2026', '18 September 2026']);
    }

    public function test_print_accepts_a_range_of_exactly_62_days(): void
    {
        $response = $this->actingAs($this->boardMember())
            ->get(route('prayer-duties.print', ['from' => '2026-09-01', 'to' => '2026-11-01']));

        $response->assertOk()
            ->assertSeeInOrder(['1 September 2026', '30 Oktober 2026']);
    }

    public function test_print_link_with_a_range_longer_than_62_days_opens_the_current_week_and_says_why(): void
    {
        $response = $this->actingAs($this->boardMember())
            ->get(route('prayer-duties.print', ['from' => '2026-09-01', 'to' => '2026-11-02']));

        $response->assertOk()
            ->assertSee('Rentang cetak paling panjang 62 hari.')
            ->assertSeeInOrder(['Senin, 14 September 2026', "Jum'at, 18 September 2026"])
            ->assertDontSee('1 September 2026');
    }

    public function test_print_link_with_a_start_date_that_is_not_a_date_opens_the_current_week_and_says_why(): void
    {
        $response = $this->actingAs($this->boardMember())
            ->get(route('prayer-duties.print', ['from' => 'bukan-tanggal']));

        $response->assertOk()
            ->assertSee('Dari tanggal harus berupa tanggal yang valid.')
            ->assertSeeInOrder(['Senin, 14 September 2026', "Jum'at, 18 September 2026"]);
    }

    public function test_sheet_says_so_when_the_range_holds_no_working_day(): void
    {
        $response = $this->actingAs($this->boardMember())
            ->get(route('prayer-duties.print', ['from' => '2026-09-19', 'to' => '2026-09-20']));

        $response->assertOk()
            ->assertSee('Tidak ada hari kerja (Senin–Jumat) dalam rentang ini.');
    }

    public function test_preview_links_to_the_pdf_of_the_same_range(): void
    {
        $response = $this->actingAs($this->boardMember())
            ->get(route('prayer-duties.print', ['from' => '2026-09-14', 'to' => '2026-09-18']));

        $response->assertOk()->assertSee(
            e(route('prayer-duties.pdf', ['from' => '2026-09-14', 'to' => '2026-09-18'])),
            false,
        );
    }

    public function test_pdf_opens_inline_and_is_named_after_its_range(): void
    {
        $response = $this->actingAs($this->boardMember())
            ->get(route('prayer-duties.pdf', ['from' => '2026-09-14', 'to' => '2026-09-18']));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename=petugas-sholat-2026-09-14-2026-09-18.pdf');

        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_pdf_rejects_a_range_longer_than_62_days(): void
    {
        $response = $this->actingAs($this->boardMember())
            ->from(route('prayer-duties.print'))
            ->get(route('prayer-duties.pdf', ['from' => '2026-09-01', 'to' => '2026-11-02']));

        $response->assertRedirect(route('prayer-duties.print'))
            ->assertSessionHasErrors(['to' => 'Rentang cetak paling panjang 62 hari.']);
    }

    private function boardMember(): User
    {
        return User::factory()->withRole(UserRole::DkmAdmin)->create();
    }
}
