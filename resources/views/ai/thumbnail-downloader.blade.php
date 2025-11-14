@extends('layouts.app')

@section('title', 'Thumbnail Downloader')

@section('content')
<div class="row justify-content-center">
    <div class="col-12">
        <div class="glass-card p-4 mb-4">
            <h2 class="mb-4 text-center">YouTube Thumbnail Downloader</h2>
            
            <div class="row" id="alert-container"></div>
            
            <div class="loading-spinner text-center mb-4" id="loadingSpinner" style="display: none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Processing...</span>
                </div>
                <p class="mt-2">Fetching thumbnails, please wait...</p>
            </div>
            
            <form id="thumbnailForm">
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
                    <button type="submit" class="btn btn-primary btn-lg" id="fetchBtn">
                        <i class="bi bi-download"></i> Fetch Thumbnails
                    </button>
                </div>
            </form>
        </div>
        
        <div class="glass-card p-4" id="resultsSection" style="display: none;">
            <h3 class="mb-4 text-center">Thumbnails</h3>
            
            <div id="thumbnailGrid" class="row g-4">
                <!-- Thumbnails will be displayed here -->
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        // Handle form submission
        $('#thumbnailForm').on('submit', function(e) {
            e.preventDefault();
            fetchThumbnails();
        });
        
        // Fetch thumbnails function
        function fetchThumbnails() {
            const url = $('#url').val().trim();
            
            if (!url) {
                showAlert('Please enter a YouTube URL', 'danger');
                return;
            }
            
            // Show loading spinner
            $('#loadingSpinner').show();
            $('#fetchBtn').prop('disabled', true);
            
            $.ajax({
                url: '{{ route("ai.thumbnail-downloader.fetch") }}',
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    url: url
                },
                success: function(response) {
                    $('#loadingSpinner').hide();
                    $('#fetchBtn').prop('disabled', false);
                    
                    if (response.success) {
                        displayThumbnails(response.thumbnails);
                        showAlert('Thumbnails fetched successfully!', 'success');
                    } else {
                        showAlert(response.message, 'danger');
                    }
                },
                error: function(xhr) {
                    $('#loadingSpinner').hide();
                    $('#fetchBtn').prop('disabled', false);
                    
                    let message = 'Error fetching thumbnails';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    showAlert(message, 'danger');
                }
            });
        }
        
        // Display thumbnails
        function displayThumbnails(thumbnails) {
            const thumbnailGrid = $('#thumbnailGrid');
            thumbnailGrid.empty();
            
            const thumbnailTypes = [
                { key: 'maxres', name: 'Maximum Resolution', class: 'img-fluid' },
                { key: 'sd', name: 'Standard Definition', class: 'img-fluid' },
                { key: 'hq', name: 'High Quality', class: 'img-fluid' },
                { key: 'mq', name: 'Medium Quality', class: 'img-fluid' },
                { key: 'default', name: 'Default Quality', class: 'img-fluid' }
            ];
            
            thumbnailTypes.forEach(function(type) {
                if (thumbnails[type.key]) {
                    const thumbnailHtml = `
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100">
                                <img src="${thumbnails[type.key]}" class="card-img-top thumbnail-img ${type.class}" alt="${type.name}" loading="lazy">
                                <div class="card-body">
                                    <h5 class="card-title">${type.name}</h5>
                                    <a href="${thumbnails[type.key]}" download class="btn btn-primary w-100">
                                        <i class="bi bi-download"></i> Download
                                    </a>
                                </div>
                            </div>
                        </div>
                    `;
                    thumbnailGrid.append(thumbnailHtml);
                }
            });
            
            $('#resultsSection').show();
        }
    });
</script>
@endsection