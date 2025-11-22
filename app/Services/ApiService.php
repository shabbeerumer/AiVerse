<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiService
{
    /**
     * Make a request to Hugging Face API with proper error handling
     *
     * @param string $model
     * @param array $data
     * @param array $options
     * @return array
     */
    public static function huggingFaceRequest($model, $data = [], $options = [])
    {
        // Increase script execution time
        ini_set('max_execution_time', 300);
        set_time_limit(300);
        
        try {
            // Try to get token from env, fallback to direct .env parsing if not found
            $token = env('HUGGINGFACE_API_TOKEN');
            
            if (!$token) {
                // Fallback to direct .env parsing
                $token = self::getEnvValue('HUGGINGFACE_API_TOKEN');
            }
            
            if (!$token) {
                return [
                    'success' => false,
                    'error' => 'Hugging Face API token not configured',
                    'retryable' => false
                ];
            }

            $defaultOptions = [
                'timeout' => 300,        // Increased from 120 to 300 seconds
                'connect_timeout' => 60, // Added connect timeout
                'retry' => 3,
                'retry_delay' => 1000
            ];
            
            $options = array_merge($defaultOptions, $options);
            
            // Use the NEW router endpoint
            $url = "https://router.huggingface.co/hf-inference/models/{$model}";
            
            // Special handling for models that require specific data format
            // Only process base64 encoding for non-image generation models
            if ($model === 'briaai/RMBG-1.4' || $model === 'caidas/swin2SR-classical-sr-x2-64' || 
                $model === 'zhaozijie3132/BiRefNet' || $model === 'nightmareai/real-esrgan' ||
                $model === 'facebook/fastspeech2-en-ljspeech' || $model === 'ZhengPeng7/BiRefNet' ||
                $model === 'skytnt/anime-seg') {
                // These models expect base64 encoded inputs for image models
                if ((strpos($model, 'RMBG') !== false || strpos($model, 'esrgan') !== false || 
                     strpos($model, 'BiRefNet') !== false || strpos($model, 'anime-seg') !== false) && 
                    isset($data['inputs']) && !is_string($data['inputs'])) {
                    $data['inputs'] = base64_encode($data['inputs']);
                }
            }
            // For image generation models, we don't modify the data - send as is
            
            // Log the request for debugging
            Log::info("Hugging Face API Request to: {$url}", [
                'model' => $model,
                'data_keys' => array_keys($data),
                'data_sample' => array_slice($data, 0, 2) // First 2 elements only
            ]);
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])
                ->timeout($options['timeout'])
                ->connectTimeout($options['connect_timeout']) // Added connect timeout
                ->post($url, $data);
            
            // Log response info
            Log::info("Hugging Face API Response", [
                'model' => $model,
                'status' => $response->status(),
                'content_type' => $response->header('Content-Type'),
                'content_length' => strlen($response->body())
            ]);
            
            // Handle rate limiting
            if ($response->status() == 429) {
                return [
                    'success' => false,
                    'error' => 'API rate limit exceeded. Please try again in a few minutes.',
                    'retryable' => true,
                    'retry_after' => $response->header('Retry-After', 60)
                ];
            }
            
            // Handle model loading
            if ($response->status() == 503) {
                $responseData = $response->json();
                return [
                    'success' => false,
                    'error' => 'Model is currently loading. Please try again in a moment.',
                    'retryable' => true,
                    'estimated_time' => $responseData['estimated_time'] ?? 30
                ];
            }
            
            // Handle model warmup
            if ($response->status() == 500) {
                $responseData = $response->json();
                if (isset($responseData['error']) && strpos($responseData['error'], 'is currently loading') !== false) {
                    return [
                        'success' => false,
                        'error' => 'Model is currently loading. Please try again in a moment.',
                        'retryable' => true,
                        'estimated_time' => 30
                    ];
                }
            }
            
            // Handle 410 Gone errors
            if ($response->status() == 410) {
                return [
                    'success' => false,
                    'error' => 'Model endpoint is no longer available. Please check the model name.',
                    'retryable' => false
                ];
            }
            
            // Handle payment required errors
            if ($response->status() == 402) {
                return [
                    'success' => false,
                    'error' => 'Payment required for this model. We are trying alternative free models.',
                    'retryable' => false
                ];
            }
            
            if ($response->successful()) {
                // Check content type to determine if it's an image
                $contentType = $response->header('Content-Type', '');
                $isImage = strpos($contentType, 'image/') !== false;
                
                // Get response body
                $body = $response->body();
                
                Log::info("Response analysis", [
                    'is_image' => $isImage,
                    'content_type' => $contentType,
                    'content_length' => strlen($body),
                    'first_100_bytes' => substr($body, 0, 100)
                ]);
                
                // Additional check for SVG or abstract content
                if ($isImage || strpos($contentType, 'application/octet-stream') !== false) {
                    // Check if it's actually SVG or abstract content
                    if (self::isAbstractContent($body)) {
                        return [
                            'success' => false,
                            'error' => 'Model returned abstract content (SVG/shapes) instead of real image',
                            'retryable' => false
                        ];
                    }
                }
                
                return [
                    'success' => true,
                    'data' => $body,
                    'content_type' => $contentType,
                    'is_image' => $isImage
                ];
            }
            
            return [
                'success' => false,
                'error' => 'API request failed: ' . $response->reason(),
                'status' => $response->status(),
                'retryable' => $response->status() >= 500,
                'response_body' => $response->body()
            ];
        } catch (\Exception $e) {
            Log::error('Hugging Face API request failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Network error: ' . $e->getMessage(),
                'retryable' => true
            ];
        }
    }
    
    /**
     * Check if the response contains abstract content like SVG or geometric shapes
     */
    private static function isAbstractContent($data)
    {
        // Check if data is empty
        if (empty($data)) {
            return true;
        }
        
        // Check if it's very small (likely not a real image)
        if (strlen($data) < 100) {
            return true;
        }
        
        // Check the first few bytes to determine file type
        $header = substr($data, 0, 10);
        
        // If it starts with PNG or JPEG signature, it's likely a real image
        if (substr($header, 0, 8) === "\x89PNG\x0D\x0A\x1A\x0A" || 
            substr($header, 0, 2) === "\xFF\xD8") {
            return false;
        }
        
        // Check if it's SVG or contains SVG-like content (abstract shapes)
        if (stripos($data, '<svg') !== false || stripos($data, '<path') !== false || 
            stripos($data, '<rect') !== false || stripos($data, '<circle') !== false) {
            return true;
        }
        
        // Check if it's XML (likely SVG)
        if (stripos($data, '<?xml') !== false) {
            return true;
        }
        
        // Check if it contains common abstract pattern indicators
        if (preg_match('/<pattern|<mask|<linearGradient|<radialGradient/i', $data)) {
            return true;
        }
        
        // If we can't determine it's a real image, assume it might be abstract
        // Real images are typically much larger than abstract shapes
        return strlen($data) < 1000;
    }

    /**
     * Make a request to YouTube Data API with proper error handling
     *
     * @param string $endpoint
     * @param array $params
     * @return array
     */
    public static function youtubeRequest($endpoint, $params = [])
    {
        try {
            $apiKey = env('YOUTUBE_API_KEY');
            
            if (!$apiKey) {
                // Fallback to direct .env parsing
                $apiKey = self::getEnvValue('YOUTUBE_API_KEY');
            }
            
            if (!$apiKey) {
                return [
                    'success' => false,
                    'error' => 'YouTube API key not configured',
                    'retryable' => false
                ];
            }

            $defaultParams = [
                'key' => $apiKey
            ];
            
            $params = array_merge($defaultParams, $params);
            
            $url = "https://www.googleapis.com/youtube/v3/{$endpoint}";
            
            $response = Http::timeout(30)->connectTimeout(10)->get($url, $params);
            
            // Handle quota exceeded
            if ($response->status() == 403) {
                $responseData = $response->json();
                if (isset($responseData['error']['message']) && 
                    strpos($responseData['error']['message'], 'quota') !== false) {
                    return [
                        'success' => false,
                        'error' => 'YouTube API quota exceeded. Please try again later.',
                        'retryable' => false
                    ];
                }
            }
            
            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }
            
            return [
                'success' => false,
                'error' => 'YouTube API request failed: ' . $response->reason(),
                'status' => $response->status(),
                'retryable' => $response->status() >= 500
            ];
        } catch (\Exception $e) {
            Log::error('YouTube API request failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Network error: ' . $e->getMessage(),
                'retryable' => true
            ];
        }
    }

    /**
     * Execute yt-dlp command with proper error handling and sanitization
     *
     * @param string $url
     * @param array $options
     * @return array
     */
    public static function executeYtdlp($url, $options = [])
    {
        // Increase script execution time
        ini_set('max_execution_time', 900);
        set_time_limit(900);
        
        try {
            // Sanitize URL
            $url = filter_var($url, FILTER_SANITIZE_URL);
            
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return [
                    'success' => false,
                    'error' => 'Invalid URL provided',
                    'retryable' => false
                ];
            }

            // Get yt-dlp path with fallbacks
            $ytDlpPath = env('YT_DLP_PATH');
            
            // If not found in env(), try direct .env parsing
            if (!$ytDlpPath) {
                $ytDlpPath = self::getEnvValue('YT_DLP_PATH');
            }
            
            // If still not found, use default
            if (!$ytDlpPath) {
                $ytDlpPath = self::getDefaultYtdlpPath();
            }
            
            // Additional check for Windows paths with quotes
            if (strpos($ytDlpPath, '"') === 0 && strrpos($ytDlpPath, '"') === strlen($ytDlpPath) - 1) {
                $ytDlpPath = trim($ytDlpPath, '"');
            }
            
            // Validate that yt-dlp exists
            if (!self::isCommandAvailable($ytDlpPath)) {
                // Try alternative paths including system PATH
                $alternativePaths = [
                    'yt-dlp.exe',
                    'yt-dlp',
                    'C:/Users/usama/AppData/Roaming/Python/Python310/Scripts/yt-dlp.exe',
                    'C:\\Users\\usama\\AppData\\Roaming\\Python\\Python310\\Scripts\\yt-dlp.exe',
                    '/usr/bin/yt-dlp',
                    '/usr/local/bin/yt-dlp'
                ];
                
                $found = false;
                foreach ($alternativePaths as $path) {
                    if (self::isCommandAvailable($path)) {
                        $ytDlpPath = $path;
                        $found = true;
                        break;
                    }
                }
                
                // If still not found, try to find it in the system PATH
                if (!$found) {
                    $systemPath = self::findCommandInPath('yt-dlp.exe');
                    if ($systemPath && self::isCommandAvailable($systemPath)) {
                        $ytDlpPath = $systemPath;
                        $found = true;
                    }
                }
                
                if (!$found) {
                    return [
                        'success' => false,
                        'error' => 'yt-dlp not found. Please install yt-dlp and configure YT_DLP_PATH in .env. Tried paths: ' . implode(', ', $alternativePaths),
                        'retryable' => false
                    ];
                }
            }

            $defaultOptions = [
                'format' => 'bestaudio',
                'extract_audio' => true,
                'audio_format' => 'mp3',
                'audio_quality' => 0,
                'timeout' => 900 // Increased from 300 to 900 seconds (15 minutes)
            ];
            
            $options = array_merge($defaultOptions, $options);
            
            // Create a temporary file base name for the audio (don't create the file yet)
            $tempDir = sys_get_temp_dir();
            $tempBaseName = $tempDir . DIRECTORY_SEPARATOR . 'yt_audio_' . uniqid();
            
            // Debug: Log the temp base name
            Log::info('yt-dlp temp base name: ' . $tempBaseName);
            
            // Build command
            $command = "\"" . str_replace('"', '', $ytDlpPath) . "\"";
            
            if ($options['extract_audio']) {
                $command .= " --extract-audio";
            }
            
            if (!empty($options['audio_format'])) {
                $command .= " --audio-format " . escapeshellarg($options['audio_format']);
            }
            
            if (!empty($options['audio_quality'])) {
                $command .= " --audio-quality " . escapeshellarg($options['audio_quality']);
            }
            
            if (!empty($options['format'])) {
                $command .= " -f " . escapeshellarg($options['format']);
            }
            
            // Use the base name for the output pattern
            $command .= " -o " . escapeshellarg($tempBaseName . '.%(ext)s');
            $command .= " " . escapeshellarg($url);
            $command .= " 2>&1";
            
            // Debug: Log the command
            Log::info('yt-dlp command: ' . $command);
            
            // Execute command with timeout
            $descriptorspec = [
                0 => ["pipe", "r"],
                1 => ["pipe", "w"],
                2 => ["pipe", "w"]
            ];
            
            $process = proc_open($command, $descriptorspec, $pipes);
            
            if (!is_resource($process)) {
                return [
                    'success' => false,
                    'error' => 'Failed to start yt-dlp process with command: ' . $command,
                    'retryable' => false
                ];
            }
            
            // Set timeout
            $startTime = time();
            $timeout = $options['timeout'];
            
            // Wait for process to complete or timeout
            while (true) {
                $status = proc_get_status($process);
                
                if (!$status['running']) {
                    break;
                }
                
                if (time() - $startTime > $timeout) {
                    proc_terminate($process);
                    return [
                        'success' => false,
                        'error' => 'yt-dlp process timed out after ' . $timeout . ' seconds',
                        'retryable' => true
                    ];
                }
                
                usleep(100000); // 0.1 second
            }
            
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            
            fclose($pipes[1]);
            fclose($pipes[2]);
            
            $exitCode = proc_close($process);
            
            // Debug: Log the output and errors
            Log::info('yt-dlp output: ' . $output);
            Log::info('yt-dlp errors: ' . $errors);
            Log::info('yt-dlp exit code: ' . $exitCode);
            
            if ($exitCode !== 0) {
                Log::error('yt-dlp command failed: ' . $errors . ' Command: ' . $command);
                
                return [
                    'success' => false,
                    'error' => 'yt-dlp failed: ' . trim($errors),
                    'retryable' => true
                ];
            }
            
            // Find the actual output file (yt-dlp adds extension)
            $outputFile = null;
            $extensions = ['mp3', 'wav', 'm4a', 'flac', 'opus', 'aac', 'ogg'];
            
            // Debug: Check what files were created
            Log::info('Looking for output files with base name: ' . $tempBaseName);
            
            // First, check if the exact temp file exists (no extension added)
            if (file_exists($tempBaseName)) {
                Log::info('Found temp file without extension: ' . $tempBaseName);
                $outputFile = $tempBaseName;
            }
            
            // If not found, check with extensions
            if (!$outputFile) {
                foreach ($extensions as $ext) {
                    $potentialFile = $tempBaseName . '.' . $ext;
                    if (file_exists($potentialFile)) {
                        Log::info('Found output file with extension ' . $ext . ': ' . $potentialFile);
                        $outputFile = $potentialFile;
                        break;
                    }
                }
            }
            
            // If still not found, do a more comprehensive search
            if (!$outputFile) {
                Log::info('Doing comprehensive search for files matching pattern: ' . $tempBaseName . '.*');
                $files = glob($tempBaseName . '.*');
                if (!empty($files)) {
                    $outputFile = $files[0];
                    Log::info('Found output file through glob: ' . $outputFile);
                }
            }
            
            if (!$outputFile || !file_exists($outputFile)) {
                // Debug: List all files in temp directory that might be related
                Log::info('Audio file not found. Checking temp directory contents:');
                $allFiles = scandir($tempDir);
                $ytFiles = array_filter($allFiles, function($file) use ($tempBaseName) {
                    return strpos($file, basename($tempBaseName)) !== false;
                });
                Log::info('Files matching temp base name in temp directory: ' . implode(', ', $ytFiles));
                
                return [
                    'success' => false,
                    'error' => 'Audio file not found after download. Exit code: ' . $exitCode . '. Output: ' . trim($output) . '. Errors: ' . trim($errors),
                    'retryable' => true
                ];
            }
            
            $audioData = file_get_contents($outputFile);
            
            // Debug: Log file size
            Log::info('Audio file size: ' . strlen($audioData) . ' bytes');
            
            // Clean up temporary files
            if (file_exists($tempBaseName)) {
                unlink($tempBaseName);
            }
            
            if (file_exists($outputFile)) {
                unlink($outputFile);
            }
            
            return [
                'success' => true,
                'data' => $audioData
            ];
        } catch (\Exception $e) {
            Log::error('yt-dlp execution failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'yt-dlp execution failed: ' . $e->getMessage(),
                'retryable' => true
            ];
        }
    }

    /**
     * Get environment variable value by parsing .env file directly
     *
     * @param string $key
     * @return string|null
     */
    public static function getEnvValue($key)
    {
        // Use Laravel's base_path if available, otherwise use a fallback
        $basePath = defined('LARAVEL_START') ? base_path() : __DIR__ . '/../../..';
        $envFile = $basePath . '/.env';
        
        if (!file_exists($envFile)) {
            // Try alternative path
            $envFile = '.env';
            if (!file_exists($envFile)) {
                return null;
            }
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Skip comments
            if (strpos($line, '#') === 0) {
                continue;
            }
            
            // Check if line contains the key
            if (strpos($line, $key . '=') === 0) {
                $parts = explode('=', $line, 2);
                if (isset($parts[1])) {
                    // Remove quotes if present
                    return trim($parts[1], '"\'');
                }
            }
        }
        
        return null;
    }

    /**
     * Check if a command is available
     *
     * @param string $command
     * @return bool
     */
    public static function isCommandAvailable($command)
    {
        try {
            // Clean the command path
            $command = trim($command, '"\'');
            
            // Check if file exists as a full path
            if (file_exists($command)) {
                // If it's a full path, just test if it's executable
                $descriptorspec = [
                    0 => ["pipe", "r"],
                    1 => ["pipe", "w"],
                    2 => ["pipe", "w"]
                ];
                
                // For Windows, we need to handle the command properly
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    // On Windows, we need to use cmd to execute the command
                    $testCommand = 'cmd /c "' . $command . '" --version';
                } else {
                    $testCommand = $command . ' --version';
                }
                
                $process = @proc_open($testCommand, $descriptorspec, $pipes);
                
                if (is_resource($process)) {
                    fclose($pipes[0]);
                    $output = stream_get_contents($pipes[1]);
                    $errors = stream_get_contents($pipes[2]);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    $exitCode = proc_close($process);
                    return $exitCode === 0;
                }
                
                return false;
            }
            
            // If it's just a command name (no path separators), check if it's in PATH
            if (strpos($command, '/') === false && strpos($command, '\\') === false) {
                // On Windows, check with .exe extension
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    $command .= '.exe';
                }
                
                $descriptorspec = [
                    0 => ["pipe", "r"],
                    1 => ["pipe", "w"],
                    2 => ["pipe", "w"]
                ];
                
                // For Windows, we need to handle the command properly
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    $testCommand = 'cmd /c ' . $command . ' --version';
                } else {
                    $testCommand = $command . ' --version';
                }
                
                $process = @proc_open($testCommand, $descriptorspec, $pipes);
                
                if (is_resource($process)) {
                    fclose($pipes[0]);
                    $output = stream_get_contents($pipes[1]);
                    $errors = stream_get_contents($pipes[2]);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    $exitCode = proc_close($process);
                    return $exitCode === 0;
                }
                
                return false;
            }
            
            // If it contains path separators but file doesn't exist
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Find a command in the system PATH
     *
     * @param string $command
     * @return string|null
     */
    public static function findCommandInPath($command)
    {
        $paths = explode(PATH_SEPARATOR, getenv('PATH'));
        foreach ($paths as $path) {
            $fullPath = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $command;
            if (file_exists($fullPath) && is_executable($fullPath)) {
                return $fullPath;
            }
        }
        return null;
    }
    
    /**
     * Get default yt-dlp path based on OS
     *
     * @return string
     */
    private static function getDefaultYtdlpPath()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows
            return 'yt-dlp.exe';
        } else {
            // Unix-like systems
            return '/usr/bin/yt-dlp';
        }
    }
}