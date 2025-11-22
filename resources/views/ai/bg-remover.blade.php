@extends('layouts.app')

@section('title', 'Background Remover')

@section('content')
<div class="row justify-content-center">
    <div class="col-12">
        <div class="glass-card p-4 mb-4">
            <h2 class="mb-4 text-center">Background Remover</h2>
            
            <div class="row" id="alert-container"></div>
            
            <div class="loading-spinner text-center mb-4" id="loadingSpinner" style="display: none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Processing...</span>
                </div>
                <p class="mt-2">Removing background, please wait...</p>
            </div>
            
            <form id="bgRemoverForm" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label for="image" class="form-label">Upload Image:</label>
                    <input class="form-control" type="file" id="image" name="image" accept="image/*" required>
                    <div class="form-text">Supported formats: JPG, PNG, WEBP (Max 10MB)</div>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg" id="processBtn">
                        <i class="bi bi-back"></i> Remove Background
                    </button>
                </div>
            </form>
        </div>
        
        <div class="glass-card p-4" id="resultsSection" style="display: none;">
            <h3 class="mb-4 text-center">Result</h3>
            
            <div class="row">
                <div class="col-md-6 mb-4 mb-md-0">
                    <h4 class="text-center">Original</h4>
                    <img id="originalImage" src="" class="img-fluid rounded image-preview" alt="Original Image" loading="lazy">
                </div>
                <div class="col-md-6">
                    <h4 class="text-center">Background Removed</h4>
                    <img id="resultImage" src="" class="img-fluid rounded image-preview" alt="Result Image" loading="lazy">
                    
                    <div class="d-grid mt-3">
                        <a href="#" id="downloadBtn" class="btn btn-success" download>
                            <i class="bi bi-download"></i> Download Result
                        </a>
                    </div>
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
        $('#bgRemoverForm').on('submit', function(e) {
            e.preventDefault();
            removeBackground();
        });
        
        // Remove background function
        function removeBackground() {
            const imageFile = $('#image')[0].files[0];
            
            if (!imageFile) {
                showAlert('Please select an image', 'danger');
                return;
            }
            
            // Show loading spinner
            $('#loadingSpinner').show();
            $('#processBtn').prop('disabled', true);
            
            // Display original image preview
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#originalImage').attr('src', e.target.result);
            }
            reader.readAsDataURL(imageFile);
            
            const formData = new FormData();
            formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
            formData.append('image', imageFile);
            
            $.ajax({
                url: '{{ route("ai.bg-remover.process") }}',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $('#loadingSpinner').hide();
                    $('#processBtn').prop('disabled', false);
                    
                    if (response.success) {
                        $('#resultImage').attr('src', response.result_url);
                        $('#downloadBtn').attr('href', response.result_url);
                        $('#resultsSection').show();
                        showAlert('Background removed successfully!', 'success');
                    } else {
                        showAlert(response.message, 'danger');
                        
                        // Provide specific guidance for payment-related errors
                        if (response.message && response.message.toLowerCase().includes('payment')) {
                            showAlert('We are trying multiple free models. If this continues to fail, you may need to add your own Hugging Face API token to the .env file or try again later when free quota is available. As an alternative, consider installing the free "rembg" Python package locally.', 'info');
                        }
                        // If error is retryable, show retry option
                        else if (response.retryable) {
                            showAlert('You can try again in a few moments.', 'info');
                        }
                    }
                },
                error: function(xhr) {
                    $('#loadingSpinner').hide();
                    $('#processBtn').prop('disabled', false);
                    
                    let message = 'Error removing background';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    showAlert(message, 'danger');
                    
                    // Provide specific guidance for payment-related errors
                    if (message && message.toLowerCase().includes('payment')) {
                        showAlert('We are trying multiple free models. If this continues to fail, you may need to add your own Hugging Face API token to the .env file or try again later when free quota is available. As an alternative, consider installing the free "rembg" Python package locally.', 'info');
                    }
                }
            });
        }
    });
</script>
@endsection