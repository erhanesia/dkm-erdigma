<?php

namespace Tests\Feature\Http\Controllers\Web\Mentoring;

use App\Enums\UserRole;
use App\Models\Location;
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
}
