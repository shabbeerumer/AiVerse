<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'AiVerse') }} - @yield('title')</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.15);
            --glass-border: rgba(255, 255, 255, 0.18);
            --glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            --dark-glass-bg: rgba(30, 30, 30, 0.25);
            --dark-glass-border: rgba(255, 255, 255, 0.1);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding-bottom: 2rem;
        }

        .dark-mode body {
            background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
        }

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            box-shadow: var(--glass-shadow);
            border-radius: 15px;
        }

        .dark-mode .glass-card {
            background: var(--dark-glass-bg);
            border: 1px solid var(--dark-glass-border);
        }

        .navbar {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            box-shadow: var(--glass-shadow);
        }

        .dark-mode .navbar {
            background: var(--dark-glass-bg);
            border: 1px solid var(--dark-glass-border);
        }

        .tool-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .tool-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .dark-mode .tool-card:hover {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
        }

        .loading-spinner {
            display: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }

        .dark-mode .btn-primary {
            background: linear-gradient(135deg, #0f2027 0%, #2c5364 100%);
        }

        .footer {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
        }

        .dark-mode .footer {
            background: var(--dark-glass-bg);
            border: 1px solid var(--dark-glass-border);
        }

        .theme-toggle {
            cursor: pointer;
        }

        .image-preview {
            max-height: 300px;
            object-fit: contain;
        }

        .thumbnail-img {
            transition: transform 0.3s ease;
        }

        .thumbnail-img:hover {
            transform: scale(1.05);
        }

        /* Lazy loading styles */
        .lazy {
            opacity: 0;
            transition: opacity 0.3s;
        }

        .lazy.loaded {
            opacity: 1;
        }

        .lazy:not(.loaded) {
            background-color: #f0f0f0;
        }
    </style>

    @yield('styles')
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="{{ url('/') }}">
                {{ config('app.name', 'AiVerse') }}
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('ai.image-generator') }}">Image Generator</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('ai.bg-remover') }}">Background Remover</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('ai.enhancer') }}">Image Enhancer</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('ai.voice-clone') }}">Voice Clone</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('ai.thumbnail-downloader') }}">Thumbnail Downloader</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('ai.tag-downloader') }}">Tag Downloader</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('ai.audio-downloader') }}">Audio Downloader</a>
                    </li>
                </ul>
                
                <div class="d-flex align-items-center">
                    <div class="theme-toggle me-3" id="themeToggle">
                        <i class="bi bi-moon-fill"></i>
                    </div>
                    <a href="https://github.com" target="_blank" class="text-decoration-none">
                        <i class="bi bi-github fs-4"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <main class="container mt-4">
        @yield('content')
    </main>
    
    <!-- Footer -->
    <footer class="footer mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>{{ config('app.name', 'AiVerse') }}</h5>
                    <p class="mb-0">AI Tools powered by open-source models and free APIs.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">&copy; {{ date('Y') }} {{ config('app.name', 'AiVerse') }}. All rights reserved.</p>
                    <div class="mt-2">
                        <a href="#" class="text-decoration-none me-3">Privacy Policy</a>
                        <a href="#" class="text-decoration-none">Terms of Service</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Lazy loading script -->
    <script>
        // Lazy loading for images
        document.addEventListener('DOMContentLoaded', function() {
            const lazyImages = [].slice.call(document.querySelectorAll('img.lazy'));
            
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver(function(entries, observer) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            const lazyImage = entry.target;
                            lazyImage.src = lazyImage.dataset.src;
                            lazyImage.classList.add('loaded');
                            imageObserver.unobserve(lazyImage);
                        }
                    });
                });
                
                lazyImages.forEach(function(lazyImage) {
                    imageObserver.observe(lazyImage);
                });
            } else {
                // Fallback for browsers that don't support IntersectionObserver
                lazyImages.forEach(function(lazyImage) {
                    lazyImage.src = lazyImage.dataset.src;
                    lazyImage.classList.add('loaded');
                });
            }
        });
    </script>
    
    <!-- Custom JS -->
    <script>
        // Theme toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const themeToggle = document.getElementById('themeToggle');
            const currentTheme = localStorage.getItem('theme') || 'light';
            
            if (currentTheme === 'dark') {
                document.body.classList.add('dark-mode');
                themeToggle.innerHTML = '<i class="bi bi-sun-fill"></i>';
            } else {
                themeToggle.innerHTML = '<i class="bi bi-moon-fill"></i>';
            }
            
            themeToggle.addEventListener('click', function() {
                document.body.classList.toggle('dark-mode');
                
                if (document.body.classList.contains('dark-mode')) {
                    localStorage.setItem('theme', 'dark');
                    themeToggle.innerHTML = '<i class="bi bi-sun-fill"></i>';
                } else {
                    localStorage.setItem('theme', 'light');
                    themeToggle.innerHTML = '<i class="bi bi-moon-fill"></i>';
                }
            });
        });
        
        // Show loading spinner
        function showLoading() {
            $('.loading-spinner').show();
        }
        
        // Hide loading spinner
        function hideLoading() {
            $('.loading-spinner').hide();
        }
        
        // Show alert message
        function showAlert(message, type = 'success') {
            const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            `;
            $('#alert-container').append(alertHtml);
        }
    </script>
    
    @yield('scripts')
</body>
</html>