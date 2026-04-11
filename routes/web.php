<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChecklistController;

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// Logout
Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/login');
})->name('logout');

// Redirect root to checklist
Route::get('/', fn () => redirect('/checklist'));

// ✅ Daily Checklist
Route::middleware(['web','auth'])->prefix('checklist')->name('checklist.')->group(function () {
    Route::get('/', [ChecklistController::class, 'index'])->name('index');
    Route::get('/manage', [ChecklistController::class, 'manage'])->name('manage');
    Route::get('/report', [ChecklistController::class, 'report'])->name('report');
    Route::post('/{task}/submit', [ChecklistController::class, 'submit'])->name('submit');
    Route::delete('/submission/{submission}', [ChecklistController::class, 'deleteSubmission'])->name('delete-submission');
    Route::delete('/files/{file}', [ChecklistController::class, 'deleteFile'])->name('delete-file');
    Route::post('/tasks', [ChecklistController::class, 'storeTask'])->name('store-task');
    Route::patch('/tasks/{task}', [ChecklistController::class, 'updateTask'])->name('update-task');
    Route::delete('/tasks/{task}', [ChecklistController::class, 'destroyTask'])->name('destroy-task');
    Route::post('/tasks/reorder', [ChecklistController::class, 'reorderTasks'])->name('reorder');
    Route::post('/submission/{submission}/analyze', [ChecklistController::class, 'analyzeSubmission'])->name('analyze');
    Route::get('/submission/{submission}/analysis-logs', [ChecklistController::class, 'getAnalysisLogs'])->name('analysis-logs');
});
