@extends('layouts.app')

@section('title', 'Bulk Image Generator')

@section('content')
<div class="row justify-content-center">
    <div class="col-12">
        <div class="glass-card p-4 mb-4">
            <h2 class="mb-4 text-center">Bulk Image Generator</h2>
            
            <div class="row" id="alert-container"></div>
            
            <div class="loading-spinner text-center mb-4" id="loadingSpinner" style="display: none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Processing...</span>
                </div>
                <p class="mt-2">Generating images, please wait...</p>
                <div class="progress mt-3" style="height: 20px; display: none;" id="progressBar">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                         role="progressbar" style="width: 0%"></div>
                </div>
            </div>
            
            <form id="imageGeneratorForm">
                @csrf
                <div class="mb-3">
                    <label for="prompts" class="form-label">Enter prompts (comma-separated) or upload CSV:</label>
                    <textarea class="form-control" id="prompts" name="prompts" rows="4" 
                        placeholder="A beautiful sunset, A cute cat, A futuristic cityscape"></textarea>
                    <div class="form-text">
                        Enter prompts separated by commas, or upload a CSV file below. 
                        <button type="button" class="btn btn-sm btn-outline-primary" id="showSamples">
                            Show sample prompts
                        </button>
                    </div>
                </div>
                
                <!-- Sample prompts (hidden by default) -->
                <div class="mb-3" id="samplePrompts" style="display: none;">
                    <div class="card">
                        <div class="card-body">
                            <h5>Sample Prompts:</h5>
                            <ul>
                                <li>A majestic lion in the savannah at sunset</li>
                                <li>A futuristic cyberpunk city with neon lights</li>
                                <li>A cozy cabin in a snowy forest</li>
                                <li>A vibrant underwater coral reef with colorful fish</li>
                                <li>A steampunk airship flying over mountains</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="csvFile" class="form-label">Or upload CSV file:</label>
                    <input class="form-control" type="file" id="csvFile" name="csv_file" accept=".csv,text/csv">
                    <div class="form-text">CSV file should contain one prompt per line</div>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg" id="generateBtn">
                        <i class="bi bi-stars"></i> Generate Images
                    </button>
                </div>
            </form>
        </div>
        
        <div class="glass-card p-4" id="resultsSection" style="display: none;">
            <h3 class="mb-4 text-center">Generated Images</h3>
            
            <div class="d-flex justify-content-end mb-3">
                <button id="downloadAllBtn" class="btn btn-success">
                    <i class="bi bi-download"></i> Download All as ZIP
                </button>
            </div>
            
            <div id="imageGrid" class="row g-3">
                <!-- Images will be displayed here -->
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        // Handle form submission
        $('#imageGeneratorForm').on('submit', function(e) {
            e.preventDefault();
            generateImages();
        });
        
        // Handle CSV file upload
        $('#csvFile').on('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const csv = e.target.result;
                    // Parse CSV content
                    const lines = csv.split('\n');
                    const prompts = [];
                    lines.forEach(function(line) {
                        line = line.trim();
                        if (line) {
                            // Handle CSV with commas (split by comma but respect quoted strings)
                            const items = line.split(',').map(item => item.trim());
                            prompts.push(...items);
                        }
                    });
                    const promptsText = prompts.filter(p => p.trim() !== '').join(', ');
                    $('#prompts').val(promptsText);
                };
                reader.readAsText(file);
            }
        });
        
        // Handle download all button
        $('#downloadAllBtn').on('click', function() {
            downloadAllImages();
        });
        
        // Show sample prompts
        $('#showSamples').on('click', function() {
            $('#samplePrompts').toggle();
        });
        
        // Generate images function
        function generateImages() {
            const prompts = $('#prompts').val().trim();
            
            if (!prompts) {
                showAlert('Please enter at least one prompt', 'danger');
                return;
            }
            
            // Convert prompts string to array
            let promptsArray;
            if (prompts.includes(',')) {
                // Split by comma for multiple prompts
                promptsArray = prompts.split(',').map(prompt => prompt.trim()).filter(prompt => prompt.length > 0);
            } else {
                // Single prompt
                promptsArray = [prompts];
            }
            
            // Show loading spinner and progress bar
            $('#loadingSpinner').show();
            $('#progressBar').show();
            $('#generateBtn').prop('disabled', true);
            
            // Reset progress bar
            $('.progress-bar').css('width', '0%');
            
            $.ajax({
                url: '{{ route("ai.image-generator.generate") }}',
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    prompts: promptsArray
                },
                success: function(response) {
                    hideLoading();
                    $('#generateBtn').prop('disabled', false);
                    
                    if (response.success) {
                        displayImages(response.images);
                        showAlert('Images generated successfully!', 'success');
                        
                        // Show warnings if any
                        if (response.warnings) {
                            response.warnings.forEach(function(warning) {
                                showAlert(warning, 'warning');
                            });
                        }
                    } else {
                        showAlert(response.message, 'danger');
                    }
                },
                error: function(xhr) {
                    hideLoading();
                    $('#generateBtn').prop('disabled', false);
                    
                    let message = 'Error generating images';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    showAlert(message, 'danger');
                }
            });
        }
        
        // Display images in grid
        function displayImages(images) {
            const imageGrid = $('#imageGrid');
            imageGrid.empty();
            
            if (images.length > 0) {
                images.forEach(function(imageUrl) {
                    const imageHtml = `
                        <div class="col-md-4 col-lg-3">
                            <div class="card h-100">
                                <img src="${imageUrl}" class="card-img-top image-preview lazy" alt="Generated Image" loading="lazy">
                                <div class="card-body d-flex align-items-end">
                                    <a href="${imageUrl}" download class="btn btn-primary w-100">
                                        <i class="bi bi-download"></i> Download
                                    </a>
                                </div>
                            </div>
                        </div>
                    `;
                    imageGrid.append(imageHtml);
                });
                
                $('#resultsSection').show();
            } else {
                showAlert('No images were generated', 'warning');
            }
        }
        
        // Download all images
        function downloadAllImages() {
            const imageUrls = [];
            $('#imageGrid .col-md-4').each(function() {
                const imageUrl = $(this).find('img').attr('src');
                if (imageUrl) {
                    imageUrls.push(imageUrl);
                }
            });
            
            if (imageUrls.length === 0) {
                showAlert('No images to download', 'warning');
                return;
            }
            
            // Show loading spinner
            $('#loadingSpinner').show();
            $('#downloadAllBtn').prop('disabled', true);
            
            $.ajax({
                url: '{{ route("ai.image-generator.download-all") }}',
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    images: imageUrls
                },
                success: function(response) {
                    hideLoading();
                    $('#downloadAllBtn').prop('disabled', false);
                    
                    if (response.success && response.zip_url) {
                        // Create download link
                        const a = document.createElement('a');
                        a.href = response.zip_url;
                        a.download = 'ai_images.zip';
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                        
                        showAlert('Download started!', 'success');
                    } else {
                        showAlert(response.message || 'Error creating ZIP file', 'danger');
                    }
                },
                error: function(xhr) {
                    hideLoading();
                    $('#downloadAllBtn').prop('disabled', false);
                    
                    let message = 'Error downloading images';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    showAlert(message, 'danger');
                }
            });
        }
        
        // Hide loading spinner
        function hideLoading() {
            $('#loadingSpinner').hide();
            $('#progressBar').hide();
        }
    });
</script>
@endsection