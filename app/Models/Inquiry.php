<?php

namespace App\Models;

use App\Enums\InquiryStatus;
use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OAT;

#[OAT\Schema(
    schema: 'Inquiry',
    title: 'Inquiry',
    properties: [
        new OAT\Property(property: 'id', type: 'integer'),
        new OAT\Property(property: 'name', type: 'string'),
        new OAT\Property(property: 'email', type: 'string'),
        new OAT\Property(property: 'phone', type: 'string'),
        new OAT\Property(property: 'event_date', type: 'string', format: 'date', nullable: true),
        new OAT\Property(property: 'message', type: 'string', nullable: true),
        new OAT\Property(property: 'status', type: 'string'),
        new OAT\Property(property: 'archived', type: 'boolean'),
        new OAT\Property(property: 'consent_privacy_version', type: 'string', nullable: true),
        new OAT\Property(property: 'consented_at', type: 'string', format: 'date-time', nullable: true),
    ]
)]
class Inquiry extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'event_date',
        'message',
        'consent_privacy_version',
        'consented_at',
        'status',
        'archived',
    ];

    protected $attributes = [
        'status' => 'new',
        'archived' => false,
    ];

    protected $casts = [
        'event_date' => 'date',
        'consented_at' => 'datetime',
        'status' => InquiryStatus::class,
        'archived' => 'boolean',
    ];

    public function booking()
    {
        return $this->hasOne(Booking::class);
    }
}
