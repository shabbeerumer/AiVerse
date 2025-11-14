@extends('layouts.app')

@section('title', 'Tag Downloader')

@section('content')
<div class="row justify-content-center">
    <div class="col-12">
        <div class="glass-card p-4 mb-4">
            <h2 class="mb-4 text-center">YouTube Tag Downloader</h2>
            
            <div class="row" id="alert-container"></div>
            
            <div class="loading-spinner text-center mb-4" id="loadingSpinner" style="display: none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Processing...</span>
                </div>
                <p class="mt-2">Fetching metadata, please wait...</p>
            </div>
            
            <form id="tagForm">
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
                        <i class="bi bi-tags-fill"></i> Fetch Tags
                    </button>
                </div>
            </form>
        </div>
        
        <div class="glass-card p-4" id="resultsSection" style="display: none;">
            <h3 class="mb-4 text-center">Video Metadata</h3>
            
            <div class="row">
                <div class="col-md-8 mx-auto">
                    <div class="card">
                        <div class="card-body">
                            <h4 id="videoTitle" class="card-title"></h4>
                            <p id="videoDescription" class="card-text"></p>
                            
                            <div class="mt-4">
                                <h5>Statistics</h5>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>Views:</span>
                                        <span id="viewCount" class="fw-bold"></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>Likes:</span>
                                        <span id="likeCount" class="fw-bold"></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>Comments:</span>
                                        <span id="commentCount" class="fw-bold"></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>Published:</span>
                                        <span id="publishedAt" class="fw-bold"></span>
                                    </li>
                                </ul>
                            </div>
                            
                            <div class="mt-4">
                                <h5>Tags</h5>
                                <div id="tagsContainer" class="d-flex flex-wrap gap-2">
                                    <!-- Tags will be displayed here -->
                                </div>
                            </div>
                        </div>
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
        $('#tagForm').on('submit', function(e) {
            e.preventDefault();
            fetchTags();
        });
        
        // Fetch tags function
        function fetchTags() {
            const url = $('#url').val().trim();
            
            if (!url) {
                showAlert('Please enter a YouTube URL', 'danger');
                return;
            }
            
            // Show loading spinner
            $('#loadingSpinner').show();
            $('#fetchBtn').prop('disabled', true);
            
            $.ajax({
                url: '{{ route("ai.tag-downloader.fetch") }}',
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    url: url
                },
                success: function(response) {
                    $('#loadingSpinner').hide();
                    $('#fetchBtn').prop('disabled', false);
                    
                    if (response.success) {
                        displayMetadata(response.metadata);
                        showAlert('Metadata fetched successfully!', 'success');
                    } else {
                        showAlert(response.message, 'danger');
                    }
                },
                error: function(xhr) {
                    $('#loadingSpinner').hide();
                    $('#fetchBtn').prop('disabled', false);
                    
                    let message = 'Error fetching metadata';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    showAlert(message, 'danger');
                }
            });
        }
        
        // Display metadata
        function displayMetadata(metadata) {
            $('#videoTitle').text(metadata.title);
            $('#videoDescription').text(metadata.description);
            $('#viewCount').text(Number(metadata.viewCount).toLocaleString());
            $('#likeCount').text(Number(metadata.likeCount).toLocaleString());
            $('#commentCount').text(Number(metadata.commentCount).toLocaleString());
            $('#publishedAt').text(new Date(metadata.publishedAt).toLocaleDateString());
            
            // Display tags
            const tagsContainer = $('#tagsContainer');
            tagsContainer.empty();
            
            if (metadata.tags && metadata.tags.length > 0) {
                metadata.tags.forEach(function(tag) {
                    const tagHtml = `<span class="badge bg-primary">${tag}</span>`;
                    tagsContainer.append(tagHtml);
                });
            } else {
                tagsContainer.append('<span class="text-muted">No tags available</span>');
            }
            
            $('#resultsSection').show();
        }
    });
</script>
@endsection