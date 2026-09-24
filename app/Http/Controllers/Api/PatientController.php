<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatientRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class PatientController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = $request->integer('per_page', 25);
        $page = max($request->integer('page', 1), 1);

        if ($perPage < 1 || $perPage > 100) {
            abort(422, 'The per_page parameter must be between 1 and 100.');
        }

        $patients = Patient::query()
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return PatientResource::collection($patients);
    }

    public function store(StorePatientRequest $request): PatientResource
    {
        $patient = Patient::create($request->validated());

        return new PatientResource($patient);
    }

    public function show(Patient $patient): PatientResource
    {
        return new PatientResource($patient);
    }

    public function update(UpdatePatientRequest $request, Patient $patient): PatientResource
    {
        $patient->update($request->validated());

        return new PatientResource($patient->fresh());
    }

    public function destroy(Patient $patient): Response
    {
        $patient->delete();

        return response()->noContent();
    }
}
