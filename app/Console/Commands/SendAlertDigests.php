<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\Listing;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Emails every buyer one message with all their unsent alerts.
 *
 * Scheduled daily in routes/console.php. Nothing runs the scheduler in this
 * exercise, so run it by hand to see the digest in the log.
 */
class SendAlertDigests extends Command
{
    protected $signature = 'alerts:send-digests';

    protected $description = 'Email each buyer one digest of their unsent alerts';

    public function handle(): int
    {
        $digests = Alert::query()
            ->unsent()
            // A listing that left the market before the digest is not news.
            ->whereIn('listing_id', Listing::query()->live()->select('id'))
            ->with(['user', 'listing.branch', 'savedSearch'])
            ->orderBy('id')
            ->get()
            ->groupBy('user_id');

        $digests->each(function (Collection $alerts): void {
            /** @var Collection<int, Alert> $alerts */
            $alerts->first()->user->sendAlerts($alerts);
        });

        $this->components->info("Sent {$digests->count()} digest email(s).");

        return self::SUCCESS;
    }
}
