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

    public function generateVoice(Request $request)
    {
        try {
            $request->validate([
                'text' => 'required|string|max:1000',
            ]);

            $text = $request->input('text');
            
            // Check for special characters that might cause issues
            if (preg_match('/[<>{}[\]\\\\\/]/', $text)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Text contains invalid characters'
                ], 400);
            }

            // Generate voice with the free model only
            $result = ApiService::huggingFaceRequest(
                'suno/bark', // FREE model
                ['inputs' => $text], // Correct HF format
                ['timeout' => 600, 'connect_timeout' => 60]
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
                // Try the other free model as fallback
                $result = ApiService::huggingFaceRequest(
                    'espnet/kan-bayashi-ljspeech-vits', // Alternative FREE model
                    ['inputs' => $text], // Correct HF format
                    ['timeout' => 600, 'connect_timeout' => 60]
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
                    // Try a third model as fallback
                    $result = ApiService::huggingFaceRequest(
                        'facebook/fastspeech2-en-ljspeech', // Third FREE model
                        ['inputs' => $text],
                        ['timeout' => 600, 'connect_timeout' => 60]
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
                        return response()->json([
                            'success' => false,
                            'message' => 'Voice cloning failed: ' . $result['error'],
                            'retryable' => $result['retryable'] ?? false
                        ], 500);
                    }
                }
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