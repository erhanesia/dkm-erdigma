<?php

namespace Tests\Feature\Http\Controllers\Web\Portal;

use App\Models\AfterHoursSession;
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
         * The public layout shows today's prayer times and generates any that
         * are missing. With no official city configured that stays a local
         * calculation, and no test here may reach the network.
         */
        config(['dkm.official_schedule.city_id' => '']);
        Http::preventStrayRequests();

        $this->travelTo('2026-09-14 09:00:00');
    }

    public function test_a_moved_session_is_marked_as_rescheduled_on_its_public_page(): void
    {
        $session = AfterHoursSession::factory()->create([
            'starts_at' => '2026-09-17 19:00:00',
            'ends_at' => '2026-09-17 20:30:00',
            'rescheduled_from' => '2026-09-15 16:30:00',
        ]);

        $response = $this->get(route('portal.sessions.show', ['session' => $session->id]));

        $response->assertOk()->assertSee('Dijadwal ulang');
    }

    public function test_a_session_that_never_moved_carries_no_rescheduled_mark(): void
    {
        $session = AfterHoursSession::factory()->create([
            'starts_at' => '2026-09-15 16:30:00',
            'ends_at' => '2026-09-15 18:00:00',
        ]);

        $response = $this->get(route('portal.sessions.show', ['session' => $session->id]));

        $response->assertOk()->assertDontSee('Dijadwal ulang');
    }
}
