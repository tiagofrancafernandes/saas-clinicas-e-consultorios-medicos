<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\File;
use App\Models\Tenant\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class FileController extends Controller
{
    public function index(string $patientId): JsonResponse
    {
        $patient = Patient::query()->find($patientId);

        if (! $patient) {
            return response()->json([
                'message' => 'Patient not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $files = File::query()
            ->where('fileable_type', Patient::class)
            ->where('fileable_id', $patient->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $files,
            'meta' => [
                'patient_id' => $patient->id,
                'total_files' => $files->count(),
            ],
        ]);
    }

    public function store(Request $request, string $patientId): JsonResponse
    {
        $patient = Patient::query()->find($patientId);

        if (! $patient) {
            return response()->json([
                'message' => 'Patient not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'file' => ['nullable', 'file', 'max:20480'], // max 20MB
            'original_filename' => ['nullable', 'string', 'max:255'],
            'file_path' => ['nullable', 'string', 'max:500'],
            'mime_type' => ['nullable', 'string', 'max:100'],
            'size_bytes' => ['nullable', 'integer', 'min:1'],
            'collection' => ['nullable', 'string', 'max:50'],
            'visibility' => ['nullable', 'string', 'in:PUBLIC_CLINIC,SHARED_TEAM,PRIVATE'],
        ]);

        if ($request->hasFile('file')) {
            $uploadedFile = $request->file('file');
            $path = $uploadedFile->store("patients/{$patient->id}", 'local');

            $fileModel = File::create([
                'id' => (string) Str::uuid(),
                'fileable_type' => Patient::class,
                'fileable_id' => $patient->id,
                'collection' => $validated['collection'] ?? 'exams',
                'visibility' => $validated['visibility'] ?? 'SHARED_TEAM',
                'disk' => 'local',
                'file_path' => $path,
                'original_filename' => $uploadedFile->getClientOriginalName(),
                'mime_type' => (string) $uploadedFile->getClientMimeType(),
                'size_bytes' => (int) $uploadedFile->getSize(),
            ]);
        } else {
            if (empty($validated['original_filename']) || empty($validated['file_path'])) {
                return response()->json([
                    'message' => 'Either upload a file or provide original_filename and file_path.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $fileModel = File::create([
                'id' => (string) Str::uuid(),
                'fileable_type' => Patient::class,
                'fileable_id' => $patient->id,
                'collection' => $validated['collection'] ?? 'exams',
                'visibility' => $validated['visibility'] ?? 'SHARED_TEAM',
                'disk' => 's3',
                'file_path' => $validated['file_path'],
                'original_filename' => $validated['original_filename'],
                'mime_type' => $validated['mime_type'] ?? 'application/octet-stream',
                'size_bytes' => $validated['size_bytes'] ?? 1024,
            ]);
        }

        return response()->json([
            'message' => 'File attached to patient record successfully.',
            'data' => $fileModel,
        ], Response::HTTP_CREATED);
    }
}
