<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelAppointmentRequest;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentStatusRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Services\AppointmentService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = $request->integer('per_page', 25);
        $page = max($request->integer('page', 1), 1);

        if ($perPage < 1 || $perPage > 100) {
            abort(422, 'The per_page parameter must be between 1 and 100.');
        }

        $query = Appointment::query()
            ->with(['doctor', 'patient'])
            ->orderBy('start_time')
            ->orderBy('id');

        if ($request->has('doctor_id')) {
            $query->where('doctor_id', $request->integer('doctor_id'));
        }

        if ($request->has('patient_id')) {
            $query->where('patient_id', $request->integer('patient_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->string('status'));
        }

        $appointments = $query->paginate($perPage, ['*'], 'page', $page);

        return AppointmentResource::collection($appointments);
    }

    public function store(StoreAppointmentRequest $request): AppointmentResource
    {
        $data = $request->validated();
        $data['start_time'] = CarbonImmutable::parse($data['start_time']);
        $data['end_time'] = CarbonImmutable::parse($data['end_time']);

        $appointment = $this->appointmentService->create($data);

        return new AppointmentResource($appointment->load(['doctor', 'patient']));
    }

    public function show(Appointment $appointment): AppointmentResource
    {
        return new AppointmentResource($appointment->load(['doctor', 'patient']));
    }

    public function updateStatus(UpdateAppointmentStatusRequest $request, Appointment $appointment): AppointmentResource
    {
        $data = $request->validated();
        $status = AppointmentStatus::from($data['status']);
        $cancellationReason = $data['cancellation_reason'] ?? null;

        $appointment = $this->appointmentService->updateStatus($appointment, $status, $cancellationReason);

        return new AppointmentResource($appointment->load(['doctor', 'patient']));
    }

    public function cancel(CancelAppointmentRequest $request, Appointment $appointment): AppointmentResource
    {
        $data = $request->validated();
        $cancellationReason = $data['cancellation_reason'] ?? null;

        $appointment = $this->appointmentService->cancel($appointment, $cancellationReason);

        return new AppointmentResource($appointment->load(['doctor', 'patient']));
    }
}
