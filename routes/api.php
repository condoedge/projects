<?php

use Condoedge\Projects\Http\Controllers\GithubWebhookController;
use Condoedge\Projects\Http\Middleware\VerifyGithubSignature;
use Illuminate\Support\Facades\Route;

// GitHub → SISC real-time sync. Full path: /api/pm/github/webhook
Route::post('pm/github/webhook', [GithubWebhookController::class, 'handle'])
    ->middleware(VerifyGithubSignature::class)
    ->name('pm.github.webhook');
