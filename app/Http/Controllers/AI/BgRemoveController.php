<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\ToolLog;
use App\Services\ApiService;
use App\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BgRemoveController extends Controller
{
    public function index()
    {
        return view('ai.bg-remover');
    }

    public function removeBackground(Request $request)
    {
        try {
            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240',
            ]);

            $file = $request->file('image');
            
            // Validate file size (10MB max)
            if ($file->getSize() > 10 * 1024 * 1024) {
                return response()->json([
                    'success' => false,
                    'message' => 'File size exceeds 10MB limit'
                ], 400);
            }

            // Read the image file
            $imageData = file_get_contents($file->getPathname());
            
            // List of free models to try in order
            // Updated with more models including some that might work better
            $models = [
                'Xenova/rmbg-1.4',      // Primary free model
                'briaai/RMBG-1.4',      // Alternative model
                'ZhengPeng7/BiRefNet',  // Another free alternative
                'skytnt/anime-seg',     // Additional free option
                'Xenova/isnet-general-use', // General use model
                'Xenova/u2net',         // U2-Net model
                'Xenova/silueta'        // Lightweight model
            ];
            
            $attemptedModels = [];
            $lastError = '';
            $paymentRequiredCount = 0;
            
            // Try each model in sequence until one works
            foreach ($models as $model) {
                $attemptedModels[] = $model;
                
                $result = ApiService::huggingFaceRequest(
                    $model,
                    ['inputs' => base64_encode($imageData)],
                    ['timeout' => 600, 'connect_timeout' => 60]
                );
                
                if ($result['success']) {
                    $resultUrl = FileService::saveFile($result['data'], 'bg_removed', 'png');
                    
                    if ($resultUrl) {
                        // Log the tool usage
                        ToolLog::create([
                            'tool_name' => 'background_remover',
                            'user_ip' => $request->ip(),
                        ]);

                        return response()->json([
                            'success' => true,
                            'result_url' => $resultUrl
                        ]);
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => 'Failed to save processed image'
                        ], 500);
                    }
                } else {
                    // Store the last error for reporting
                    $lastError = $result['error'] ?? 'Unknown error';
                    
                    // Count payment required errors
                    if (isset($result['retryable']) && !$result['retryable'] && 
                        strpos(strtolower($lastError), 'payment') !== false) {
                        $paymentRequiredCount++;
                        continue;
                    }
                    
                    // For other errors, check if it's retryable
                    if (isset($result['retryable']) && $result['retryable']) {
                        // Try one more time with the same model
                        $retryResult = ApiService::huggingFaceRequest(
                            $model,
                            ['inputs' => base64_encode($imageData)],
                            ['timeout' => 600, 'connect_timeout' => 60]
                        );
                        
                        if ($retryResult['success']) {
                            $resultUrl = FileService::saveFile($retryResult['data'], 'bg_removed', 'png');
                            
                            if ($resultUrl) {
                                // Log the tool usage
                                ToolLog::create([
                                    'tool_name' => 'background_remover',
                                    'user_ip' => $request->ip(),
                                ]);

                                return response()->json([
                                    'success' => true,
                                    'result_url' => $resultUrl
                                ]);
                            } else {
                                return response()->json([
                                    'success' => false,
                                    'message' => 'Failed to save processed image'
                                ], 500);
                            }
                        }
                    }
                }
            }
            
            // If all cloud models failed due to payment requirements, try local processing
            if ($paymentRequiredCount > 0 && $paymentRequiredCount == count($attemptedModels)) {
                return $this->processLocally($request, $imageData);
            }
            
            // If we've tried all models and none worked
            $modelList = implode(', ', $attemptedModels);
            $message = "Background removal failed after trying multiple free models ({$modelList}). ";
            
            // Check if all errors were payment-related
            if ($paymentRequiredCount > 0 && $paymentRequiredCount == count($attemptedModels)) {
                $message .= "All available models currently require payment or have usage limits. ";
                $message .= "Please try again later when free quota may be available, or check if you have a Hugging Face API token with appropriate permissions. ";
                $message .= "As an alternative, you might want to try installing the 'rembg' Python package locally which offers free background removal models.";
            } else {
                $message .= $lastError . ". Please try again later or use a different image.";
            }
            
            return response()->json([
                'success' => false,
                'message' => $message
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing image: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process image locally using rembg Python package
     */
    private function processLocally(Request $request, $imageData)
    {
        try {
            // Save the input image temporarily
            $tempFileName = 'temp_' . Str::random(20) . '.png';
            $tempFilePath = storage_path('app/' . $tempFileName);
            file_put_contents($tempFilePath, $imageData);
            
            // Define output path
            $outputFileName = 'bg_removed_' . Str::random(20) . '.png';
            $outputFilePath = storage_path('app/public/' . $outputFileName);
            
            // Execute Python script
            $command = "python " . base_path('rembg_processor.py') . " " . escapeshellarg($tempFilePath) . " " . escapeshellarg($outputFilePath);
            
            // Run the command and capture output
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);
            
            // Clean up temp file
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
            
            // Check if processing was successful
            if ($returnCode === 0 && file_exists($outputFilePath)) {
                // Log the tool usage
                ToolLog::create([
                    'tool_name' => 'background_remover_local',
                    'user_ip' => $request->ip(),
                ]);
                
                // Return the result URL
                $resultUrl = '/storage/' . $outputFileName;
                return response()->json([
                    'success' => true,
                    'result_url' => $resultUrl
                ]);
            } else {
                // Clean up output file if it exists
                if (file_exists($outputFilePath)) {
                    unlink($outputFilePath);
                }
                
                // Get error message from output
                $errorMessage = implode("\n", $output);
                if (empty($errorMessage)) {
                    $errorMessage = "Local processing failed. Please ensure Python and rembg are properly installed.";
                }
                
                return response()->json([
                    'success' => false,
                    'message' => 'Local background removal failed: ' . $errorMessage
                ], 500);
            }
        } catch (\Exception $e) {
            // Clean up temp files if they exist
            if (isset($tempFilePath) && file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
            if (isset($outputFilePath) && file_exists($outputFilePath)) {
                unlink($outputFilePath);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Error in local processing: ' . $e->getMessage()
            ], 500);
        }
    }

    public function download(Request $request)
    {
        $request->validate([
            'image_url' => 'required|string',
        ]);

        try {
            $imageUrl = $request->image_url;
            $filename = basename(parse_url($imageUrl, PHP_URL_PATH));
            $filePath = storage_path('app/public/' . str_replace('/storage/', '', $imageUrl));

            if (!file_exists($filePath)) {
                return response()->json(['success' => false, 'message' => 'File not found'], 404);
            }

            // Log the tool usage
            ToolLog::create([
                'tool_name' => 'background_remover_download',
                'user_ip' => $request->ip(),
            ]);

            return response()->download($filePath, $filename)->deleteFileAfterSend(false);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error downloading image: ' . $e->getMessage()
            ], 500);
        }
    }
}