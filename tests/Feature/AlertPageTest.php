<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AlertPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_buyer_sees_only_their_own_alerts_newest_first(): void
    {
        $demo = $this->demoUser();
        $older = Alert::factory()->for($demo)->create();
        $newer = Alert::factory()->for($demo)->sent()->create();
        Alert::factory()->for(User::factory())->create();

        $this->get('/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Alerts/Index')
                ->has('alerts.data', 2)
                ->where('alerts.data.0.id', $newer->id)
                ->where('alerts.data.1.id', $older->id)
                ->where('alerts.data.1.sent_at', null)
                ->where('alerts.data.0.listing_is_live', true)
            );
    }

    /**
     * The listing page 404s once a listing leaves the market, so the alert
     * says so instead of linking to it.
     */
    public function test_an_alert_for_a_listing_no_longer_live_is_marked(): void
    {
        $demo = $this->demoUser();
        Alert::factory()->for($demo)->create(['listing_id' => Listing::factory()->sold()]);

        $this->get('/alerts')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('alerts.data.0.listing_is_live', false)
                ->where('alerts.data.0.listing_status_label', 'Sold')
            );
    }

    public function test_the_page_does_not_query_per_alert(): void
    {
        $demo = $this->demoUser();

        $queriesFor = function (int $alerts) use ($demo): int {
            Alert::query()->delete();
            Alert::factory($alerts)->for($demo)->create();

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get('/alerts')->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $this->assertSame($queriesFor(2), $queriesFor(6));
    }
}
