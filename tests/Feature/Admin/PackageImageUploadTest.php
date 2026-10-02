<?php

namespace Tests\Feature\Admin;

use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PackageImageUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_upload_persists_file_and_api_serves_absolute_url(): void
    {
        Storage::fake('public', ['url' => 'http://localhost/storage']);
        $admin = User::factory()->create(['is_admin' => true]);
        $package = Package::create(['name' => 'Shoot', 'price' => 100]);

        $this->actingAs($admin)->put(
            route('admin.packages.update', $package->id),
            [
                'name' => 'Shoot',
                'price' => 100,
                'images' => [UploadedFile::fake()->create('cover.jpg', 100)],
            ]
        )->assertRedirect(route('admin.packages.index'));

        $stored = $package->fresh()->images;
        $this->assertCount(1, $stored);
        $this->assertStringStartsNotWith('http', $stored[0]);
        Storage::disk('public')->assertExists($stored[0]);

        $this->getJson('/api/packages/'.$package->id)
            ->assertStatus(200)
            ->assertJsonPath('data.images', [Storage::disk('public')->url($stored[0])]);

        $emitted = $this->getJson('/api/packages/'.$package->id)->json('data.images.0');
        $this->assertStringStartsWith('http', $emitted);
        $this->assertStringContainsString('/storage/', $emitted);
    }
}
