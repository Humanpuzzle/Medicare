<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAvailabilityRequest;
use App\Http\Requests\UpdateAvailabilityRequest;
use App\Http\Resources\AvailabilityResource;
use App\Models\Availability;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class AvailabilityController extends Controller
{
    public function __construct(
        private readonly AvailabilityService $availabilityService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->get('per_page', 25);
        $page = max((int) $request->get('page', 1), 1);

        if ($perPage < 1 || $perPage > 100) {
            abort(422, 'The per_page parameter must be between 1 and 100.');
        }

        $query = Availability::query()->with('doctor')->orderBy('starts_at');

        if ($request->has('doctor_id')) {
            $query->where('doctor_id', $request->integer('doctor_id'));
        }

        $availabilities = $query->paginate($perPage, ['*'], 'page', $page);

        return AvailabilityResource::collection($availabilities);
    }

    public function store(StoreAvailabilityRequest $request): AvailabilityResource
    {
        $data = $request->validated();
        $data['starts_at'] = CarbonImmutable::parse($data['starts_at']);
        $data['ends_at'] = CarbonImmutable::parse($data['ends_at']);

        $availability = $this->availabilityService->create($data);

        return new AvailabilityResource($availability);
    }

    public function show(Availability $availability): AvailabilityResource
    {
        return new AvailabilityResource($availability->load('doctor'));
    }

    public function update(UpdateAvailabilityRequest $request, Availability $availability): AvailabilityResource
    {
        $data = $request->validated();

        if (isset($data['starts_at'])) {
            $data['starts_at'] = $data['starts_at'] instanceof CarbonImmutable
                ? $data['starts_at']
                : CarbonImmutable::parse($data['starts_at']);
        }
        if (isset($data['ends_at'])) {
            $data['ends_at'] = $data['ends_at'] instanceof CarbonImmutable
                ? $data['ends_at']
                : CarbonImmutable::parse($data['ends_at']);
        }

        $availability = $this->availabilityService->update($availability, $data);

        return new AvailabilityResource($availability);
    }

    public function destroy(Availability $availability): Response
    {
        $this->availabilityService->delete($availability);

        return response()->noContent();
    }
}
