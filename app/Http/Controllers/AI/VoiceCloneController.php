<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\ToolLog;
use App\Services\ApiService;
use App\Services\FileService;
use Illuminate\Http\Request;

class VoiceCloneController extends Controller
{
    public function index()
    {
        return view('ai.voice-clone');
    }

    public function clone(Request $request)
    {
        // Increase script execution time
        ini_set('max_execution_time', 300);
        ini_set('max_input_time', 300);
        ini_set('memory_limit', '1024M');
        set_time_limit(300);
        
        $request->validate([
            'voice_sample' => 'required|file',
            'text' => 'required|string|max:1000',
        ]);

        try {
            // Validate text
            $text = trim($request->text);
            if (empty($text)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Text cannot be empty'
                ], 400);
            }

            // Check for special characters that might cause issues
            if (preg_match('/[<>{}[\]\\\\\/]/', $text)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Text contains invalid characters'
                ], 400);
            }

            // Validate file
            $file = $request->file('voice_sample');
            $validation = FileService::validateFile($file, ['audio/wav', 'audio/mp3'], 10240);
            
            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $validation['error']
                ], 400);
            }

            // Read the voice sample file
            $voiceData = file_get_contents($file->getPathname());
            
            // Generate voice with a free alternative model
            $result = ApiService::huggingFaceRequest(
                'facebook/fastspeech2-en-ljspeech', // Free alternative to coqui/XTTS-v2
                ['inputs' => $text],
                ['timeout' => 300, 'connect_timeout' => 60]
            );
            
            if ($result['success']) {
                $audioUrl = FileService::saveFile($result['data'], 'voices', 'wav');
                
                if ($audioUrl) {
                    // Log the tool usage
                    ToolLog::create([
                        'tool_name' => 'voice_clone',
                        'user_ip' => $request->ip(),
                        'details' => json_encode(['text_length' => strlen($text)])
                    ]);

                    return response()->json([
                        'success' => true,
                        'audio_url' => $audioUrl
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to save generated audio'
                    ], 500);
                }
            } else {
                // Fallback to another free model if the first one fails
                if (strpos($result['error'], 'Payment Required') !== false) {
                    $result = ApiService::huggingFaceRequest(
                        'coqui/XTTS-v2',
                        ['inputs' => $text],
                        ['timeout' => 300, 'connect_timeout' => 60]
                    );
                    
                    if ($result['success']) {
                        $audioUrl = FileService::saveFile($result['data'], 'voices', 'wav');
                        
                        if ($audioUrl) {
                            ToolLog::create([
                                'tool_name' => 'voice_clone',
                                'user_ip' => $request->ip(),
                                'details' => json_encode(['text_length' => strlen($text)])
                            ]);

                            return response()->json([
                                'success' => true,
                                'audio_url' => $audioUrl
                            ]);
                        }
                    }
                }
                
                return response()->json([
                    'success' => false,
                    'message' => 'Voice cloning failed: ' . $result['error'],
                    'retryable' => $result['retryable'] ?? false
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating voice: ' . $e->getMessage()
            ], 500);
        }
    }

    public function download(Request $request)
    {
        $request->validate([
            'audio_url' => 'required|string',
        ]);

        try {
            $audioUrl = $request->audio_url;
            $filename = basename(parse_url($audioUrl, PHP_URL_PATH));
            $filePath = storage_path('app/public/' . str_replace('/storage/', '', $audioUrl));

            if (!file_exists($filePath)) {
                return response()->json(['success' => false, 'message' => 'File not found'], 404);
            }

            // Log the tool usage
            ToolLog::create([
                'tool_name' => 'voice_clone_download',
                'user_ip' => $request->ip(),
            ]);

            return response()->download($filePath, $filename)->deleteFileAfterSend(false);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error downloading audio: ' . $e->getMessage()
            ], 500);
        }
    }
}