<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AvailableSlotResource;
use App\Models\Doctor;
use App\Services\SlotService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

final class DoctorAvailableSlotController extends Controller
{
    public function __construct(
        private readonly SlotService $slotService
    ) {}

    public function index(Request $request, Doctor $doctor): AnonymousResourceCollection
    {
        $perPage = min((int) $request->get('per_page', 25), 100);
        $page = max((int) $request->get('page', 1), 1);

        $from = $request->has('from')
            ? CarbonImmutable::parse((string) $request->string('from'))
            : CarbonImmutable::now('UTC')->startOfDay();

        $to = $request->has('to')
            ? CarbonImmutable::parse((string) $request->string('to'))
            : $from->copy()->addDays(30);

        $slots = $this->slotService->getAvailableSlots([
            'doctor_id' => $doctor->id,
            'from' => $from,
            'to' => $to,
        ]);

        $slotResources = array_map(function ($slot) use ($doctor) {
            return new AvailableSlotResource([
                'doctor_id' => $doctor->id,
                'availability_id' => null,
                'starts_at' => $slot->startsAt,
                'ends_at' => $slot->endsAt,
                'is_available' => $slot->isAvailable,
            ]);
        }, $slots);

        $paginator = new LengthAwarePaginator(
            array_slice($slotResources, ($page - 1) * $perPage, $perPage),
            count($slotResources),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return AvailableSlotResource::collection($paginator);
    }
}
