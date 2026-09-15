<?php

namespace Tests\Feature\Http\Controllers\Web\Mentoring;

use App\Enums\SessionStatus;
use App\Enums\UserRole;
use App\Models\AfterHoursSession;
use App\Models\Location;
use App\Models\MentoringGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MentoringGroupControllerTest extends TestCase
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
    }

    public function test_the_halaqah_form_offers_saved_places(): void
    {
        Location::factory()->create(['name' => 'Musholla Erdigma']);

        $response = $this->actingAs(User::factory()->withRole(UserRole::DkmAdmin)->create())
            ->get(route('mentoring-groups.create'));

        $response->assertOk()->assertSee('<option value="Musholla Erdigma"', false);
    }

    public function test_a_new_routine_place_is_saved_to_the_place_list(): void
    {
        $mentor = User::factory()->mentor()->create();

        $this->actingAs(User::factory()->withRole(UserRole::DkmAdmin)->create())
            ->post(route('mentoring-groups.store'), [
                'name' => 'Halaqah Al-Fatih',
                'mentor_id' => $mentor->id,
                'capacity' => 10,
                'default_location' => 'Aula Lantai 3',
                'is_active' => '1',
            ]);

        $location = Location::query()->sole();

        $this->assertSame('Aula Lantai 3', $location->name);
        $this->assertDatabaseHas('mentoring_groups', [
            'name' => 'Halaqah Al-Fatih',
            'default_location' => 'Aula Lantai 3',
            'default_location_id' => $location->id,
        ]);
    }

    public function test_the_halaqah_page_shows_the_reading_of_its_most_recent_meeting(): void
    {
        $this->travelTo('2026-09-14 09:00:00');
        $group = MentoringGroup::factory()->create();

        // Created before the older meeting, so the order cannot come from the ids.
        AfterHoursSession::factory()->completed()->for($group, 'group')->create([
            'topic' => 'Tahsin Pekan Lalu',
            'starts_at' => '2026-09-08 16:30:00',
            'ends_at' => '2026-09-08 18:00:00',
            'summary' => 'Al-Baqarah ayat 24',
        ]);
        AfterHoursSession::factory()->completed()->for($group, 'group')->create([
            'starts_at' => '2026-08-25 16:30:00',
            'ends_at' => '2026-08-25 18:00:00',
            'summary' => 'Al-Baqarah ayat 5',
        ]);
        AfterHoursSession::factory()->for($group, 'group')->create([
            'status' => SessionStatus::Cancelled,
            'starts_at' => '2026-09-10 16:30:00',
            'ends_at' => '2026-09-10 18:00:00',
            'summary' => 'Catatan kegiatan batal',
        ]);
        AfterHoursSession::factory()->for($group, 'group')->create([
            'starts_at' => '2026-09-15 16:30:00',
            'ends_at' => '2026-09-15 18:00:00',
        ]);

        $response = $this->actingAs(User::factory()->withRole(UserRole::DkmAdmin)->create())
            ->get(route('mentoring-groups.show', $group));

        $response->assertOk()
            ->assertSeeInOrder(['Bacaan Terakhir', 'Al-Baqarah ayat 24', 'Tahsin Pekan Lalu'])
            ->assertDontSee('Al-Baqarah ayat 5')
            ->assertDontSee('Catatan kegiatan batal');
    }

    public function test_a_halaqah_without_a_recorded_reading_says_so(): void
    {
        $group = MentoringGroup::factory()->create();

        $response = $this->actingAs($group->mentor)->get(route('mentoring-groups.show', $group));

        $response->assertOk()->assertSee('Belum ada bacaan yang dicatat.');
    }
}
