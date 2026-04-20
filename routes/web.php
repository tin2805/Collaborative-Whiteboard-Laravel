<?php

use App\Http\Controllers\RoomController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return redirect()->route('rooms.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
    Route::get('/rooms/my-rooms', [RoomController::class, 'myRooms'])->name('rooms.my');
    Route::get('/rooms/public', [RoomController::class, 'publicRooms'])->name('rooms.public');
    Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
    Route::get('/rooms/{room}/check-private', [RoomController::class, 'checkPrivate'])->name('rooms.check-private');
    Route::post('/rooms/{room}/join', [RoomController::class, 'join'])->name('rooms.join');
    Route::delete('/rooms/{room}/members/{user}', [RoomController::class, 'kickMember'])->name('rooms.members.kick');
    Route::delete('/rooms/{room}/leave', [RoomController::class, 'leaveRoom'])->name('rooms.leave');
    Route::patch('/rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
    Route::get('/rooms/{room}', [RoomController::class, 'show'])->name('rooms.show');
    Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');
    Route::post('/rooms/{room}/objects', [RoomController::class, 'storeObject'])->name('rooms.objects.store');
    Route::patch('/rooms/{room}/objects/{object}', [RoomController::class, 'updateObject'])->name('rooms.objects.update');
    Route::delete('/rooms/{room}/objects/{object}', [RoomController::class, 'destroyObject'])->name('rooms.objects.destroy');
    Route::delete('/rooms/{room}/clear', [RoomController::class, 'clearAllObjects'])->name('rooms.clear');
    Route::delete('/rooms/{room}/clear-mine', [RoomController::class, 'clearMyObjects'])->name('rooms.clear-mine');
    Route::post('/rooms/{room}/request-clear', [RoomController::class, 'requestClearAll'])->name('rooms.request-clear');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
