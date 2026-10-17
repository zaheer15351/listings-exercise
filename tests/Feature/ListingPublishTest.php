<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Events\ListingWentLive;
use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ListingPublishTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishing_a_draft_puts_it_live_today_and_announces_it(): void
    {
        Event::fake([ListingWentLive::class]);
        $this->freezeSecond();
        $listing = Listing::factory()->draft()->create();

        $listing->publish();

        $this->assertSame(ListingStatus::Live, $listing->fresh()->status);
        $this->assertTrue($listing->fresh()->listed_at->equalTo(now()));
        Event::assertDispatchedTimes(ListingWentLive::class, 1);
    }

    public function test_a_relisted_property_keeps_its_original_listing_date(): void
    {
        Event::fake([ListingWentLive::class]);
        $listing = Listing::factory()->underOffer()->create();
        $listedAt = $listing->listed_at;

        $listing->publish();

        $this->assertTrue($listing->fresh()->listed_at->equalTo($listedAt));
        Event::assertDispatched(ListingWentLive::class);
    }

    public function test_publishing_a_live_listing_does_nothing(): void
    {
        Event::fake([ListingWentLive::class]);
        $listing = Listing::factory()->live()->create();

        $listing->publish();

        Event::assertNotDispatched(ListingWentLive::class);
    }

    /**
     * A published draft used to keep a null listed_at and sort onto the last
     * page of the newest-first index.
     */
    public function test_a_published_draft_sorts_first_on_the_index(): void
    {
        Listing::factory(3)->live()->create();
        $draft = Listing::factory()->draft()->create();

        $draft->publish();

        $this->get('/')->assertInertia(fn ($page) => $page->where('listings.data.0.id', $draft->id));
    }
}
