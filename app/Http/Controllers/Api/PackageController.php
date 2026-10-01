<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PackageResource;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OAT;

#[OAT\Tag(
    name: 'Packages',
    description: 'API Endpoints for Packages'
)]
class PackageController extends Controller
{
    #[OAT\Get(
        path: '/api/packages',
        summary: 'Get list of packages',
        description: 'Returns list of available packages.',
        tags: ['Packages']
    )]
    #[OAT\Response(
        response: 200,
        description: 'Successful operation',
        content: new OAT\JsonContent(
            properties: [
                new OAT\Property(
                    property: 'data',
                    type: 'array',
                    items: new OAT\Items(ref: '#/components/schemas/Package')
                )
            ],
            type: 'object'
        )
    )]
    public function index(Request $request)
    {
        $json = Cache::remember('catalog:packages:index', 300, function () use ($request) {
            $packages = Package::with('scents')->get();

            return PackageResource::collection($packages)->toResponse($request)->getContent();
        });

        $etag = md5($json);

        if (trim((string) $request->header('If-None-Match'), '"') === $etag) {
            return response(null, 304)->header('ETag', $etag);
        }

        return response($json, 200, ['Content-Type' => 'application/json'])->header('ETag', $etag);
    }

    #[OAT\Get(
        path: '/api/packages/{package}',
        summary: 'Get package details',
        description: 'Returns full package details for a specific ID.',
        tags: ['Packages']
    )]
    #[OAT\Parameter(
        name: 'package',
        description: 'ID of package to return',
        in: 'path',
        required: true,
        schema: new OAT\Schema(type: 'integer')
    )]
    #[OAT\Response(
        response: 200,
        description: 'Successful operation',
        content: new OAT\JsonContent(
            properties: [
                new OAT\Property(property: 'data', ref: '#/components/schemas/Package')
            ],
            type: 'object'
        )
    )]
    #[OAT\Response(
        response: 404, 
        description: 'Package not found',
        content: new OAT\JsonContent(
            properties: [
                new OAT\Property(property: 'message', type: 'string')
            ],
            type: 'object'
        )
    )]
    public function show(Request $request, Package $package)
    {
        $key = "catalog:packages:{$package->id}";
        $json = Cache::remember($key, 300, function () use ($request, $package) {
            $package->load('scents');

            return (new PackageResource($package))->toResponse($request)->getContent();
        });

        $etag = md5($json);

        if (trim((string) $request->header('If-None-Match'), '"') === $etag) {
            return response(null, 304)->header('ETag', $etag);
        }

        return response($json, 200, ['Content-Type' => 'application/json'])->header('ETag', $etag);
    }
}
