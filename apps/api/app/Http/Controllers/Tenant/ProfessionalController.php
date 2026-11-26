<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ClinicRoom;
use App\Models\Tenant\Professional;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class ProfessionalController extends Controller
{
    public function index(): JsonResponse
    {
        $professionals = Professional::query()
            ->with(['availabilities' => fn ($query) => $query->where('is_active', true)->orderBy('day_of_week')->orderBy('start_time')])
            ->orderBy('name')
            ->get();

        return new JsonResponse([
            'data' => $professionals,
        ], Response::HTTP_OK);
    }

    public function rooms(): JsonResponse
    {
        $rooms = ClinicRoom::query()
            ->orderBy('name')
            ->get();

        return new JsonResponse([
            'data' => $rooms,
        ], Response::HTTP_OK);
    }
}
