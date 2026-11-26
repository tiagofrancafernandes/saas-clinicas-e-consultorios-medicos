<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\File;
use App\Models\Tenant\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class FileController extends Controller
{
    public function index(string $patientId): JsonResponse
    {
        $patient = Str::isUuid($patientId) ? Patient::query()->find($patientId) : null;

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
        $patient = Str::isUuid($patientId) ? Patient::query()->find($patientId) : null;

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

    public function download(string $patientId, string $fileId): StreamedResponse|JsonResponse
    {
        $file = Str::isUuid($patientId) && Str::isUuid($fileId)
            ? File::query()
                ->where('fileable_type', Patient::class)
                ->where('fileable_id', $patientId)
                ->find($fileId)
            : null;

        if (! $file) {
            return response()->json(['message' => 'File not found.'], Response::HTTP_NOT_FOUND);
        }

        if (! Storage::disk($file->disk)->exists($file->file_path)) {
            return response()->json(['message' => 'File content not available on storage disk.'], Response::HTTP_NOT_FOUND);
        }

        return Storage::disk($file->disk)->download($file->file_path, $file->original_filename);
    }

    public function destroy(string $patientId, string $fileId): JsonResponse
    {
        $file = Str::isUuid($patientId) && Str::isUuid($fileId)
            ? File::query()
                ->where('fileable_type', Patient::class)
                ->where('fileable_id', $patientId)
                ->find($fileId)
            : null;

        if (! $file) {
            return response()->json(['message' => 'File not found.'], Response::HTTP_NOT_FOUND);
        }

        $file->delete();

        return response()->json(['message' => 'File removed successfully.']);
    }
}
