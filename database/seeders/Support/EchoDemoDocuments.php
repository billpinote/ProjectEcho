<?php

namespace Database\Seeders\Support;

use App\Models\PilotProfile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class EchoDemoDocuments
{
    /** @var list<string> */
    private array $createdPaths = [];

    public function __construct(private readonly EchoDemoDocumentImage $images) {}

    public function attach(User $user, PilotProfile $profile, User $reviewer): void
    {
        $license = $profile->formattedLicense();
        $expiry = $profile->license_expiry_date;
        $medicalExpiry = $profile->medical_expiry_date;
        $documents = [
            'license' => ['pilot_license', $license, 'Pilot License / Student Pilot Permit', $expiry, $expiry->copy()->subYears(3), 'License type: '.$profile->license_type->value],
            'medical' => ['other', 'TEST-MED-'.$profile->license_number, 'Medical Certificate — Test Fixture', $medicalExpiry, $medicalExpiry->copy()->subYears(2), 'Medical details are fictional; no clinical assessment.'],
            'qualifications' => ['other', 'TEST-QUAL-'.$profile->license_number, 'Qualification Record — Test Fixture', $medicalExpiry, $medicalExpiry->copy()->subYears(2), 'Training / ratings: '.$profile->ratings],
            'identity' => ['company_id', 'TEST-ID-'.$profile->license_number, 'Demo Profile Identity Card', $expiry, $expiry->copy()->subYears(3), 'Project Echo identity fixture; not a government ID.'],
        ];

        foreach ($documents as $key => [$type, $identifier, $title, $expires, $issued, $details]) {
            $path = 'kyc-documents/echo-demo/'.$user->id.'/'.$key.'.png';
            $document = $user->kycDocuments()->where('document_type', $type)
                ->where('document_identifier', $identifier)->first();

            // Never replace an attachment uploaded or reviewed through the application.
            if ($document !== null && ($document->file_path !== $path
                || $document->remarks !== EchoDemoProfiles::REMARKS || $document->verified_at !== null)) {
                continue;
            }

            $disk = Storage::disk('local');
            if ($document === null && $disk->exists($path)) {
                throw new RuntimeException('A demo document path is already occupied; existing files were preserved.');
            }
            if (! $disk->exists($path)) {
                $bytes = $this->images->render($user->name, $identifier, $title,
                    $issued->toDateString(), $expires->toDateString(), $details);
                $this->createdPaths[] = $path;
                if (! $disk->put($path, $bytes, ['visibility' => 'private'])) {
                    throw new RuntimeException('Could not store a private demo document.');
                }
            }

            if ($document === null) {
                $document = $user->kycDocuments()->create([
                    'document_type' => $type, 'document_identifier' => $identifier,
                    'file_path' => $path, 'original_file_name' => 'echo-demo-'.$key.'.png',
                    'verified_at' => null, 'verified_by_user_id' => null,
                    'remarks' => EchoDemoProfiles::REMARKS,
                ]);
            }

            $document->forceFill(['verified_at' => now(), 'verified_by_user_id' => $reviewer->id])->save();
            $user->auditLogs()->create([
                'action' => 'echo_demo_kyc_verified', 'source' => 'echo_demo_seeder',
                'performed_by_user_id' => $reviewer->id,
                'auditable_type' => $document->getMorphClass(), 'auditable_id' => $document->id,
                'description' => 'Fictional demo document marked verified for local software testing only.',
            ]);
        }
    }

    public function cleanUpFailedRun(): void
    {
        // Filesystem writes do not roll back with the database transaction.
        foreach ($this->createdPaths as $path) {
            Storage::disk('local')->delete($path);
        }
        $this->finishRun();
    }

    public function finishRun(): void
    {
        $this->createdPaths = [];
    }
}
