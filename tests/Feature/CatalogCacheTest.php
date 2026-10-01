<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CatalogCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_caches_and_supports_etag_and_admin_edit_busts(): void
    {
        $package = Package::create([
            'name' => 'Essential',
            'description' => 'Test',
            'price' => 1000,
            'pax_options' => [50],
            'pax_prices' => [50 => 4499],
        ]);

        $first = $this->getJson('/api/packages');
        $first->assertStatus(200);
        $first->assertJsonStructure(['data']);
        $etag = $first->headers->get('ETag');
        $this->assertNotEmpty($etag);
        $this->assertTrue(Cache::has('catalog:packages:index'));

        $second = $this->getJson('/api/packages');
        $second->assertStatus(200);
        $this->assertSame($etag, $second->headers->get('ETag'));
        $this->assertSame($first->getContent(), $second->getContent());

        $this->getJson('/api/packages', ['If-None-Match' => $etag])->assertStatus(304);

        $show = $this->getJson('/api/packages/'.$package->id);
        $show->assertStatus(200);
        $show->assertJsonStructure(['data']);
        $showEtag = $show->headers->get('ETag');
        $this->assertNotEmpty($showEtag);
        $this->getJson('/api/packages/'.$package->id, ['If-None-Match' => $showEtag])->assertStatus(304);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->put(route('admin.packages.update', $package->id), [
            'name' => 'Essential Updated',
            'description' => 'Test',
            'price' => 1000,
            'inclusions' => [],
            'pax_options' => [],
            'freebies' => [],
            'pax_prices' => ['50' => 4499],
        ])->assertRedirect(route('admin.packages.index'));

        $this->assertFalse(Cache::has('catalog:packages:index'));
        $this->assertFalse(Cache::has("catalog:packages:{$package->id}"));

        $third = $this->getJson('/api/packages');
        $third->assertStatus(200);
        $this->assertNotSame($etag, $third->headers->get('ETag'));
        $third->assertJsonFragment(['name' => 'Essential Updated']);
    }
}
