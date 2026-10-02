<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use OpenApi\Attributes as OAT;

#[OAT\Schema(
    schema: 'Scent',
    title: 'Scent',
    description: 'Scent model',
    properties: [
        new OAT\Property(property: 'id', type: 'integer', example: 1),
        new OAT\Property(property: 'name', type: 'string', example: 'Lavender'),
        new OAT\Property(property: 'category', type: 'string', example: 'women', nullable: true),
        new OAT\Property(property: 'description', type: 'string', example: 'A calming floral scent.', nullable: true),
        new OAT\Property(property: 'image_url', type: 'string', example: 'https://example.com/lavender.jpg', nullable: true),
        new OAT\Property(property: 'is_available', type: 'boolean', example: true),
        new OAT\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OAT\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ]
)]
class Scent extends Model
{
    protected $fillable = [
        'name',
        'category',
        'description',
        'image_url',
        'is_available',
    ];

    protected $casts = [
        'is_available' => 'boolean',
    ];

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class);
    }
}
