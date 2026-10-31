<?php

namespace App\Frame;

use App\Data\ServiceRequestData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use ReflectionClass;
use Schemastud\DataSchemas\Generators\Generator;
use Schemastud\Frame\Contracts\ResourceSchemaResolver;
use Schemastud\Frame\Registry\ResourceDefinition;

/** Supplies the public read projection while leaving the resource's declared write schema intact. */
final class ServiceRequestReadSchemaResolver implements ResourceSchemaResolver
{
    public function __construct(private ?ResourceSchemaResolver $next = null) {}

    /** @return array<string, mixed>|null */
    public function schema(ResourceDefinition $resource, Request $request): ?array
    {
        if ($resource->key !== 'service-requests') {
            return $this->next?->schema($resource, $request);
        }

        if ($request->query->has('purpose')) {
            Validator::make($request->query(), ['purpose' => ['required', 'in:read,write']])->validate();
        }

        if ($request->query('purpose', 'write') === 'read') {
            return app(Generator::class)->forResponse()->generate(new ReflectionClass(ServiceRequestData::class));
        }

        return $this->next?->schema($resource, $request);
    }
}
