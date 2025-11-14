@extends('layouts.app')

@section('title', 'Voice Clone')

@section('content')
<div class="row justify-content-center">
    <div class="col-12">
        <div class="glass-card p-4 mb-4">
            <h2 class="mb-4 text-center">Voice Clone</h2>
            
            <div class="row" id="alert-container"></div>
            
            <div class="loading-spinner text-center mb-4" id="loadingSpinner" style="display: none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Processing...</span>
                </div>
                <p class="mt-2">Cloning voice, please wait...</p>
            </div>
            
            <form id="voiceCloneForm" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label for="voice_sample" class="form-label">Upload Voice Sample:</label>
                    <input class="form-control" type="file" id="voice_sample" name="voice_sample" accept="audio/wav,audio/mp3" required>
                    <div class="form-text">Supported formats: WAV, MP3 (Max 10MB)</div>
                </div>
                
                <div class="mb-3">
                    <label for="text" class="form-label">Enter Text:</label>
                    <textarea class="form-control" id="text" name="text" rows="4" 
                        placeholder="Enter the text you want to convert to speech..." required maxlength="1000"></textarea>
                    <div class="form-text">
                        Maximum 1000 characters. 
                        <span id="charCount">0/1000</span>
                    </div>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg" id="cloneBtn">
                        <i class="bi bi-mic-fill"></i> Clone Voice
                    </button>
                </div>
            </form>
        </div>
        
        <div class="glass-card p-4" id="resultsSection" style="display: none;">
            <h3 class="mb-4 text-center">Generated Audio</h3>
            
            <div class="text-center">
                <audio id="audioPlayer" controls class="w-100 mb-3">
                    <source src="" type="audio/wav">
                    Your browser does not support the audio element.
                </audio>
                
                <div class="d-grid">
                    <a href="#" id="downloadBtn" class="btn btn-success">
                        <i class="bi bi-download"></i> Download Audio
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
        $('#voiceCloneForm').on('submit', function(e) {
            e.preventDefault();
            cloneVoice();
        });
        
        // Update character count
        $('#text').on('input', function() {
            const count = $(this).val().length;
            $('#charCount').text(count + '/1000');
            
            if (count > 900) {
                $('#charCount').addClass('text-danger');
            } else {
                $('#charCount').removeClass('text-danger');
            }
        });
        
        // Clone voice function
        function cloneVoice() {
            const voiceFile = $('#voice_sample')[0].files[0];
            const text = $('#text').val().trim();
            
            if (!voiceFile) {
                showAlert('Please select a voice sample', 'danger');
                return;
            }
            
            if (!text) {
                showAlert('Please enter some text', 'danger');
                return;
            }
            
            if (text.length > 1000) {
                showAlert('Text must be less than 1000 characters', 'danger');
                return;
            }
            
            // Check for special characters
            if (/[<>{}[\]\\\\\/]/.test(text)) {
                showAlert('Text contains invalid characters', 'danger');
                return;
            }
            
            // Show loading spinner
            $('#loadingSpinner').show();
            $('#cloneBtn').prop('disabled', true);
            
            const formData = new FormData();
            formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
            formData.append('voice_sample', voiceFile);
            formData.append('text', text);
            
            $.ajax({
                url: '{{ route("ai.voice-clone.generate") }}',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $('#loadingSpinner').hide();
                    $('#cloneBtn').prop('disabled', false);
                    
                    if (response.success) {
                        $('#audioPlayer source').attr('src', response.audio_url);
                        $('#audioPlayer')[0].load();
                        $('#downloadBtn').attr('href', response.audio_url);
                        $('#resultsSection').show();
                        showAlert('Voice cloned successfully!', 'success');
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
                    $('#cloneBtn').prop('disabled', false);
                    
                    let message = 'Error cloning voice';
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