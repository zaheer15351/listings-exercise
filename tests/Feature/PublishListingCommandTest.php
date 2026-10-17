<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Models\Alert;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\SavedSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PublishListingCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_without_an_id_it_lists_the_drafts(): void
    {
        $draft = Listing::factory()->draft()->create();
        $live = Listing::factory()->live()->create();

        $this->artisan('listings:publish')
            ->expectsOutputToContain($draft->reference)
            ->doesntExpectOutputToContain($live->reference)
            ->assertSuccessful();
    }

    public function test_it_publishes_a_draft_and_reports_the_alerts(): void
    {
        Notification::fake();
        $branch = Branch::factory()->create(['region' => 'Leeds']);
        SavedSearch::factory()->for($this->demoUser())->instant()->create(['region' => 'Leeds']);
        $draft = Listing::factory()->for($branch)->draft()->create();

        $this->artisan('listings:publish', ['listing' => $draft->id])
            ->expectsOutputToContain('1 alert(s) raised: 1 emailed now, 0 waiting for the daily digest')
            ->assertSuccessful();

        $this->assertSame(ListingStatus::Live, $draft->fresh()->status);
        $this->assertSame(1, Alert::query()->count());
    }

    /**
     * A re-listed property already has alerts from the first time it went live;
     * the command reports only what this publish did.
     */
    public function test_a_relisted_property_reports_no_new_alerts(): void
    {
        Notification::fake();
        $branch = Branch::factory()->create(['region' => 'Leeds']);
        SavedSearch::factory()->for($this->demoUser())->instant()->create(['region' => 'Leeds']);
        $listing = Listing::factory()->for($branch)->draft()->create();
        $listing->publish();
        $listing->update(['status' => ListingStatus::UnderOffer]);

        $this->artisan('listings:publish', ['listing' => $listing->id])
            ->expectsOutputToContain('No new alerts')
            ->assertSuccessful();
    }

    public function test_a_live_listing_is_left_alone(): void
    {
        $live = Listing::factory()->live()->create();

        $this->artisan('listings:publish', ['listing' => $live->id])
            ->expectsOutputToContain('already live')
            ->assertSuccessful();
    }

    public function test_an_unknown_id_fails(): void
    {
        $this->artisan('listings:publish', ['listing' => 999])->assertFailed();
    }
}
