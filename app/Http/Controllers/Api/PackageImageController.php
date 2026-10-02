<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OAT;

class PackageImageController extends Controller
{
    private const COLLECTIONS = ['images', 'gallery_images'];

    #[OAT\Get(
        path: '/api/packages/{package}/images/{collection}/{index}',
        summary: 'Get package image bytes',
        description: 'Streams a package image with CORS headers. Static /storage files bypass Laravel under php artisan serve, so Flutter web (XHR image fetches) needs this endpoint.',
        tags: ['Packages']
    )]
    #[OAT\Parameter(name: 'package', description: 'ID of package', in: 'path', required: true, schema: new OAT\Schema(type: 'integer'))]
    #[OAT\Parameter(name: 'collection', description: 'Image collection', in: 'path', required: true, schema: new OAT\Schema(type: 'string', enum: ['images', 'gallery_images']))]
    #[OAT\Parameter(name: 'index', description: 'Position in collection', in: 'path', required: true, schema: new OAT\Schema(type: 'integer'))]
    #[OAT\Response(response: 200, description: 'Image bytes')]
    #[OAT\Response(response: 404, description: 'No image at slot')]
    public function show(Package $package, string $collection, int $index)
    {
        if (! in_array($collection, self::COLLECTIONS, true)) {
            abort(404);
        }

        $paths = \App\Support\PackageSanitizer::strings($package->{$collection});
        $path = $paths[$index] ?? null;

        if (! is_string($path) || preg_match('#^https?://#i', $path)) {
            abort(404);
        }

        if (! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($path), [
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
