<?php

use App\Http\Controllers\ZKTecoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// ZKTeco Biometric Routes
Route::prefix('zkteco')->group(function () {
    Route::get('/dashboard', [ZKTecoController::class, 'dashboard'])->name('zkteco.dashboard');
    Route::post('/device', [ZKTecoController::class, 'storeDevice'])->name('zkteco.store.device');
    Route::post('/employee', [ZKTecoController::class, 'storeEmployee'])->name('zkteco.store.employee');
    Route::post('/command/user', [ZKTecoController::class, 'createUserCommand'])->name('zkteco.command.user');
    Route::post('/test/connection', [ZKTecoController::class, 'testConnection'])->name('zkteco.test.connection');

    // Monitoring endpoints
    Route::get('/command/{commandId}/status', [ZKTecoController::class, 'checkCommandStatus'])->name('zkteco.command.status');
    Route::get('/device/{deviceSerial}/pending', [ZKTecoController::class, 'getPendingCommands'])->name('zkteco.device.pending');
    Route::get('/device/{deviceSerial}/history', [ZKTecoController::class, 'getDeviceCommandHistory'])->name('zkteco.device.history');
});


// ZKTeco Device Communication Endpoints (as per package specification)
Route::prefix('iclock')->group(function () {
    Route::get('/cdata', [ZKTecoController::class, 'deviceHandshake']);
    Route::post('/cdata', [ZKTecoController::class, 'receiveAttendance']);
    Route::get('/getrequest', [ZKTecoController::class, 'getCommands']);
    Route::post('/devicecmd', [ZKTecoController::class, 'receiveCommandResult']);
});
