@extends('layouts.app')

@section('title', 'Audio Downloader')

@section('content')
<div class="row justify-content-center">
    <div class="col-12">
        <div class="glass-card p-4 mb-4">
            <h2 class="mb-4 text-center">YouTube Audio Downloader</h2>
            
            <div class="row" id="alert-container"></div>
            
            <div class="loading-spinner text-center mb-4" id="loadingSpinner" style="display: none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Processing...</span>
                </div>
                <p class="mt-2">Downloading audio, please wait...</p>
                <p class="text-muted small">This may take a few minutes for longer videos</p>
            </div>
            
            <form id="audioForm">
                @csrf
                <div class="mb-3">
                    <label for="url" class="form-label">YouTube Video URL:</label>
                    <input type="url" class="form-control" id="url" name="url" 
                        placeholder="https://www.youtube.com/watch?v=..." required>
                    <div class="form-text">
                        Example: https://www.youtube.com/watch?v=dQw4w9WgXcQ
                    </div>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg" id="downloadBtn">
                        <i class="bi bi-music-note-beamed"></i> Download Audio
                    </button>
                </div>
            </form>
        </div>
        
        <div class="glass-card p-4" id="resultsSection" style="display: none;">
            <h3 class="mb-4 text-center">Download Audio</h3>
            
            <div class="text-center">
                <audio id="audioPlayer" controls class="w-100 mb-3">
                    <source src="" type="audio/mpeg">
                    Your browser does not support the audio element.
                </audio>
                
                <div class="d-grid">
                    <a href="#" id="downloadLink" class="btn btn-success">
                        <i class="bi bi-download"></i> Download MP3
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        // Handle form submission
        $('#audioForm').on('submit', function(e) {
            e.preventDefault();
            downloadAudio();
        });
        
        // Download audio function
        function downloadAudio() {
            const url = $('#url').val().trim();
            
            if (!url) {
                showAlert('Please enter a YouTube URL', 'danger');
                return;
            }
            
            // Show loading spinner
            $('#loadingSpinner').show();
            $('#downloadBtn').prop('disabled', true);
            
            $.ajax({
                url: '{{ route("ai.audio-downloader.process") }}',
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    url: url
                },
                success: function(response) {
                    $('#loadingSpinner').hide();
                    $('#downloadBtn').prop('disabled', false);
                    
                    if (response.success) {
                        $('#audioPlayer source').attr('src', response.audio_url);
                        $('#audioPlayer')[0].load();
                        $('#downloadLink').attr('href', response.audio_url);
                        $('#resultsSection').show();
                        showAlert('Audio downloaded successfully!', 'success');
                    } else {
                        showAlert(response.message, 'danger');
                        
                        // If error is retryable, show retry option
                        if (response.retryable) {
                            showAlert('You can try again in a few moments.', 'info');
                        }
                    }
                },
                error: function(xhr) {
                    $('#loadingSpinner').hide();
                    $('#downloadBtn').prop('disabled', false);
                    
                    let message = 'Error downloading audio';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    showAlert(message, 'danger');
                }
            });
        }
    });
</script>
@endsection