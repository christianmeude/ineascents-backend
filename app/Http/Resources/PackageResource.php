<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PackageResource extends JsonResource
{
    /**
     * Stored image paths are relative to the `public` disk
     * (e.g. `packages/xxx.jpg`). Clients need absolute URLs.
     */
    private static function imageUrls(mixed $value): array
    {
        return array_map(
            fn (string $path) => preg_match('#^https?://#i', $path)
                ? $path
                : Storage::disk('public')->url($path),
            \App\Support\PackageSanitizer::strings($value)
        );
    }
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'inclusions' => \App\Support\PackageSanitizer::strings($this->inclusions),
            'pax_options' => \App\Support\PackageSanitizer::paxOptions($this->pax_options),
            'pax_prices' => (object) \App\Support\PackageSanitizer::paxPrices($this->pax_prices),
            'freebies' => \App\Support\PackageSanitizer::strings($this->freebies),
            'price' => (float) $this->price,
            'rating' => (float) $this->rating,
            'reviews_count' => (int) $this->reviews_count,
            'images' => self::imageUrls($this->images),
            'gallery_images' => self::imageUrls($this->gallery_images),
            'scents' => ScentResource::collection($this->whenLoaded('scents')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

}
