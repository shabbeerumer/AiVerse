<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\ToolLog;
use App\Services\ApiService;
use App\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class ImageGenController extends Controller
{
    public function index()
    {
        return view('ai.image-generator');
    }

    public function generate(Request $request)
    {
        // Increase script execution time for heavy image generation tasks
        ini_set('max_execution_time', 600);
        ini_set('max_input_time', 600);
        ini_set('memory_limit', '1024M');
        set_time_limit(600);
        
        $request->validate([
            'prompts' => 'required|array|min:1|max:10',
            'prompts.*' => 'required|string|max:200',
        ]);

        try {
            // Parse prompts (support both comma-separated and CSV)
            $prompts = [];
            
            // Ensure we're working with a string
            $promptsInput = is_array($request->prompts) ? implode(', ', $request->prompts) : $request->prompts;
            
            if (strpos($promptsInput, ',') !== false) {
                // Comma-separated
                $prompts = array_map('trim', explode(',', $promptsInput));
            } else {
                // Single prompt or CSV content
                $prompts = FileService::parseCsvPrompts($promptsInput);
            }

            // Remove empty prompts
            $prompts = array_filter($prompts, function($prompt) {
                return !empty(trim($prompt));
            });

            if (empty($prompts)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid prompts provided'
                ], 400);
            }

            $images = [];
            $failedPrompts = [];

            // List of confirmed FREE models that generate REAL images
            $models = [
                'black-forest-labs/FLUX.1-schnell',
                'stabilityai/sd-turbo',
                'playgroundai/playground-v2.5'
            ];

            foreach ($prompts as $prompt) {
                if (!empty($prompt)) {
                    $promptFailed = false;
                    $lastError = '';
                    $modelUsed = '';
                    $skipReason = '';
                    
                    // Try each model in sequence until one works
                    foreach ($models as $model) {
                        // Use the correct format for image generation models
                        $parameters = [
                            'inputs' => $prompt,
                            'parameters' => [
                                'guidance_scale' => 3,
                                'num_inference_steps' => 4
                            ]
                        ];
                        
                        $result = ApiService::huggingFaceRequest(
                            $model,
                            $parameters,
                            ['timeout' => 600, 'connect_timeout' => 60]
                        );

                        if ($result['success']) {
                            // Check if the result is a valid image (not SVG, shapes, etc.)
                            if ($this->isValidImage($result['data'], $result['content_type'])) {
                                $imageUrl = FileService::saveFile($result['data'], 'ai_images', 'png');
                                if ($imageUrl) {
                                    $images[] = $imageUrl;
                                    $promptFailed = false;
                                    $modelUsed = $model;
                                    $skipReason = "Success";
                                    break; // Success, move to next prompt
                                } else {
                                    $lastError = 'Failed to save image';
                                    $promptFailed = true;
                                }
                            } else {
                                // Invalid image format (SVG, shapes, etc.)
                                $lastError = 'Model returned invalid image format (abstract shapes/SVG)';
                                $skipReason = "Invalid image format";
                                // Try next model
                                continue;
                            }
                        } else {
                            $lastError = $result['error'] ?? 'Unknown error';
                            $promptFailed = true;
                            
                            // Handle payment required errors (skip instantly)
                            if (isset($result['retryable']) && !$result['retryable'] && 
                                (strpos(strtolower($lastError), 'payment') !== false || 
                                 strpos(strtolower($lastError), '402') !== false)) {
                                $skipReason = "Payment required";
                                continue; // Skip to next model instantly
                            }
                            
                            // For other errors, check if it's retryable
                            if (isset($result['retryable']) && $result['retryable']) {
                                // Try one more time with the same model
                                $retryResult = ApiService::huggingFaceRequest(
                                    $model,
                                    $parameters,
                                    ['timeout' => 600, 'connect_timeout' => 60]
                                );
                                
                                if ($retryResult['success']) {
                                    // Check if the retry result is a valid image
                                    if ($this->isValidImage($retryResult['data'], $retryResult['content_type'])) {
                                        $imageUrl = FileService::saveFile($retryResult['data'], 'ai_images', 'png');
                                        if ($imageUrl) {
                                            $images[] = $imageUrl;
                                            $promptFailed = false;
                                            $modelUsed = $model;
                                            $skipReason = "Success on retry";
                                            break; // Success, move to next prompt
                                        } else {
                                            $lastError = 'Failed to save image';
                                            $promptFailed = true;
                                        }
                                    } else {
                                        $lastError = 'Model returned invalid image format (abstract shapes/SVG) on retry';
                                        // Try next model
                                        continue;
                                    }
                                }
                            }
                        }
                    }
                    
                    // Log model usage and skip reason
                    \Log::info("Image generation attempt for prompt: '{$prompt}'", [
                        'model_used' => $modelUsed,
                        'skip_reason' => $skipReason,
                        'last_error' => $lastError
                    ]);
                    
                    // If all models failed for this prompt
                    if ($promptFailed) {
                        $failedPrompts[] = $prompt . ' (' . $lastError . ')';
                    }
                }
            }

            // Log the tool usage
            ToolLog::create([
                'tool_name' => 'image_generator',
                'user_ip' => $request->ip(),
                'details' => json_encode([
                    'prompts_count' => count($prompts),
                    'success_count' => count($images),
                    'failed_count' => count($failedPrompts)
                ])
            ]);

            $response = [
                'success' => true,
                'images' => $images
            ];

            if (!empty($failedPrompts)) {
                $response['warnings'] = [
                    'Some prompts failed to generate',
                    'Failed prompts: ' . implode(', ', $failedPrompts)
                ];
            }

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate images: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if the returned data is a valid image (not SVG, shapes, patterns, masks, or color blobs)
     */
    private function isValidImage($data, $contentType)
    {
        // Check if data is empty
        if (empty($data)) {
            return false;
        }
        
        // Check content type for valid image types
        if ($contentType && (strpos($contentType, 'image/png') !== false || 
                            strpos($contentType, 'image/jpeg') !== false)) {
            // Check if it's very small (likely not a real image)
            if (strlen($data) < 100) {
                return false;
            }
            
            // Check the first few bytes to determine file type
            $header = substr($data, 0, 10);
            
            // PNG signature
            if (substr($header, 0, 8) === "\x89PNG\x0D\x0A\x1A\x0A") {
                return true;
            }
            
            // JPEG signature
            if (substr($header, 0, 2) === "\xFF\xD8") {
                return true;
            }
        }
        
        // Check if it's SVG or contains SVG-like content (abstract shapes)
        if (stripos($data, '<svg') !== false || stripos($data, '<path') !== false || 
            stripos($data, '<rect') !== false || stripos($data, '<circle') !== false) {
            return false;
        }
        
        // Check if it's XML (likely SVG)
        if (stripos($data, '<?xml') !== false) {
            return false;
        }
        
        // Check if it contains common abstract pattern indicators
        if (preg_match('/<pattern|<mask|<linearGradient|<radialGradient/i', $data)) {
            return false;
        }
        
        // If it has a valid image content type and reasonable size, accept it
        if ($contentType && (strpos($contentType, 'image/') !== false) && strlen($data) > 1000) {
            return true;
        }
        
        return false;
    }

    public function downloadAll(Request $request)
    {
        // Increase script execution time for ZIP creation
        ini_set('max_execution_time', 600);
        set_time_limit(600);
        
        $request->validate([
            'images' => 'required|array',
            'images.*' => 'required|string',
        ]);

        try {
            $zipUrl = FileService::createZipArchive($request->images, 'ai_images_' . time() . '.zip');

            if ($zipUrl) {
                // Log the tool usage
                ToolLog::create([
                    'tool_name' => 'image_generator_download',
                    'user_ip' => $request->ip(),
                    'details' => json_encode(['images_count' => count($request->images)])
                ]);

                return response()->json([
                    'success' => true,
                    'zip_url' => $zipUrl
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create ZIP archive'
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating ZIP archive: ' . $e->getMessage()
            ], 500);
        }
    }

    public function downloadZip(Request $request)
    {
        $request->validate([
            'zip_url' => 'required|string',
        ]);

        try {
            $zipUrl = $request->zip_url;
            $filename = basename(parse_url($zipUrl, PHP_URL_PATH));
            $filePath = storage_path('app/public/' . str_replace('/storage/', '', $zipUrl));

            if (!file_exists($filePath)) {
                return response()->json(['success' => false, 'message' => 'File not found'], 404);
            }

            return response()->download($filePath, $filename)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error downloading ZIP: ' . $e->getMessage()
            ], 500);
        }
    }
}