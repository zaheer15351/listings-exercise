<?php

namespace App\Actions;

use App\Models\Alert;
use App\Models\User;
use App\Notifications\AlertNotification;
use Illuminate\Database\Eloquent\Collection;

/**
 * Emails a buyer some of their alerts as one message and marks them sent.
 *
 * Instant searches and the daily digest both deliver through here, so the two
 * paths can't drift. Delivery isn't something a user or an alert is, so it
 * lives in its own class, in the single-purpose style of Laravel's Fortify
 * actions.
 */
class SendAlerts
{
    /**
     * @param  Collection<int, Alert>  $alerts  all belonging to $user
     */
    public function handle(User $user, Collection $alerts): void
    {
        if ($alerts->isEmpty()) {
            return;
        }

        $alerts->loadMissing(['listing.branch', 'savedSearch']);

        $user->notify(new AlertNotification($alerts));

        Alert::query()->whereKey($alerts->modelKeys())->update(['sent_at' => now()]);
    }
}
