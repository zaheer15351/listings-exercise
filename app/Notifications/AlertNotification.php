<?php

namespace App\Notifications;

use App\Models\Alert;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The alert email. One listing for an instant search, several for a digest;
 * either way a single email. With MAIL_MAILER=log it is written to the log.
 */
class AlertNotification extends Notification
{
    /**
     * @param  Collection<int, Alert>  $alerts  with listing.branch and savedSearch loaded
     */
    public function __construct(public readonly Collection $alerts) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->alerts->count() === 1
            ? $this->single($this->alerts->sole())
            : $this->digest();
    }

    private function single(Alert $alert): MailMessage
    {
        return (new MailMessage)
            ->subject("New property for {$alert->savedSearch->summary()}")
            ->greeting('A new listing matches your saved search')
            ->line($this->describe($alert->listing))
            ->action('View listing', route('listings.show', $alert->listing))
            ->line($this->why());
    }

    private function digest(): MailMessage
    {
        $message = (new MailMessage)
            ->subject("{$this->alerts->count()} new properties match your saved searches")
            ->greeting('New listings since your last email');

        foreach ($this->alerts->groupBy('saved_search_id') as $alerts) {
            $message->line("**{$alerts->first()->savedSearch->summary()}**");

            foreach ($alerts as $alert) {
                $message->line($this->describe($alert->listing).' · '.route('listings.show', $alert->listing));
            }
        }

        return $message
            ->action('Manage your saved searches', route('saved-searches.index'))
            ->line($this->why());
    }

    private function describe(Listing $listing): string
    {
        return sprintf(
            '£%s · %d bed %s · %s, %s',
            number_format($listing->price),
            $listing->bedrooms,
            strtolower($listing->property_type->label()),
            $listing->address_line_1,
            $listing->branch->region,
        );
    }

    private function why(): string
    {
        return 'You are getting this because of your saved searches. Delete a search to stop its emails.';
    }
}
