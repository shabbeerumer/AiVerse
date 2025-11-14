@extends('layouts.app')

@section('title', 'Image Enhancer')

@section('content')
<div class="row justify-content-center">
    <div class="col-12">
        <div class="glass-card p-4 mb-4">
            <h2 class="mb-4 text-center">Image Enhancer</h2>
            
            <div class="row" id="alert-container"></div>
            
            <div class="loading-spinner text-center mb-4" id="loadingSpinner" style="display: none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Processing...</span>
                </div>
                <p class="mt-2">Enhancing image, please wait...</p>
            </div>
            
            <form id="enhancerForm" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label for="image" class="form-label">Upload Image:</label>
                    <input class="form-control" type="file" id="image" name="image" accept="image/*" required>
                    <div class="form-text">Supported formats: JPG, PNG, WEBP (Max 5MB)</div>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg" id="processBtn">
                        <i class="bi bi-zoom-in"></i> Enhance Image
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
                    <h4 class="text-center">Enhanced</h4>
                    <img id="resultImage" src="" class="img-fluid rounded image-preview" alt="Enhanced Image" loading="lazy">
                    
                    <div class="d-grid mt-3">
                        <a href="#" id="downloadBtn" class="btn btn-success">
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
        $('#enhancerForm').on('submit', function(e) {
            e.preventDefault();
            enhanceImage();
        });
        
        // Enhance image function
        function enhanceImage() {
            const imageFile = $('#image')[0].files[0];
            
            if (!imageFile) {
                showAlert('Please select an image', 'danger');
                return;
            }
            
            // Show loading spinner
            $('#loadingSpinner').show();
            $('#processBtn').prop('disabled', true);
            
            const formData = new FormData();
            formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
            formData.append('image', imageFile);
            
            $.ajax({
                url: '{{ route("ai.enhancer.process") }}',
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
                        showAlert('Image enhanced successfully!', 'success');
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
                    $('#processBtn').prop('disabled', false);
                    
                    let message = 'Error enhancing image';
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