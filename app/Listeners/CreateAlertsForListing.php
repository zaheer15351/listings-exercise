<?php

namespace App\Listeners;

use App\Actions\SendAlerts;
use App\Enums\AlertFrequency;
use App\Events\ListingWentLive;
use App\Models\Alert;
use App\Models\Listing;
use App\Models\SavedSearch;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\LazyCollection;

/**
 * Generates one alert per buyer whose saved search the new listing matches.
 *
 * Matching searches are streamed a page at a time, ordered by buyer, so memory
 * holds one page rather than every match.
 *
 * Synchronous on purpose: the README's two-terminal setup runs no queue worker,
 * so a queued listener would silently do nothing for anyone running it that way.
 * In production this would implement ShouldQueue; nothing else changes.
 */
class CreateAlertsForListing
{
    public function __construct(private readonly SendAlerts $sendAlerts) {}

    public function handle(ListingWentLive $event): void
    {
        $listing = $event->listing->loadMissing('branch');

        SavedSearch::query()
            ->matching($listing)
            ->with('user')
            ->orderBy('user_id')
            ->orderBy('id')
            ->lazy()
            // One buyer's searches arrive together, so each run is one buyer.
            ->chunkWhile(fn (SavedSearch $search, int $key, Enumerable $buyer) => $search->user_id === $buyer->last()->user_id)
            ->each(fn (LazyCollection $searches) => $this->alert($listing, $this->owner($searches->collect())));
    }

    private function alert(Listing $listing, SavedSearch $owner): void
    {
        // The unique key on (user, listing) makes this idempotent: a buyer
        // already told about the listing is not told again.
        $alert = Alert::query()->createOrFirst(
            ['user_id' => $owner->user_id, 'listing_id' => $listing->id],
            ['saved_search_id' => $owner->id],
        );

        if ($alert->wasRecentlyCreated && $owner->frequency === AlertFrequency::Instant) {
            $this->sendAlerts->handle($owner->user, new EloquentCollection([$alert]));
        }
    }

    /**
     * When several of a buyer's searches match, one alert is raised and this
     * search owns it: an instant search if there is one, so the buyer who asked
     * to hear straight away does, otherwise the oldest.
     *
     * @param  Collection<int, SavedSearch>  $searches  one buyer's, ordered by id
     */
    private function owner(Collection $searches): SavedSearch
    {
        /** @var SavedSearch */
        return $searches->firstWhere('frequency', AlertFrequency::Instant) ?? $searches->first();
    }
}
