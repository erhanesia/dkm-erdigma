<?php

namespace Tests\Feature\Http\Controllers\Web\Mentoring;

use App\Enums\UserRole;
use App\Models\AfterHoursSession;
use App\Models\MentoringGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MyAfterHoursControllerTest extends TestCase
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

        $this->travelTo('2026-09-14 09:00:00');
    }

    public function test_a_member_sees_the_last_reading_of_their_own_halaqah(): void
    {
        $group = MentoringGroup::factory()->create();
        $member = $this->memberOf($group);

        AfterHoursSession::factory()->completed()->for($group, 'group')->create([
            'starts_at' => '2026-09-08 16:30:00',
            'ends_at' => '2026-09-08 18:00:00',
            'summary' => 'Al-Kahfi ayat 10',
        ]);
        AfterHoursSession::factory()->completed()->create([
            'starts_at' => '2026-09-09 16:30:00',
            'ends_at' => '2026-09-09 18:00:00',
            'summary' => 'Yasin ayat 40',
        ]);

        $response = $this->actingAs($member)->get(route('my-after-hours.index'));

        $response->assertOk()
            ->assertSeeInOrder(['Bacaan Terakhir', 'Al-Kahfi ayat 10'])
            ->assertDontSee('Yasin ayat 40');
    }

    public function test_a_member_is_told_when_no_reading_has_been_recorded_yet(): void
    {
        $member = $this->memberOf(MentoringGroup::factory()->create());

        $response = $this->actingAs($member)->get(route('my-after-hours.index'));

        $response->assertOk()->assertSee('Belum ada bacaan yang dicatat.');
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
}
