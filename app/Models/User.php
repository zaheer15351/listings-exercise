<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\AlertNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property-read Collection<int, SavedSearch> $savedSearches
 * @property-read Collection<int, Alert> $alerts
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasMany<SavedSearch, $this>
     */
    public function savedSearches(): HasMany
    {
        return $this->hasMany(SavedSearch::class);
    }

    /**
     * @return HasMany<Alert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Email the given alerts as one message and mark them sent. The single
     * place an alert is delivered, used by instant searches and the digest.
     *
     * @param  Collection<int, Alert>  $alerts
     */
    public function sendAlerts(Collection $alerts): void
    {
        if ($alerts->isEmpty()) {
            return;
        }

        $alerts->loadMissing(['listing.branch', 'savedSearch']);

        $this->notify(new AlertNotification($alerts));

        Alert::query()->whereKey($alerts->modelKeys())->update(['sent_at' => now()]);
    }
}
