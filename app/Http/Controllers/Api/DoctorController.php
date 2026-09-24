<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDoctorRequest;
use App\Http\Requests\UpdateDoctorRequest;
use App\Http\Resources\DoctorResource;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class DoctorController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = $request->integer('per_page', 25);
        $page = max($request->integer('page', 1), 1);

        if ($perPage < 1 || $perPage > 100) {
            abort(422, 'The per_page parameter must be between 1 and 100.');
        }

        $doctors = Doctor::query()
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return DoctorResource::collection($doctors);
    }

    public function store(StoreDoctorRequest $request): DoctorResource
    {
        $doctor = Doctor::create($request->validated());

        return new DoctorResource($doctor);
    }

    public function show(Doctor $doctor): DoctorResource
    {
        return new DoctorResource($doctor);
    }

    public function update(UpdateDoctorRequest $request, Doctor $doctor): DoctorResource
    {
        $doctor->update($request->validated());

        return new DoctorResource($doctor->fresh());
    }

    public function destroy(Doctor $doctor): Response
    {
        $doctor->delete();

        return response()->noContent();
    }
}
