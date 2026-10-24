<?php

use App\Http\Controllers\FlightController;
use App\Http\Controllers\FlightManifestController;
use App\Http\Controllers\ManifestPdfController;
use App\Http\Controllers\ProfileUpdateRequests\PilotSignatureController;
use App\Http\Controllers\ProfileUpdateRequests\ProfileDocumentController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::view('/', 'access-gateway')->name('gateway');
Route::post('/pilot/signatures/prepare', [PilotSignatureController::class, 'prepare'])
    ->middleware(['auth', 'throttle:20,1'])->name('pilot-signatures.prepare');
Route::get('/pilot/signatures/{signature}', [PilotSignatureController::class, 'show'])
    ->middleware(['auth', 'throttle:60,1'])->name('pilot-signatures.show');
Route::view('/signed-out', 'signed-out')->name('signed-out');

Route::get('/manifest', [FlightManifestController::class, 'index'])->name('manifests.index');
Route::get('/manifest/blank', [ManifestPdfController::class, 'blank'])->middleware('auth')->name('manifests.blank');
Route::get('/manifest/guest', [ManifestPdfController::class, 'guestForm'])->name('manifests.guest');
Route::post('/manifest/guest/pdf', [ManifestPdfController::class, 'guest'])
    ->withoutMiddleware([
        StartSession::class,
        ShareErrorsFromSession::class,
        ValidateCsrfToken::class,
    ])->middleware('throttle:10,1')->name('manifests.guest.pdf');
Route::get('/flights/{flight}/manifest/pdf', [ManifestPdfController::class, 'show'])
    ->middleware(['auth', 'throttle:30,1'])->name('flights.manifest.pdf');
Route::get('/flights/{flight}/manifest', [FlightManifestController::class, 'edit'])
    ->middleware('auth')->name('flights.manifest.edit');
Route::put('/flights/{flight}/manifest', [FlightManifestController::class, 'update'])
    ->middleware(['auth', 'throttle:60,1'])->name('flights.manifest.update');

Route::get('/flightplan', [FlightController::class, 'flightplan'])->name('flightplan');
Route::post('/flightplan', [FlightController::class, 'store'])->name('flightplan.store');
Route::get('/flightplan/scan-qr', [FlightController::class, 'scanQr'])->name('flightplan.scan-qr');
Route::post('/flightplan/scan-qr', [FlightController::class, 'lookupScanQr'])->name('flightplan.scan-qr.lookup');
Route::post('/flightplan/edit-from-qr', [FlightController::class, 'editFromQr'])->name('flightplan.edit-from-qr');
Route::get('/flightplan/scan-qr/preview/{token}', [FlightController::class, 'previewScannedFlightPlan'])->name('flightplan.scan-qr.preview');
Route::get('/flightplan/scan-qr/pic-authorization-preview/{token}', [FlightController::class, 'previewPicAuthorizationFlightPlan'])->name('flightplan.pic-authorization.preview');
Route::get('/flightplan/preview', [FlightController::class, 'previewFlightPlan'])->name('flightplan.preview');
Route::post('/flightplan/approve', [FlightController::class, 'approveFlightPlan'])->name('flightplan.approve');
Route::post('/flightplan/pdf-only', [FlightController::class, 'generatePdfOnly'])->name('flightplan.pdf-only');
Route::post('/flightplan/edit-preview', [FlightController::class, 'editPreview'])->name('flightplan.edit-preview');
Route::post('/flightplan/discard-preview', [FlightController::class, 'discardPreview'])->name('flightplan.discard-preview');
Route::get('/flights/{flight}/qr', [FlightController::class, 'showQr'])->name('flights.qr');
Route::get('/flights/{flight}/qr-image', [FlightController::class, 'downloadQrImage'])->name('flights.qr.download');
Route::get('/flights/{flight}/view', [FlightController::class, 'showFlightPlanView'])->name('flights.view');
Route::post('/flights/{flight}/accept', [FlightController::class, 'acceptFlightPlan'])->name('flights.accept');
Route::post('/flights/{flight}/reject', [FlightController::class, 'rejectFlightPlan'])->name('flights.reject');
Route::get('/flights/{flight}/pdf', [FlightController::class, 'downloadPdf'])->name('flights.pdf.download');
Route::get('/reports/abbreviated/pdf', [FlightController::class, 'downloadAbbreviatedReportPdf'])
    ->middleware('auth')
    ->name('reports.abbreviated.pdf');
Route::get('/reports/post-ops-log/pdf', [FlightController::class, 'downloadPostOpsLogPdf'])
    ->middleware('auth')
    ->name('reports.post-ops-log.pdf');
Route::get('/profile-update-request-documents/{document}', [ProfileDocumentController::class, 'downloadUpdateRequestDocument'])
    ->middleware('auth')
    ->name('profile-update-request-documents.download');
Route::get('/user-kyc-documents/{document}', [ProfileDocumentController::class, 'downloadKycDocument'])
    ->middleware('auth')
    ->name('user-kyc-documents.download');
