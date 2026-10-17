<?php

namespace App\Console\Commands;

use App\Enums\ListingStatus;
use App\Models\Alert;
use App\Models\Listing;
use Illuminate\Console\Command;

/**
 * Stands in for the admin action that would put a listing on the market, so
 * the alert flow can be seen end to end. Without an id it lists the drafts.
 */
class PublishListing extends Command
{
    protected $signature = 'listings:publish {listing? : The id of the listing to put live}';

    protected $description = 'Put a listing live, raising alerts for matching saved searches';

    public function handle(): int
    {
        if ($this->argument('listing') === null) {
            return $this->listDrafts();
        }

        $listing = Listing::query()->with('branch')->find($this->argument('listing'));

        if ($listing === null) {
            $this->components->error("No listing with id {$this->argument('listing')}.");

            return self::FAILURE;
        }

        if ($listing->status === ListingStatus::Live) {
            $this->components->warn("{$listing->reference} is already live, so nothing was raised.");

            return self::SUCCESS;
        }

        // Only report what this publish raised: a re-listed property can already
        // have alerts from the first time it went live.
        $lastAlertId = Alert::query()->max('id') ?? 0;

        $listing->publish();

        $raised = Alert::query()->where('listing_id', $listing->id)->where('id', '>', $lastAlertId);
        $total = $raised->count();
        $emailed = $raised->whereNotNull('sent_at')->count();

        $this->components->info($total === 0
            ? "{$listing->reference} is live. No new alerts: no saved search matches, or every match was already told."
            : "{$listing->reference} is live. {$total} alert(s) raised: {$emailed} emailed now, ".($total - $emailed).' waiting for the daily digest.');

        return self::SUCCESS;
    }

    private function listDrafts(): int
    {
        $drafts = Listing::query()->with('branch')->where('status', ListingStatus::Draft)->orderBy('id')->get();

        $this->table(
            ['Id', 'Reference', 'Price', 'Beds', 'Type', 'Region'],
            $drafts->map(fn (Listing $listing) => [
                $listing->id,
                $listing->reference,
                '£'.number_format($listing->price),
                $listing->bedrooms,
                $listing->property_type->label(),
                $listing->branch->region,
            ]),
        );

        $this->line('Publish one with: php artisan listings:publish {id}');

        return self::SUCCESS;
    }
}
