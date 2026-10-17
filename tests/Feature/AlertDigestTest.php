<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use App\Notifications\AlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AlertDigestTest extends TestCase
{
    use RefreshDatabase;

    private User $demo;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->demo = $this->demoUser();
    }

    /**
     * Support's ask: a busy day is one email, not one per listing or per search.
     */
    public function test_a_buyer_gets_one_email_for_all_their_unsent_alerts(): void
    {
        $leeds = SavedSearch::factory()->for($this->demo)->create(['region' => 'Leeds']);
        $flats = SavedSearch::factory()->for($this->demo)->create(['property_type' => 'flat']);
        Alert::factory(3)->for($this->demo)->create(['saved_search_id' => $leeds->id]);
        Alert::factory(2)->for($this->demo)->create(['saved_search_id' => $flats->id]);

        $this->artisan('alerts:send-digests')->assertSuccessful();

        Notification::assertSentToTimes($this->demo, AlertNotification::class, 1);
        Notification::assertSentTo($this->demo, AlertNotification::class, fn (AlertNotification $n) => $n->alerts->count() === 5);
        $this->assertSame(0, Alert::query()->unsent()->count());
    }

    public function test_each_buyer_gets_their_own_digest(): void
    {
        $other = User::factory()->create();
        Alert::factory()->for($this->demo)->create();
        Alert::factory()->for($other)->create();

        $this->artisan('alerts:send-digests')->assertSuccessful();

        Notification::assertSentToTimes($this->demo, AlertNotification::class, 1);
        Notification::assertSentToTimes($other, AlertNotification::class, 1);
    }

    public function test_alerts_already_emailed_are_not_sent_again(): void
    {
        Alert::factory()->for($this->demo)->sent()->create();

        $this->artisan('alerts:send-digests')->assertSuccessful();
        $this->artisan('alerts:send-digests')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_a_listing_that_left_the_market_is_left_out(): void
    {
        $gone = Alert::factory()->for($this->demo)->create(['listing_id' => Listing::factory()->sold()]);
        $live = Alert::factory()->for($this->demo)->create();

        $this->artisan('alerts:send-digests')->assertSuccessful();

        Notification::assertSentTo($this->demo, AlertNotification::class, fn (AlertNotification $n) => $n->alerts->modelKeys() === [$live->id]);
        $this->assertNull($gone->fresh()->sent_at);
    }

    public function test_the_digest_lists_every_listing_under_its_search(): void
    {
        $search = SavedSearch::factory()->for($this->demo)->create(['region' => 'Leeds']);
        $alerts = Alert::factory(2)->for($this->demo)->create(['saved_search_id' => $search->id]);
        $alerts->load(['listing.branch', 'savedSearch']);

        $mail = (new AlertNotification($alerts))->toMail($this->demo);
        $html = (string) $mail->render();

        $this->assertSame('2 new properties match your saved searches', $mail->subject);
        $this->assertStringContainsString('Any property in Leeds', $html);
        foreach ($alerts as $alert) {
            $this->assertStringContainsString(route('listings.show', $alert->listing), $html);
        }
    }
}
