<?php

namespace App\Http\Controllers\ProfileUpdateRequests;

use App\Domain\Users\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ProfileUpdateRequestDocument;
use App\Models\User;
use App\Models\UserKycDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileDocumentController extends Controller
{
    public function downloadUpdateRequestDocument(Request $request, ProfileUpdateRequestDocument $document)
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $document->loadMissing('request.user');
        abort_unless($this->canAccessUserDocument($user, $document->request->user_id), 403);
        abort_unless(Storage::disk('local')->exists($document->stored_path), 404);

        return Storage::disk('local')->download($document->stored_path, $document->original_filename);
    }

    public function downloadKycDocument(Request $request, UserKycDocument $document)
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);
        abort_unless($this->canAccessUserDocument($user, $document->user_id), 403);
        abort_unless(filled($document->file_path) && Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download(
            $document->file_path,
            $document->original_file_name ?: basename((string) $document->file_path),
        );
    }

    private function canAccessUserDocument(User $actor, int $ownerId): bool
    {
        if (! $actor->is_active) {
            return false;
        }

        if ($actor->id === $ownerId) {
            return true;
        }

        return in_array($actor->role, [UserRole::Admin, UserRole::Artisan], true);
    }
}
