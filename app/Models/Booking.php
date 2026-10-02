<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OAT;

#[OAT\Schema(
    schema: 'Booking',
    title: 'Booking',
    properties: [
        new OAT\Property(property: 'id', type: 'integer'),
        new OAT\Property(property: 'booking_reference', type: 'string'),
        new OAT\Property(property: 'user_id', type: 'integer', nullable: true),
        new OAT\Property(property: 'customer_name', type: 'string'),
        new OAT\Property(property: 'customer_email', type: 'string'),
        new OAT\Property(property: 'customer_phone', type: 'string', nullable: true),
        new OAT\Property(property: 'pax', type: 'integer'),
        new OAT\Property(property: 'event_date', type: 'string', format: 'date-time'),
        new OAT\Property(property: 'event_time', type: 'string', nullable: true),
        new OAT\Property(property: 'venue_address', type: 'string'),
        new OAT\Property(property: 'payment_method', type: 'string'),
        new OAT\Property(property: 'status', type: 'string'),
        new OAT\Property(property: 'checkout_url', type: 'string', nullable: true),
        new OAT\Property(property: 'inquiry_id', type: 'integer', nullable: true),
        new OAT\Property(property: 'consent_privacy_version', type: 'string', nullable: true),
        new OAT\Property(property: 'consented_at', type: 'string', format: 'date-time', nullable: true),
        new OAT\Property(property: 'package', ref: '#/components/schemas/Package'),
        new OAT\Property(property: 'scents', type: 'array', items: new OAT\Items(ref: '#/components/schemas/Scent')),
    ]
)]

class Booking extends Model
{
    /**
     * Minutes an unpaid/offline-unconfirmed booking may squat its date.
     * Single source of truth for the hold window (route + lazy sweeps).
     */
    public const PENDING_HOLD_MINUTES = 15;

    /**
     * Cancel stale Pending bookings. Called sweep-first by every
     * blocked-date read (availability, booking guard, bookings list) so the
     * calendar can never display a squatted date — no cron required.
     */
    public static function expireStalePending(): int
    {
        return static::where('status', BookingStatus::Pending->value)
            ->where('created_at', '<', now()->subMinutes(self::PENDING_HOLD_MINUTES))
            ->update(['status' => BookingStatus::Cancelled->value]);
    }

    protected $fillable = [
        'booking_reference',
        'user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'package_id',
        'pax',
        'event_date',
        'event_time',
        'venue_address',
        'status',
        'total_price',
        'notes',
        'payment_method',
        'checkout_url',
        'inquiry_id',
        'consent_privacy_version',
        'consented_at',
    ];

    protected $casts = [
        'event_date' => 'date',
        'consented_at' => 'datetime',
        'total_price' => 'decimal:2',
        'status' => BookingStatus::class,
        'payment_method' => PaymentMethod::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            if (empty($booking->booking_reference)) {
                $booking->booking_reference = 'BOOKING-'.strtoupper(uniqid());
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function scents()
    {
        return $this->belongsToMany(Scent::class);
    }

    public function inquiry()
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function feedbacks()
    {
        return $this->hasMany(Feedback::class);
    }
}
