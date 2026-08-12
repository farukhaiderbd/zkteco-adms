<?php

use App\ZKTeco\Http\Controllers\ZKTecoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ZKTeco Biometric API Routes
|--------------------------------------------------------------------------
|
| These routes handle communication with ZKTeco biometric devices.
| They are specifically designed for device-to-server communication.
|
*/

// ZKTeco device communication endpoints
Route::get('/iclock/cdata', [ZKTecoController::class, 'handshake'])->name('zkteco.handshake');
Route::post('/iclock/cdata', [ZKTecoController::class, 'handleAttendanceData'])->name('zkteco.attendance');
Route::get('/iclock/getrequest', [ZKTecoController::class, 'handleGetRequest'])->name('zkteco.get_request');
Route::post('/iclock/devicecmd', [ZKTecoController::class, 'handleDeviceCommand'])->name('zkteco.device_command');

// Optional ping endpoint for device status checks
Route::get('/iclock/ping', [ZKTecoController::class, 'handlePing'])->name('zkteco.ping');
