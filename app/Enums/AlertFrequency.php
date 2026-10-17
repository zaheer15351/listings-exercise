<?php

namespace App\Enums;

/**
 * How often a saved search emails its matches. Alerts are always generated the
 * moment a listing goes live; this only decides when they are delivered.
 */
enum AlertFrequency: string
{
    case Instant = 'instant';
    case Daily = 'daily';

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $frequency) => ['value' => $frequency->value, 'label' => $frequency->label()],
            self::cases(),
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Instant => 'As soon as it is listed',
            self::Daily => 'Daily digest',
        };
    }
}
