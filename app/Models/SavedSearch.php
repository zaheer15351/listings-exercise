<?php

namespace App\Models;

use App\Enums\AlertFrequency;
use App\Enums\PropertyType;
use Database\Factories\SavedSearchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A buyer's search, kept so they can be alerted when a new listing matches it.
 *
 * @property int $id
 * @property int $user_id
 * @property PropertyType|null $property_type
 * @property string|null $region
 * @property int|null $min_bedrooms
 * @property int|null $min_price
 * @property int|null $max_price
 * @property AlertFrequency $frequency
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 */
class SavedSearch extends Model
{
    /** @use HasFactory<SavedSearchFactory> */
    use HasFactory;

    /**
     * The criteria, in the order they read in a summary. These are also the
     * listings page's filter parameters.
     */
    public const array CRITERIA = ['property_type', 'region', 'min_bedrooms', 'min_price', 'max_price'];

    protected $fillable = [
        'property_type',
        'region',
        'min_bedrooms',
        'min_price',
        'max_price',
        'frequency',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'property_type' => PropertyType::class,
            'min_bedrooms' => 'integer',
            'min_price' => 'integer',
            'max_price' => 'integer',
            'frequency' => AlertFrequency::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Saved searches the given listing satisfies: every criterion that is set
     * must hold, and an unset criterion matches anything. The comparisons are
     * the listings filter's own, so an alert means "this would now show up in
     * the search you saved".
     *
     * @param  Builder<SavedSearch>  $query
     */
    public function scopeMatching(Builder $query, Listing $listing): void
    {
        $query
            ->where(fn (Builder $q) => $q->whereNull('property_type')->orWhere('property_type', $listing->property_type))
            ->where(fn (Builder $q) => $q->whereNull('region')->orWhere('region', $listing->branch->region))
            ->where(fn (Builder $q) => $q->whereNull('min_bedrooms')->orWhere('min_bedrooms', '<=', $listing->bedrooms))
            ->where(fn (Builder $q) => $q->whereNull('min_price')->orWhere('min_price', '<=', $listing->price))
            ->where(fn (Builder $q) => $q->whereNull('max_price')->orWhere('max_price', '>=', $listing->price));
    }

    /**
     * The set criteria as listings filter parameters, so a saved search can
     * link back to its current results.
     *
     * @return array<string, int|string>
     */
    public function filters(): array
    {
        return array_filter([
            'property_type' => $this->property_type?->value,
            'region' => $this->region,
            'min_bedrooms' => $this->min_bedrooms,
            'min_price' => $this->min_price,
            'max_price' => $this->max_price,
        ], fn ($value) => $value !== null);
    }

    /**
     * A human summary, e.g. "Flat in Leeds, 2+ beds, £200,000 to £300,000".
     */
    public function summary(): string
    {
        $parts = [($this->property_type?->label() ?? 'Any property').($this->region ? " in {$this->region}" : '')];

        if ($this->min_bedrooms !== null) {
            $parts[] = "{$this->min_bedrooms}+ beds";
        }

        $parts[] = match (true) {
            $this->min_price !== null && $this->max_price !== null => self::money($this->min_price).' to '.self::money($this->max_price),
            $this->min_price !== null => 'from '.self::money($this->min_price),
            $this->max_price !== null => 'up to '.self::money($this->max_price),
            default => null,
        };

        return implode(', ', array_filter($parts));
    }

    private static function money(int $pounds): string
    {
        return '£'.number_format($pounds);
    }
}
