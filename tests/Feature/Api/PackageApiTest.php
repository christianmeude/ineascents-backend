<?php

namespace Tests\Feature\Api;

use App\Models\Package;
use App\Models\Scent;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $response = $this->getJson('/api/packages/'.$package->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.images', [
                \Illuminate\Support\Facades\Storage::disk('public')->url('packages/cover.jpg'),
            ])
            ->assertJsonPath('data.gallery_images', [
                \Illuminate\Support\Facades\Storage::disk('public')->url('packages/g1.jpg'),
                'https://cdn.example.com/g2.jpg',
            ]);
    }
}
