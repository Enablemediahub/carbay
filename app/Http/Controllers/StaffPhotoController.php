<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Worker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StaffPhotoController extends Controller
{
    public function __invoke(Request $request, string $type, int $id): BinaryFileResponse
    {
        abort_unless(in_array($type, ['manager', 'worker'], true), 404);
        $staff = ($type === 'manager' ? User::withoutGlobalScopes() : Worker::withoutGlobalScopes())->findOrFail($id);
        $viewer = $request->user();
        if ($viewer instanceof Worker) {
            abort_unless($type === 'worker' && $viewer->id === $staff->id, 403);
        } elseif (! $viewer->isSuperAdmin()) {
            abort_unless($viewer->tenant_id === $staff->tenant_id, 403);
            if ($viewer->role === 'manager') {
                abort_unless($viewer->branch_id === $staff->branch_id, 403);
            }
        }
        abort_unless($staff->photo_path && str_starts_with($staff->photo_path, 'staff-photos/')
            && ! str_contains($staff->photo_path, '..') && Storage::disk('local')->exists($staff->photo_path), 404);

        return response()->file(Storage::disk('local')->path($staff->photo_path), ['Cache-Control' => 'private, no-store']);
    }
}
