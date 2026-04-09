<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', function () {
    // Si falla el JWT y Laravel intenta redirigir al "login", lo mandamos de vuelta al portal Madre
    $frontendUrl = config('services.app_frontend.url');
    return redirect($frontendUrl . '/login?session_expired=true');
})->name('login');
