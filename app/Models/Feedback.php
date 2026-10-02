<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OAT;

#[OAT\Schema(
    schema: 'Feedback',
    title: 'Feedback',
    properties: [
        new OAT\Property(property: 'id', type: 'integer'),
        new OAT\Property(property: 'user_id', type: 'integer'),
        new OAT\Property(property: 'booking_id', type: 'integer', nullable: true),
        new OAT\Property(property: 'stars', type: 'integer', minimum: 1, maximum: 5),
        new OAT\Property(property: 'text', type: 'string', nullable: true),
        new OAT\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OAT\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ]
)]
class Feedback extends Model
{
    protected $table = 'feedbacks';

    protected $fillable = [
        'user_id',
        'booking_id',
        'stars',
        'text',
    ];

    protected $casts = [
        'stars' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
