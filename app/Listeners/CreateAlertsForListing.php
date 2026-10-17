<?php

namespace App\Listeners;

use App\Enums\AlertFrequency;
use App\Events\ListingWentLive;
use App\Models\Alert;
use App\Models\SavedSearch;
use Illuminate\Database\Eloquent\Collection;

/**
 * Generates one alert per buyer whose saved search the new listing matches.
 *
 * Synchronous on purpose: the app runs no queue worker, so a queued listener
 * would silently do nothing. In production this would implement ShouldQueue;
 * nothing else changes.
 */
class CreateAlertsForListing
{
    public function handle(ListingWentLive $event): void
    {
        $listing = $event->listing->loadMissing('branch');

        SavedSearch::query()
            ->matching($listing)
            ->with('user')
            ->orderBy('id')
            ->get()
            ->groupBy('user_id')
            ->each(function (Collection $searches) use ($listing): void {
                $owner = $this->owner($searches);

                // The unique key on (user, listing) makes this idempotent: a
                // buyer already told about the listing is not told again.
                $alert = Alert::query()->createOrFirst(
                    ['user_id' => $owner->user_id, 'listing_id' => $listing->id],
                    ['saved_search_id' => $owner->id],
                );

                if ($alert->wasRecentlyCreated && $owner->frequency === AlertFrequency::Instant) {
                    $owner->user->sendAlerts(new Collection([$alert]));
                }
            });
    }

    /**
     * When several of a buyer's searches match, one alert is raised and this
     * search owns it: an instant search if there is one, so the buyer who asked
     * to hear straight away does, otherwise the oldest.
     *
     * @param  Collection<int, SavedSearch>  $searches  ordered by id
     */
    private function owner(Collection $searches): SavedSearch
    {
        /** @var SavedSearch */
        return $searches->firstWhere('frequency', AlertFrequency::Instant) ?? $searches->first();
    }
}
