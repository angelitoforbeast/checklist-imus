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

// Redirect root — admin goes to conversations, others to checklist
Route::get('/', function () {
    if (Auth::check() && Auth::user()->isAdmin()) {
        return redirect('/checklist/conversations');
    }
    return redirect('/checklist');
});

// ✅ Daily Checklist (all authenticated users)
Route::middleware(['web','auth'])->prefix('checklist')->name('checklist.')->group(function () {
    Route::get('/', [ChecklistController::class, 'index'])->name('index');
    Route::get('/poll-status', [ChecklistController::class, 'pollStatus'])->name('poll-status');
    Route::get('/{task}/poll-conversation', [ChecklistController::class, 'pollConversation'])->name('poll-conversation');
    Route::get('/report', [ChecklistController::class, 'report'])->name('report');
    Route::get('/conversations', [ChecklistController::class, 'conversations'])->name('conversations');
    Route::post('/{task}/submit', [ChecklistController::class, 'submit'])->name('submit');
    Route::post("/{task}/upload-photo", [ChecklistController::class, "uploadPhoto"])->name("upload-photo");
    Route::post("/{task}/send-note", [ChecklistController::class, "sendNote"])->name("send-note");
    Route::post('/{task}/start', [ChecklistController::class, 'startTask'])->name('start-task');
    Route::delete('/submission/{submission}', [ChecklistController::class, 'deleteSubmission'])->name('delete-submission');
    Route::delete('/files/{file}', [ChecklistController::class, 'deleteFile'])->name('delete-file');

    // Admin-only checklist management
    Route::middleware('admin')->group(function () {
        Route::get('/manage', [ChecklistController::class, 'manage'])->name('manage');
        Route::post('/tasks', [ChecklistController::class, 'storeTask'])->name('store-task');
        Route::patch('/tasks/{task}', [ChecklistController::class, 'updateTask'])->name('update-task');
        Route::delete('/tasks/{task}', [ChecklistController::class, 'destroyTask'])->name('destroy-task');
        Route::post('/tasks/{task}/duplicate', [ChecklistController::class, 'duplicateTask'])->name('duplicate-task');
        Route::post('/tasks/{task}/toggle-active', [ChecklistController::class, 'toggleActive'])->name('toggle-active');
        Route::post('/tasks/reorder', [ChecklistController::class, 'reorderTasks'])->name('reorder');
        Route::post('/tasks/bulk-assign', [ChecklistController::class, 'bulkAssign'])->name('bulk-assign');
        Route::post('/tasks/bulk-delete', [ChecklistController::class, 'bulkDelete'])->name('bulk-delete');
        Route::post('/tasks/bulk-toggle-active', [ChecklistController::class, 'bulkToggleActive'])->name('bulk-toggle-active');
        Route::post('/submission/{submission}/revert', [ChecklistController::class, 'revertSubmission'])->name('revert-submission');
        Route::post('/submission/{submission}/reset', [ChecklistController::class, 'resetSubmission'])->name('reset-submission');
    Route::post('/task/{task}/send-comment', [ChecklistController::class, 'sendComment'])->name('send-comment');
    });

    Route::post('/submission/{submission}/analyze', [ChecklistController::class, 'analyzeSubmission'])->name('analyze');
    Route::get('/submission/{submission}/analysis-logs', [ChecklistController::class, 'getAnalysisLogs'])->name('analysis-logs');
    Route::post('/submission/{submission}/approval-check', [ChecklistController::class, 'approvalCheck'])->name('approval-check');
    Route::get('/submission/{submission}/approval-logs', [ChecklistController::class, 'getApprovalLogs'])->name('approval-logs');
});

// 🔐 Admin Panel — Roles & Users
Route::middleware(['web','auth','admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/roles', [RoleController::class, 'rolesIndex'])->name('roles');
    Route::get('/users', [RoleController::class, 'usersIndex'])->name('users');
    Route::post('/roles', [RoleController::class, 'storeRole'])->name('store-role');
    Route::patch('/roles/{role}', [RoleController::class, 'updateRole'])->name('update-role');
    Route::delete('/roles/{role}', [RoleController::class, 'destroyRole'])->name('destroy-role');

    Route::post('/users', [RoleController::class, 'storeUser'])->name('store-user');
    Route::patch('/users/{user}', [RoleController::class, 'updateUser'])->name('update-user');
    Route::patch('/users/{user}/toggle', [RoleController::class, 'toggleUser'])->name('toggle-user');
    Route::delete('/users/{user}', [RoleController::class, 'destroyUser'])->name('destroy-user');
});
