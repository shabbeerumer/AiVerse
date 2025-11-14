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

            // Read image
            $imageData = base64_encode(file_get_contents($file->getPathname()));

            // === FREE MODEL REQUEST ===
            $payload = [
                "inputs" => $imageData
            ];

            $result = ApiService::huggingFaceRequest(
                'zhaozijie3132/BiRefNet',
                $payload,
                ['timeout' => 300, 'connect_timeout' => 60]
            );

            // SUCCESS
            if ($result['success']) {

                $resultUrl = FileService::saveFile($result['data'], 'bg_removed', 'png');

                if ($resultUrl) {
                    ToolLog::create([
                        'tool_name' => 'background_remover',
                        'user_ip' => $request->ip(),
                    ]);

                    return response()->json([
                        'success' => true,
                        'result_url' => $resultUrl
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to save processed image'
                ], 500);
            }

            // === FALLBACK TO PAID MODEL (IF FREE MODEL FAILS) ===
            if (strpos($result['error'], 'Payment Required') !== false) {

                $result = ApiService::huggingFaceRequest(
                    'briaai/RMBG-1.4',
                    ["inputs" => $imageData],
                    ['timeout' => 300, 'connect_timeout' => 60]
                );

                if ($result['success']) {
                    $resultUrl = FileService::saveFile($result['data'], 'bg_removed', 'png');

                    if ($resultUrl) {
                        ToolLog::create([
                            'tool_name' => 'background_remover',
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
                'message' => 'Background removal failed: ' . $result['error'],
                'retryable' => $result['retryable'] ?? false
            ], 500);

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
