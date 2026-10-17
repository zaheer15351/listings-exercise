<?php

namespace App\Models;

use App\Enums\ListingStatus;
use App\Enums\PropertyType;
use App\Events\ListingWentLive;
use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The casts() method tells Eloquent how to hydrate these attributes, but static
 * analysis can't infer that from a string map — these annotations do.
 *
 * @property int $id
 * @property int $branch_id
 * @property string $reference
 * @property string $address_line_1
 * @property string $city
 * @property string $postcode
 * @property int $price
 * @property int $bedrooms
 * @property int $bathrooms
 * @property PropertyType $property_type
 * @property ListingStatus $status
 * @property Carbon|null $listed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Branch $branch
 */
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'reference',
        'address_line_1',
        'city',
        'postcode',
        'price',
        'bedrooms',
        'bathrooms',
        'property_type',
        'status',
        'listed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'bedrooms' => 'integer',
            'bathrooms' => 'integer',
            'property_type' => PropertyType::class,
            'status' => ListingStatus::class,
            'listed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Put the listing on the market and announce it.
     *
     * The one place a listing becomes live, so the one place that raises
     * alerts. A listing created or bulk-updated straight into live (seeders,
     * imports, raw queries) announces nothing, and publishing a listing that is
     * already live does nothing. A re-listed property keeps its original
     * listing date; a draft gets today's.
     */
    public function publish(): void
    {
        if ($this->status === ListingStatus::Live) {
            return;
        }

        $this->status = ListingStatus::Live;
        $this->listed_at ??= now();
        $this->save();

        ListingWentLive::dispatch($this);
    }

    /**
     * Only listings that are currently on the market.
     *
     * @param  Builder<Listing>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->where('status', ListingStatus::Live);
    }
}
