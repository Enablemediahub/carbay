<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Tenant;
use App\Services\PlateOcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlateOcrController extends Controller
{
    public function __invoke(Request $request, PlateOcrService $ocr): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user?->role, ['manager', 'ceo'], true) && $user?->tenant_id && $user?->branch_id, 403);
        $request->validate(['photo' => ['required', 'image', 'max:10240']]);
        $tenant = Tenant::withoutGlobalScopes()->findOrFail($user->tenant_id);
        abort_unless(Branch::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereKey($user->branch_id)
            ->where('status', 'active')
            ->exists(), 402, 'This branch is inactive.');
        $result = $ocr->scan($request->file('photo'), $tenant, (int) $user->branch_id, (int) $user->id);

        if ($result['confidence'] < 0.7) {
            $result['confirmation_required'] = true;
        } else {
            $result['confirmation_required'] = false;
        }

        return response()->json($result);
    }
}
