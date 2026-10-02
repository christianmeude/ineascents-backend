<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    /**
     * Image URLs point at the CORS-enabled image endpoint, not raw
     * storage paths. Static /storage files bypass Laravel under
     * `php artisan serve`, so Flutter web XHR fetches need ACAO
     * headers only a Laravel response carries. Already-absolute
     * values (external CDN) pass through untouched.
     */
    private function imageUrls(string $collection, mixed $value): array
    {
        $urls = [];
        foreach (\App\Support\PackageSanitizer::strings($value) as $i => $path) {
            if (preg_match('#^https?://#i', $path)) {
                $urls[] = $path;
            } else {
                $urls[] = url("/api/packages/{$this->id}/images/{$collection}/{$i}");
            }
        }

        return $urls;
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
            'images' => $this->imageUrls('images', $this->images),
            'gallery_images' => $this->imageUrls('gallery_images', $this->gallery_images),
            'scents' => ScentResource::collection($this->whenLoaded('scents')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

}
