<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\ToolLog;
use App\Services\ApiService;
use App\Services\FileService;
use Illuminate\Http\Request;

class AudioController extends Controller
{
    public function index()
    {
        return view('ai.audio-downloader');
    }

    public function downloadAudio(Request $request)
    {
        // Increase script execution time
        ini_set('max_execution_time', 300);
        ini_set('max_input_time', 300);
        ini_set('memory_limit', '1024M');
        set_time_limit(300);
        
        $request->validate([
            'url' => 'required|url',
        ]);

        try {
            // Extract video ID from YouTube URL
            $videoId = $this->extractVideoId($request->url);
            
            if (!$videoId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid YouTube URL'
                ], 400);
            }

            // Download audio using yt-dlp
            $result = ApiService::executeYtdlp($request->url);
            
            if ($result['success']) {
                $audioUrl = FileService::saveFile($result['data'], 'audio', 'mp3');
                
                if ($audioUrl) {
                    // Log the tool usage
                    ToolLog::create([
                        'tool_name' => 'audio_downloader',
                        'user_ip' => $request->ip(),
                        'details' => json_encode(['video_id' => $videoId])
                    ]);

                    return response()->json([
                        'success' => true,
                        'audio_url' => $audioUrl
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to save audio file'
                    ], 500);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Audio download failed: ' . $result['error'],
                    'retryable' => $result['retryable'] ?? false
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error downloading audio: ' . $e->getMessage()
            ], 500);
        }
    }

    private function extractVideoId($url)
    {
        $pattern = '/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([^&\n?#]+)/';
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
        return null;
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
                'tool_name' => 'audio_download',
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