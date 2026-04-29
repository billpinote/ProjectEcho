<?php

use App\Http\Controllers\FlightController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('/flightplan', [FlightController::class, 'flightplan'])->name('flightplan');
Route::post('/flightplan', [FlightController::class, 'store'])->name('flightplan.store');
Route::get('/flightplan/scan-qr', [FlightController::class, 'scanQr'])->name('flightplan.scan-qr');
Route::post('/flightplan/scan-qr', [FlightController::class, 'lookupScanQr'])->name('flightplan.scan-qr.lookup');
Route::get('/flightplan/scan-qr/preview/{token}', [FlightController::class, 'previewScannedFlightPlan'])->name('flightplan.scan-qr.preview');
Route::get('/flightplan/preview', [FlightController::class, 'previewFlightPlan'])->name('flightplan.preview');
Route::post('/flightplan/approve', [FlightController::class, 'approveFlightPlan'])->name('flightplan.approve');
Route::post('/flightplan/edit-preview', [FlightController::class, 'editPreview'])->name('flightplan.edit-preview');
Route::post('/flightplan/discard-preview', [FlightController::class, 'discardPreview'])->name('flightplan.discard-preview');
Route::get('/flights/{flight}/qr', [FlightController::class, 'showQr'])->name('flights.qr');
Route::get('/flights/{flight}/qr-image', [FlightController::class, 'downloadQrImage'])->name('flights.qr.download');
Route::get('/flights/{flight}/view', [FlightController::class, 'showFlightPlanView'])->name('flights.view');
Route::post('/flights/{flight}/accept', [FlightController::class, 'acceptFlightPlan'])->name('flights.accept');
Route::post('/flights/{flight}/reject', [FlightController::class, 'rejectFlightPlan'])->name('flights.reject');
Route::get('/flights/{flight}/pdf', [FlightController::class, 'downloadPdf'])->name('flights.pdf.download');
