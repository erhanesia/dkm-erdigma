<?php

namespace Tests\Feature\Livewire;

use App\Enums\PrayerName;
use App\Livewire\PrayerDutyPrintPreview;
use App\Models\PrayerDuty;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class PrayerDutyPrintPreviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Rendering the sheet generates any prayer times it is missing. With no
         * official city configured that stays a local calculation, and no test
         * here may reach the network.
         */
        config(['dkm.official_schedule.city_id' => '']);
        Http::preventStrayRequests();

        // A Wednesday, so the working week runs from Monday 14 to Friday 18 September.
        $this->travelTo('2026-09-16 09:00:00');
    }

    public function test_applying_a_range_redraws_the_sheet_and_the_pdf_link(): void
    {
        PrayerDuty::factory()
            ->for(User::factory()->state(['name' => 'Ustadz Abdullah']), 'imam')
            ->create(['date' => '2026-09-22', 'prayer' => PrayerName::Dhuhr]);

        Livewire::withQueryParams(['from' => '2026-09-14', 'to' => '2026-09-18'])
            ->test(PrayerDutyPrintPreview::class)
            ->set('fromInput', '2026-09-21')
            ->set('toInput', '2026-09-25')
            ->call('apply')
            ->assertHasNoErrors()
            ->assertSet('from', '2026-09-21')
            ->assertSet('to', '2026-09-25')
            ->assertSeeInOrder(['Senin, 21 September 2026', 'Ustadz Abdullah', "Jum'at, 25 September 2026"])
            ->assertDontSee('14 September 2026')
            ->assertSeeHtml(e(route('prayer-duties.pdf', ['from' => '2026-09-21', 'to' => '2026-09-25'])));
    }

    public function test_a_range_typed_backwards_is_turned_around_in_the_form_too(): void
    {
        Livewire::test(PrayerDutyPrintPreview::class)
            ->set('fromInput', '2026-09-25')
            ->set('toInput', '2026-09-21')
            ->call('apply')
            ->assertSet('from', '2026-09-21')
            ->assertSet('to', '2026-09-25')
            ->assertSet('fromInput', '2026-09-21')
            ->assertSet('toInput', '2026-09-25');
    }

    public function test_a_range_longer_than_62_days_keeps_the_sheet_on_the_last_applied_range(): void
    {
        Livewire::test(PrayerDutyPrintPreview::class)
            ->set('fromInput', '2026-09-01')
            ->set('toInput', '2026-11-02')
            ->call('apply')
            ->assertHasErrors(['to'])
            ->assertSee('Rentang cetak paling panjang 62 hari.')
            ->assertSet('from', '2026-09-14')
            ->assertSet('to', '2026-09-18')
            ->assertSeeInOrder(['Senin, 14 September 2026', "Jum'at, 18 September 2026"]);
    }

    public function test_a_start_date_that_is_not_a_date_is_refused(): void
    {
        Livewire::test(PrayerDutyPrintPreview::class)
            ->set('fromInput', 'bukan-tanggal')
            ->call('apply')
            ->assertHasErrors(['from'])
            ->assertSee('Dari tanggal harus berupa tanggal yang valid.')
            ->assertSet('from', '2026-09-14');
    }

    public function test_the_applied_range_cannot_be_changed_from_the_browser(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(PrayerDutyPrintPreview::class)->set('to', '2030-12-31');
    }
}
