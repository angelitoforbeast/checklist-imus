<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChecklistController;

// Auth routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Redirect root to checklist
Route::get('/', function () {
    return redirect('/checklist');
});

// Checklist routes (auth required)
Route::middleware('auth')->prefix('checklist')->group(function () {
    // Staff pages
    Route::get('/', [ChecklistController::class, 'index'])->name('checklist.index');
    Route::post('/{task}/submit', [ChecklistController::class, 'submit'])->name('checklist.submit');
    Route::delete('/submission/{submission}', [ChecklistController::class, 'deleteSubmission'])->name('checklist.deleteSubmission');
    Route::delete('/files/{file}', [ChecklistController::class, 'deleteFile'])->name('checklist.deleteFile');

    // Report page
    Route::get('/report', [ChecklistController::class, 'report'])->name('checklist.report');

    // AI Analysis
    Route::post('/submission/{submission}/analyze', [ChecklistController::class, 'analyzeSubmission'])->name('checklist.analyze');
    Route::get('/submission/{submission}/analysis-logs', [ChecklistController::class, 'getAnalysisLogs'])->name('checklist.analysisLogs');

    // Admin only - manage tasks
    Route::middleware('admin')->group(function () {
        Route::get('/manage', [ChecklistController::class, 'manage'])->name('checklist.manage');
        Route::post('/tasks', [ChecklistController::class, 'storeTask'])->name('checklist.storeTask');
        Route::patch('/tasks/{task}', [ChecklistController::class, 'updateTask'])->name('checklist.updateTask');
        Route::delete('/tasks/{task}', [ChecklistController::class, 'destroyTask'])->name('checklist.destroyTask');
        Route::post('/tasks/reorder', [ChecklistController::class, 'reorderTasks'])->name('checklist.reorderTasks');
    });
});
