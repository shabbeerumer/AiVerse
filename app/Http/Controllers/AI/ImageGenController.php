<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\ToolLog;
use App\Services\ApiService;
use App\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
        ini_set('max_execution_time', 300);
        ini_set('max_input_time', 300);
        ini_set('memory_limit', '1024M');
        set_time_limit(300);
        
        $request->validate([
            'prompts' => 'required|string',
        ]);

        try {
            // Parse prompts (support both comma-separated and CSV)
            $prompts = [];
            if (strpos($request->prompts, ',') !== false) {
                // Comma-separated
                $prompts = array_map('trim', explode(',', $request->prompts));
            } else {
                // Single prompt or CSV content
                $prompts = FileService::parseCsvPrompts($request->prompts);
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

            foreach ($prompts as $prompt) {
                if (!empty($prompt)) {
                    // Use the new Stable Diffusion XL model
                    $result = ApiService::huggingFaceRequest(
                        'stabilityai/stable-diffusion-xl-base-1.0',
                        ['inputs' => $prompt],
                        ['timeout' => 300, 'connect_timeout' => 60] // Increased timeout for XL model
                    );

                    if ($result['success']) {
                        $imageUrl = FileService::saveFile($result['data'], 'ai_images', 'png');
                        if ($imageUrl) {
                            $images[] = $imageUrl;
                        } else {
                            $failedPrompts[] = $prompt;
                        }
                    } else {
                        $failedPrompts[] = $prompt . ' (' . $result['error'] . ')';
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

    public function downloadAll(Request $request)
    {
        // Increase script execution time for ZIP creation
        ini_set('max_execution_time', 300);
        set_time_limit(300);
        
        $request->validate([
            'images' => 'required|array',
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