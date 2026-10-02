<?php

namespace Tests\Feature\Api;

use App\Models\Package;
use App\Models\Scent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PackageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_packages(): void
    {
        $package = Package::create([
            'name' => 'Romantic Getaway',
            'price' => 199.99,
            'rating' => 4.5,
            'reviews_count' => 120,
            'gallery_images' => ['image1.jpg', 'image2.jpg'],
        ]);

        $response = $this->getJson('/api/packages');

        $response->assertStatus(200)
            ->assertValidRequest()
            ->assertValidResponse(200)
            ->assertJsonFragment([
                'id' => $package->id,
                'rating' => 4.5,
            ]);
    }

    public function test_can_show_package_details(): void
    {
        $package = Package::create([
            'name' => 'Adventure Trip',
            'description' => 'A thrilling experience.',
            'price' => 299.99,
            'rating' => 4.8,
            'reviews_count' => 50,
            'gallery_images' => ['adv1.jpg'],
            'inclusions' => ['Food', 'Guide'],
        ]);

        $scent = Scent::create([
            'name' => 'Lavender',
            'description' => 'A relaxing scent.',
        ]);

        $package->scents()->attach($scent->id);

        $response = $this->getJson('/api/packages/'.$package->id);

        $response->assertStatus(200)
            ->assertValidRequest()
            ->assertValidResponse(200)
            ->assertJsonFragment([
                'id' => $package->id,
                'name' => 'Adventure Trip',
            ])
            ->assertJsonFragment([
                'name' => 'Lavender',
            ]);
    }

    public function test_returns_404_for_missing_package(): void
    {
        $response = $this->getJson('/api/packages/999');

        $response->assertStatus(404)
            ->assertValidRequest()
            ->assertValidResponse(404);
    }

    public function test_images_served_as_absolute_urls(): void
    {
        $package = Package::create([
            'name' => 'Photo Shoot',
            'price' => 99.99,
            'images' => ['packages/cover.jpg'],
            'gallery_images' => ['packages/g1.jpg', 'https://cdn.example.com/g2.jpg'],
        ]);

        $cover = url("/api/packages/{$package->id}/images/images/0");
        $g1 = url("/api/packages/{$package->id}/images/gallery_images/0");

        $response = $this->getJson('/api/packages/'.$package->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.images', [$cover])
            ->assertJsonPath('data.gallery_images', [$g1, 'https://cdn.example.com/g2.jpg']);

        foreach ([$cover, $g1] as $endpoint) {
            $this->assertStringStartsWith('http', $endpoint);
        }

        $index = $this->getJson('/api/packages');

        $index->assertStatus(200)
            ->assertJsonFragment([$cover]);
    }

    public function test_image_endpoint_streams_bytes_with_cors_headers(): void
    {
        Storage::fake('public', ['url' => 'http://localhost/storage']);
        Storage::disk('public')->put('packages/cover.jpg', 'bytes');
        $package = Package::create([
            'name' => 'Stream',
            'price' => 10,
            'images' => ['packages/cover.jpg'],
        ]);

        $this->get("/api/packages/{$package->id}/images/images/0")
            ->assertStatus(200)
            ->assertHeader('Access-Control-Allow-Origin', '*');

        $this->get("/api/packages/{$package->id}/images/gallery_images/0")->assertStatus(404);
        $this->get("/api/packages/{$package->id}/images/images/9")->assertStatus(404);
        $this->get("/api/packages/{$package->id}/images/nope/0")->assertStatus(404);

        $external = Package::create([
            'name' => 'CDN',
            'price' => 10,
            'gallery_images' => ['https://cdn.example.com/g2.jpg'],
            'images' => ['packages/gone.jpg'],
        ]);

        $this->get("/api/packages/{$external->id}/images/gallery_images/0")->assertStatus(404);
        $this->get("/api/packages/{$external->id}/images/images/0")->assertStatus(404);
    }
}
