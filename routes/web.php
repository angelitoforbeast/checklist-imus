<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\RoleController;

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

// ✅ Daily Checklist (all authenticated users)
Route::middleware(['web','auth'])->prefix('checklist')->name('checklist.')->group(function () {
    Route::get('/', [ChecklistController::class, 'index'])->name('index');
    Route::get('/report', [ChecklistController::class, 'report'])->name('report');
    Route::post('/{task}/submit', [ChecklistController::class, 'submit'])->name('submit');
    Route::post("/{task}/upload-photo", [ChecklistController::class, "uploadPhoto"])->name("upload-photo");
    Route::post("/{task}/send-note", [ChecklistController::class, "sendNote"])->name("send-note");
    Route::delete('/submission/{submission}', [ChecklistController::class, 'deleteSubmission'])->name('delete-submission');
    Route::delete('/files/{file}', [ChecklistController::class, 'deleteFile'])->name('delete-file');

    // Admin-only checklist management
    Route::middleware('admin')->group(function () {
        Route::get('/manage', [ChecklistController::class, 'manage'])->name('manage');
        Route::post('/tasks', [ChecklistController::class, 'storeTask'])->name('store-task');
        Route::patch('/tasks/{task}', [ChecklistController::class, 'updateTask'])->name('update-task');
        Route::delete('/tasks/{task}', [ChecklistController::class, 'destroyTask'])->name('destroy-task');
        Route::post('/tasks/reorder', [ChecklistController::class, 'reorderTasks'])->name('reorder');
        Route::post('/submission/{submission}/revert', [ChecklistController::class, 'revertSubmission'])->name('revert-submission');
    });

    Route::post('/submission/{submission}/analyze', [ChecklistController::class, 'analyzeSubmission'])->name('analyze');
    Route::get('/submission/{submission}/analysis-logs', [ChecklistController::class, 'getAnalysisLogs'])->name('analysis-logs');
    Route::post('/submission/{submission}/approval-check', [ChecklistController::class, 'approvalCheck'])->name('approval-check');
    Route::get('/submission/{submission}/approval-logs', [ChecklistController::class, 'getApprovalLogs'])->name('approval-logs');
});

// 🔐 Admin Panel — Roles & Users
Route::middleware(['web','auth','admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/roles', [RoleController::class, 'index'])->name('roles');
    Route::post('/roles', [RoleController::class, 'storeRole'])->name('store-role');
    Route::patch('/roles/{role}', [RoleController::class, 'updateRole'])->name('update-role');
    Route::delete('/roles/{role}', [RoleController::class, 'destroyRole'])->name('destroy-role');

    Route::post('/users', [RoleController::class, 'storeUser'])->name('store-user');
    Route::patch('/users/{user}', [RoleController::class, 'updateUser'])->name('update-user');
    Route::patch('/users/{user}/toggle', [RoleController::class, 'toggleUser'])->name('toggle-user');
    Route::delete('/users/{user}', [RoleController::class, 'destroyUser'])->name('destroy-user');
});
