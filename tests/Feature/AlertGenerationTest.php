<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Enums\PropertyType;
use App\Models\Alert;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use App\Notifications\AlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AlertGenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $demo;

    private Branch $leeds;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->demo = $this->demoUser();
        $this->leeds = Branch::factory()->create(['region' => 'Leeds']);
    }

    private function draftInLeeds(): Listing
    {
        return Listing::factory()->for($this->leeds)->draft()->create([
            'property_type' => PropertyType::Flat,
            'bedrooms' => 3,
            'price' => 250_000,
        ]);
    }

    public function test_a_matching_listing_going_live_generates_an_alert(): void
    {
        $search = SavedSearch::factory()->for($this->demo)->create(['region' => 'Leeds']);
        $listing = $this->draftInLeeds();

        $listing->publish();

        $alert = Alert::query()->sole();
        $this->assertSame([$this->demo->id, $listing->id, $search->id], [$alert->user_id, $alert->listing_id, $alert->saved_search_id]);
    }

    public function test_a_listing_that_does_not_match_generates_nothing(): void
    {
        SavedSearch::factory()->for($this->demo)->create(['region' => 'Manchester']);

        $this->draftInLeeds()->publish();

        $this->assertSame(0, Alert::query()->count());
    }

    /**
     * Only publish() announces a listing; seeders and imports that write live
     * rows directly raise nothing.
     */
    public function test_a_listing_created_live_generates_nothing(): void
    {
        SavedSearch::factory()->for($this->demo)->create(['region' => 'Leeds']);

        Listing::factory()->for($this->leeds)->live()->create();

        $this->assertSame(0, Alert::query()->count());
    }

    public function test_saving_a_search_does_not_backfill_listings_already_live(): void
    {
        $this->draftInLeeds()->publish();

        $this->post('/saved-searches', ['region' => 'Leeds', 'frequency' => 'instant'])->assertRedirect();

        $this->assertSame(0, Alert::query()->count());
        Notification::assertNothingSent();
    }

    public function test_two_matching_searches_raise_one_alert_owned_by_the_instant_one(): void
    {
        SavedSearch::factory()->for($this->demo)->daily()->create(['region' => 'Leeds']);
        $instant = SavedSearch::factory()->for($this->demo)->instant()->create(['max_price' => 300_000]);

        $this->draftInLeeds()->publish();

        $this->assertSame($instant->id, Alert::query()->sole()->saved_search_id);
        Notification::assertSentToTimes($this->demo, AlertNotification::class, 1);
    }

    public function test_without_an_instant_search_the_oldest_owns_the_alert(): void
    {
        $oldest = SavedSearch::factory()->for($this->demo)->create(['region' => 'Leeds']);
        SavedSearch::factory()->for($this->demo)->create(['max_price' => 300_000]);

        $this->draftInLeeds()->publish();

        $this->assertSame($oldest->id, Alert::query()->sole()->saved_search_id);
    }

    public function test_each_buyer_gets_their_own_alert(): void
    {
        $other = User::factory()->create();
        SavedSearch::factory()->for($this->demo)->create(['region' => 'Leeds']);
        SavedSearch::factory()->for($other)->create(['region' => 'Leeds']);

        $this->draftInLeeds()->publish();

        $this->assertEqualsCanonicalizing([$this->demo->id, $other->id], Alert::query()->pluck('user_id')->all());
    }

    /**
     * The brief asks for alerts on a new listing. A property back on the market
     * goes live again but does not alert a buyer who was already told.
     */
    public function test_a_relisted_property_does_not_alert_the_same_buyer_twice(): void
    {
        SavedSearch::factory()->for($this->demo)->instant()->create(['region' => 'Leeds']);
        $listing = $this->draftInLeeds();

        $listing->publish();
        $listing->update(['status' => ListingStatus::UnderOffer]);
        $listing->publish();

        $this->assertSame(1, Alert::query()->count());
        Notification::assertSentToTimes($this->demo, AlertNotification::class, 1);
    }

    public function test_an_instant_search_is_emailed_straight_away(): void
    {
        SavedSearch::factory()->for($this->demo)->instant()->create(['region' => 'Leeds']);

        $this->draftInLeeds()->publish();

        Notification::assertSentToTimes($this->demo, AlertNotification::class, 1);
        $this->assertNotNull(Alert::query()->sole()->sent_at);
    }

    public function test_a_daily_search_waits_for_the_digest(): void
    {
        SavedSearch::factory()->for($this->demo)->daily()->create(['region' => 'Leeds']);

        $this->draftInLeeds()->publish();

        Notification::assertNothingSent();
        $this->assertNull(Alert::query()->sole()->sent_at);
    }

    public function test_the_instant_email_describes_the_listing_and_links_to_it(): void
    {
        SavedSearch::factory()->for($this->demo)->instant()->create(['region' => 'Leeds', 'max_price' => 300_000]);
        $listing = $this->draftInLeeds();

        $listing->publish();

        Notification::assertSentTo($this->demo, AlertNotification::class, function (AlertNotification $notification) use ($listing) {
            $mail = $notification->toMail($this->demo);
            $html = (string) $mail->render();

            return $mail->subject === 'New property for Any property in Leeds, up to £300,000'
                && str_contains($html, '£250,000 · 3 bed flat')
                && str_contains($html, e($listing->address_line_1))
                && $mail->actionUrl === route('listings.show', $listing);
        });
    }

    /**
     * Alerts must mean "this would now show in the search you saved". The
     * listings filter and the saved-search scope are two implementations of the
     * same rules, so this pins them together: for the same criteria, the
     * listing shows on the index exactly when publishing it raised an alert.
     *
     * @param  array<string, int|string>  $criteria
     */
    #[DataProvider('criteria')]
    public function test_an_alert_is_raised_exactly_when_the_listing_shows_in_that_search(array $criteria): void
    {
        SavedSearch::factory()->for($this->demo)->create($criteria);
        $listing = $this->draftInLeeds();

        $listing->publish();

        $alerted = Alert::query()->where('listing_id', $listing->id)->exists();
        $shown = false;
        $this->get('/?'.http_build_query([...$criteria, 'per_page' => 100]))
            ->assertInertia(function (AssertableInertia $page) use ($listing, &$shown) {
                $shown = collect($page->toArray()['props']['listings']['data'])->contains('id', $listing->id);
            });

        $this->assertSame($shown, $alerted);
    }

    /**
     * @return array<string, array{array<string, int|string>}>
     */
    public static function criteria(): array
    {
        return [
            'type matches' => [['property_type' => 'flat']],
            'type differs' => [['property_type' => 'detached']],
            'region matches' => [['region' => 'Leeds']],
            'region differs' => [['region' => 'Bristol']],
            'bedrooms at the minimum' => [['min_bedrooms' => 3]],
            'bedrooms below the minimum' => [['min_bedrooms' => 4]],
            'price at the minimum' => [['min_price' => 250000]],
            'price below the minimum' => [['min_price' => 250001]],
            'price at the maximum' => [['max_price' => 250000]],
            'price above the maximum' => [['max_price' => 249999]],
            'all criteria hold' => [['property_type' => 'flat', 'region' => 'Leeds', 'min_bedrooms' => 2, 'min_price' => 200000, 'max_price' => 300000]],
        ];
    }
}
