<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\ToolLog;
use App\Services\ApiService;
use App\Services\FileService;
use Illuminate\Http\Request;

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
            
            // Process the image with the free model only
            $result = ApiService::huggingFaceRequest(
                'Xenova/rmbg-1.4', // FREE model
                ['inputs' => ['image' => base64_encode($imageData)]], // Correct HF format
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
                // Check if this is a retryable error
                if (isset($result['retryable']) && $result['retryable']) {
                    // Try one more time with a different model
                    $result = ApiService::huggingFaceRequest(
                        'briaai/RMBG-1.4', // Alternative model
                        ['inputs' => ['image' => base64_encode($imageData)]],
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
                        return response()->json([
                            'success' => false,
                            'message' => 'Background removal failed: ' . $result['error'],
                            'retryable' => $result['retryable'] ?? false
                        ], 500);
                    }
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Background removal failed: ' . $result['error'],
                        'retryable' => $result['retryable'] ?? false
                    ], 500);
                }
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing image: ' . $e->getMessage()
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