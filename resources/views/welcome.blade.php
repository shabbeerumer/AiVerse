@extends('layouts.app')

@section('title', 'Home')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 text-center mb-5">
            <h1 class="display-4 fw-bold text-white">Welcome to {{ config('app.name', 'AiVerse') }}</h1>
            <p class="lead text-white-50">Powerful AI tools powered by open-source models and free APIs</p>
        </div>
    </div>
    
    <div class="row" id="alert-container"></div>
    
    <div class="row">
        <!-- Image Generator -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="glass-card tool-card h-100 p-4">
                <div class="text-center">
                    <i class="bi bi-image-fill fs-1 text-primary"></i>
                    <h3 class="mt-3">Image Generator</h3>
                    <p class="text-white-50">Generate AI images from text prompts using Stable Diffusion</p>
                    <a href="{{ route('ai.image-generator') }}" class="btn btn-primary">Try Now</a>
                </div>
            </div>
        </div>
        
        <!-- Background Remover -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="glass-card tool-card h-100 p-4">
                <div class="text-center">
                    <i class="bi bi-back fs-1 text-success"></i>
                    <h3 class="mt-3">Background Remover</h3>
                    <p class="text-white-50">Remove image backgrounds with U²-Net model</p>
                    <a href="{{ route('ai.bg-remover') }}" class="btn btn-primary">Try Now</a>
                </div>
            </div>
        </div>
        
        <!-- Image Enhancer -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="glass-card tool-card h-100 p-4">
                <div class="text-center">
                    <i class="bi bi-zoom-in fs-1 text-info"></i>
                    <h3 class="mt-3">Image Enhancer</h3>
                    <p class="text-white-50">Enhance image quality with Real-ESRGAN</p>
                    <a href="{{ route('ai.enhancer') }}" class="btn btn-primary">Try Now</a>
                </div>
            </div>
        </div>
        
        <!-- Voice Clone -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="glass-card tool-card h-100 p-4">
                <div class="text-center">
                    <i class="bi bi-mic-fill fs-1 text-warning"></i>
                    <h3 class="mt-3">Voice Clone</h3>
                    <p class="text-white-50">Clone voices with XTTS-v2 model</p>
                    <a href="{{ route('ai.voice-clone') }}" class="btn btn-primary">Try Now</a>
                </div>
            </div>
        </div>
        
        <!-- Thumbnail Downloader -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="glass-card tool-card h-100 p-4">
                <div class="text-center">
                    <i class="bi bi-download fs-1 text-danger"></i>
                    <h3 class="mt-3">Thumbnail Downloader</h3>
                    <p class="text-white-50">Download YouTube video thumbnails</p>
                    <a href="{{ route('ai.thumbnail-downloader') }}" class="btn btn-primary">Try Now</a>
                </div>
            </div>
        </div>
        
        <!-- Tag Downloader -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="glass-card tool-card h-100 p-4">
                <div class="text-center">
                    <i class="bi bi-tags-fill fs-1 text-secondary"></i>
                    <h3 class="mt-3">Tag Downloader</h3>
                    <p class="text-white-50">Extract YouTube video tags and metadata</p>
                    <a href="{{ route('ai.tag-downloader') }}" class="btn btn-primary">Try Now</a>
                </div>
            </div>
        </div>
        
        <!-- Audio Downloader -->
        <div class="col-md-6 col-lg-4 mb-4 mx-auto">
            <div class="glass-card tool-card h-100 p-4">
                <div class="text-center">
                    <i class="bi bi-music-note-beamed fs-1 text-primary"></i>
                    <h3 class="mt-3">Audio Downloader</h3>
                    <p class="text-white-50">Download YouTube audio as MP3</p>
                    <a href="{{ route('ai.audio-downloader') }}" class="btn btn-primary">Try Now</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
