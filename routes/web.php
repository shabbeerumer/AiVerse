<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AI\ImageGenController;
use App\Http\Controllers\AI\BgRemoveController;
use App\Http\Controllers\AI\EnhanceController;
use App\Http\Controllers\AI\VoiceCloneController;
use App\Http\Controllers\AI\ThumbnailController;
use App\Http\Controllers\AI\TagController;
use App\Http\Controllers\AI\AudioController;

Route::get('/', function () {
    return view('welcome');
});

// AI Tools Routes
Route::prefix('ai')->group(function () {
    // Image Generator
    Route::get('/image-generator', [ImageGenController::class, 'index'])->name('ai.image-generator');
    Route::post('/image-generator/generate', [ImageGenController::class, 'generate'])->name('ai.image-generator.generate');
    Route::post('/image-generator/download-all', [ImageGenController::class, 'downloadAll'])->name('ai.image-generator.download-all');
    Route::post('/image-generator/download-zip', [ImageGenController::class, 'downloadZip'])->name('ai.image-generator.download-zip');

    // Background Remover
    Route::get('/bg-remover', [BgRemoveController::class, 'index'])->name('ai.bg-remover');
    Route::post('/bg-remover/process', [BgRemoveController::class, 'removeBackground'])->name('ai.bg-remover.process');
    Route::post('/bg-remover/download', [BgRemoveController::class, 'download'])->name('ai.bg-remover.download');

    // Image Enhancer
    Route::get('/enhancer', [EnhanceController::class, 'index'])->name('ai.enhancer');
    Route::post('/enhancer/process', [EnhanceController::class, 'enhance'])->name('ai.enhancer.process');
    Route::post('/enhancer/download', [EnhanceController::class, 'download'])->name('ai.enhancer.download');

    // Voice Clone
    Route::get('/voice-clone', [VoiceCloneController::class, 'index'])->name('ai.voice-clone');
    Route::post('/voice-clone/generate', [VoiceCloneController::class, 'clone'])->name('ai.voice-clone.generate');
    Route::post('/voice-clone/download', [VoiceCloneController::class, 'download'])->name('ai.voice-clone.download');

    // Thumbnail Downloader
    Route::get('/thumbnail-downloader', [ThumbnailController::class, 'index'])->name('ai.thumbnail-downloader');
    Route::post('/thumbnail-downloader/fetch', [ThumbnailController::class, 'fetchThumbnails'])->name('ai.thumbnail-downloader.fetch');
    Route::post('/thumbnail-downloader/download', [ThumbnailController::class, 'download'])->name('ai.thumbnail-downloader.download');

    // Tag Downloader
    Route::get('/tag-downloader', [TagController::class, 'index'])->name('ai.tag-downloader');
    Route::post('/tag-downloader/fetch', [TagController::class, 'fetchTags'])->name('ai.tag-downloader.fetch');

    // Audio Downloader
    Route::get('/audio-downloader', [AudioController::class, 'index'])->name('ai.audio-downloader');
    Route::post('/audio-downloader/process', [AudioController::class, 'downloadAudio'])->name('ai.audio-downloader.process');
    Route::post('/audio-downloader/download', [AudioController::class, 'download'])->name('ai.audio-downloader.download');
});