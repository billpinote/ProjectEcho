<?php

use App\Http\Controllers\FlightManifestController;
use App\Http\Controllers\FlightPlans\FlightPlanController;
use App\Http\Controllers\FlightPlans\FlightPlanPdfController;
use App\Http\Controllers\FlightPlans\FlightPlanPreviewController;
use App\Http\Controllers\FlightPlans\FlightPlanQrController;
use App\Http\Controllers\FlightPlans\FlightPlanReviewController;
use App\Http\Controllers\FlightPlans\FlightPlanScanController;
use App\Http\Controllers\FlightPlans\PicAuthorizationPreviewController;
use App\Http\Controllers\ManifestPdfController;
use App\Http\Controllers\ProfileUpdateRequests\PilotSignatureController;
use App\Http\Controllers\ProfileUpdateRequests\ProfileDocumentController;
use App\Http\Controllers\Reports\FlightReportController;
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

Route::get('/flightplan', [FlightPlanController::class, 'flightplan'])->name('flightplan');
Route::post('/flightplan', [FlightPlanPreviewController::class, 'store'])->name('flightplan.store');
Route::get('/flightplan/scan-qr', [FlightPlanScanController::class, 'scanQr'])->name('flightplan.scan-qr');
Route::post('/flightplan/scan-qr', [FlightPlanScanController::class, 'lookupScanQr'])->name('flightplan.scan-qr.lookup');
Route::post('/flightplan/edit-from-qr', [FlightPlanScanController::class, 'editFromQr'])->name('flightplan.edit-from-qr');
Route::get('/flightplan/scan-qr/preview/{token}', [FlightPlanScanController::class, 'previewScannedFlightPlan'])->name('flightplan.scan-qr.preview');
Route::get('/flightplan/scan-qr/pic-authorization-preview/{token}', [PicAuthorizationPreviewController::class, 'previewPicAuthorizationFlightPlan'])->name('flightplan.pic-authorization.preview');
Route::get('/flightplan/preview', [FlightPlanPreviewController::class, 'previewFlightPlan'])->name('flightplan.preview');
Route::post('/flightplan/approve', [FlightPlanController::class, 'approveFlightPlan'])->name('flightplan.approve');
Route::post('/flightplan/pdf-only', [FlightPlanPdfController::class, 'generatePdfOnly'])->name('flightplan.pdf-only');
Route::post('/flightplan/edit-preview', [FlightPlanPreviewController::class, 'editPreview'])->name('flightplan.edit-preview');
Route::post('/flightplan/discard-preview', [FlightPlanPreviewController::class, 'discardPreview'])->name('flightplan.discard-preview');
Route::get('/flights/{flight}/qr', [FlightPlanQrController::class, 'showQr'])->name('flights.qr');
Route::get('/flights/{flight}/qr-image', [FlightPlanQrController::class, 'downloadQrImage'])->name('flights.qr.download');
Route::get('/flights/{flight}/view', [FlightPlanController::class, 'showFlightPlanView'])->name('flights.view');
Route::post('/flights/{flight}/accept', [FlightPlanReviewController::class, 'acceptFlightPlan'])->name('flights.accept');
Route::post('/flights/{flight}/reject', [FlightPlanReviewController::class, 'rejectFlightPlan'])->name('flights.reject');
Route::get('/flights/{flight}/pdf', [FlightPlanPdfController::class, 'downloadPdf'])->name('flights.pdf.download');
Route::get('/reports/abbreviated/pdf', [FlightReportController::class, 'downloadAbbreviatedReportPdf'])
    ->middleware('auth')
    ->name('reports.abbreviated.pdf');
Route::get('/reports/post-ops-log/pdf', [FlightReportController::class, 'downloadPostOpsLogPdf'])
    ->middleware('auth')
    ->name('reports.post-ops-log.pdf');
Route::get('/profile-update-request-documents/{document}', [ProfileDocumentController::class, 'downloadUpdateRequestDocument'])
    ->middleware('auth')
    ->name('profile-update-request-documents.download');
Route::get('/user-kyc-documents/{document}', [ProfileDocumentController::class, 'downloadKycDocument'])
    ->middleware('auth')
    ->name('user-kyc-documents.download');
