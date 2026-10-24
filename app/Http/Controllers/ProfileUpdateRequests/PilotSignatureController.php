<?php

namespace App\Http\Controllers\ProfileUpdateRequests;

use App\Domain\Users\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\PreparePilotSignatureRequest;
use App\Models\PilotSignature;
use App\Services\ProfileUpdates\PilotSignatureService;
use App\Services\ProfileUpdates\SignatureImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PilotSignatureController extends Controller
{
    public function prepare(PreparePilotSignatureRequest $request, SignatureImageService $images): JsonResponse
    {
        $image = $images->prepare($request->file('image')->get(), $request->validated('source'));

        return response()->json([
            'data_url' => 'data:image/png;base64,'.base64_encode($image['bytes']),
            'width' => $image['width'], 'height' => $image['height'],
        ])->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }

    public function show(Request $request, PilotSignature $signature, PilotSignatureService $signatures): Response
    {
        $actor = $request->user();
        abort_unless($actor?->is_active && ($actor->getKey() === $signature->user_id
            || in_array($actor->role, [UserRole::Admin, UserRole::Artisan], true)), 403);

        return response($signatures->verifiedBytes($signature))->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff')
            ->header('Content-Disposition', 'inline; filename="signature-v'.$signature->version.'.png"');
    }
}
