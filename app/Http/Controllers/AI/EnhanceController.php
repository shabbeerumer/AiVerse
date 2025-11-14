<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\ToolLog;
use App\Services\ApiService;
use App\Services\FileService;
use Illuminate\Http\Request;

class EnhanceController extends Controller
{
    public function index()
    {
        return view('ai.enhancer');
    }

    public function enhance(Request $request)
    {
        // Increase script execution time
        ini_set('max_execution_time', 300);
        ini_set('max_input_time', 300);
        ini_set('memory_limit', '1024M');
        set_time_limit(300);
        
        $request->validate([
            'image' => 'required|file',
        ]);

        try {
            // Validate file
            $file = $request->file('image');
            $validation = FileService::validateFile($file, ['image/jpeg', 'image/png', 'image/webp'], 5120);
            
            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $validation['error']
                ], 400);
            }

            // Read the image file
            $imageData = file_get_contents($file->getPathname());
            
            // Enhance the image with a free alternative model
            $result = ApiService::huggingFaceRequest(
                'nightmareai/real-esrgan', // Free alternative to swin2SR
                ['inputs' => base64_encode($imageData)],
                ['timeout' => 300, 'connect_timeout' => 60]
            );
            
            if ($result['success']) {
                $resultUrl = FileService::saveFile($result['data'], 'enhanced', 'png');
                
                if ($resultUrl) {
                    // Log the tool usage
                    ToolLog::create([
                        'tool_name' => 'image_enhancer',
                        'user_ip' => $request->ip(),
                    ]);

                    return response()->json([
                        'success' => true,
                        'result_url' => $resultUrl
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to save enhanced image'
                    ], 500);
                }
            } else {
                // Fallback to another free model if the first one fails
                if (strpos($result['error'], 'Payment Required') !== false) {
                    $result = ApiService::huggingFaceRequest(
                        'caidas/swin2SR-classical-sr-x2-64',
                        ['inputs' => base64_encode($imageData)],
                        ['timeout' => 300, 'connect_timeout' => 60]
                    );
                    
                    if ($result['success']) {
                        $resultUrl = FileService::saveFile($result['data'], 'enhanced', 'png');
                        
                        if ($resultUrl) {
                            ToolLog::create([
                                'tool_name' => 'image_enhancer',
                                'user_ip' => $request->ip(),
                            ]);

                            return response()->json([
                                'success' => true,
                                'result_url' => $resultUrl
                            ]);
                        }
                    }
                }
                
                return response()->json([
                    'success' => false,
                    'message' => 'Image enhancement failed: ' . $result['error'],
                    'retryable' => $result['retryable'] ?? false
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error enhancing image: ' . $e->getMessage()
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
                'tool_name' => 'image_enhancer_download',
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