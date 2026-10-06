<?php

namespace App\Services;

use App\Models\Job;
use App\Models\PlateScan;
use App\Models\Tenant;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PlateOcrService
{
    /**
     * @return array{raw: string, confidence: float, scan_id: int}
     */
    public function scan(UploadedFile $file, Tenant $tenant, int $branchId, int $managerId): array
    {
        $key = 'plate-ocr-tenant:'.$tenant->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages(['photo' => 'Too many plate scans. Please wait one minute and try again.']);
        }
        RateLimiter::hit($key, 60);

        $token = config('services.plate_recognizer.api_token');
        if (! is_string($token) || trim($token) === '') {
            throw ValidationException::withMessages(['photo' => 'Plate recognition is not configured. Enter the registration manually.']);
        }

        $path = $file->store('plate-scans/'.$tenant->id, 'local');
        $scan = PlateScan::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'manager_id' => $managerId,
            'image_path' => $path,
        ]);
        $contents = file_get_contents($file->getRealPath());
        if (! is_string($contents) || $contents === '') {
            throw ValidationException::withMessages(['photo' => 'The captured image is empty. Please take another photo.']);
        }

        try {
            $request = Http::withHeaders(['Authorization' => 'Token '.$token])
                ->acceptJson()
                ->attach('upload', $contents, $file->getClientOriginalName());
            foreach (config('services.plate_recognizer.regions', ['gh']) as $region) {
                $request->attach('regions[]', (string) $region);
            }
            $response = $request->post(config('services.plate_recognizer.endpoint'))
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException $exception) {
            report($exception);
            throw ValidationException::withMessages(['photo' => 'Plate recognition could not process the image. Enter the registration manually.']);
        }
        if (! is_array($response)) {
            throw ValidationException::withMessages(['photo' => 'Plate recognition returned an invalid response. Enter the registration manually.']);
        }

        $result = data_get($response, 'results.0');
        $raw = is_array($result) && is_string($result['plate'] ?? null)
            ? strtoupper(trim($result['plate']))
            : '';
        $confidence = is_array($result) && is_numeric($result['score'] ?? null)
            ? (float) $result['score']
            : 0.0;
        if ($confidence < 0 || $confidence > 1) {
            throw ValidationException::withMessages(['photo' => 'Plate recognition returned an invalid confidence score. Enter the registration manually.']);
        }

        $scan->update(['ocr_raw' => $raw ?: null, 'ocr_confidence' => $confidence]);

        return ['raw' => $raw, 'confidence' => $confidence, 'scan_id' => $scan->id];
    }

    public function confirmCorrection(int $scanId, Job $job, string $correctedValue): void
    {
        $scan = PlateScan::query()
            ->whereKey($scanId)
            ->where('tenant_id', $job->tenant_id)
            ->where('branch_id', $job->branch_id)
            ->firstOrFail();

        $scan->update([
            'job_id' => $job->id,
            'corrected_value' => strtoupper(trim($correctedValue)),
        ]);
    }
}
