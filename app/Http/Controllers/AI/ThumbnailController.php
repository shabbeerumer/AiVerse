<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\ToolLog;
use App\Services\ApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ThumbnailController extends Controller
{
    public function index()
    {
        return view('ai.thumbnail-downloader');
    }

    public function fetchThumbnails(Request $request)
    {
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

            // Try to get from cache first
            $cacheKey = 'yt_thumbnails_' . $videoId;
            $thumbnails = Cache::get($cacheKey);
            
            if (!$thumbnails) {
                // Try to get thumbnails from piped.video API first
                $thumbnails = $this->getThumbnailsFromPiped($videoId);
                
                // If piped.video fails, fallback to YouTube static URLs
                if (empty($thumbnails)) {
                    $thumbnails = $this->getThumbnails($videoId);
                }
                
                // Cache for 1 hour
                Cache::put($cacheKey, $thumbnails, 3600);
            }
            
            // Log the tool usage
            ToolLog::create([
                'tool_name' => 'thumbnail_downloader',
                'user_ip' => $request->ip(),
                'details' => json_encode(['video_id' => $videoId])
            ]);

            return response()->json([
                'success' => true,
                'thumbnails' => $thumbnails
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching thumbnails: ' . $e->getMessage()
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

    private function getThumbnailsFromPiped($videoId)
    {
        try {
            // Try piped.video API
            $response = Http::timeout(30)->get("https://pipedapi.kavin.rocks/streams/{$videoId}");
            
            if ($response->successful()) {
                $data = $response->json();
                
                // Extract thumbnails from piped response
                $thumbnails = [];
                if (isset($data['thumbnailUrl'])) {
                    // Use the main thumbnail URL
                    $thumbnails['default'] = $data['thumbnailUrl'];
                }
                
                // Add other resolutions if available
                if (isset($data['videos']) && is_array($data['videos'])) {
                    foreach ($data['videos'] as $video) {
                        if (isset($video['format']) && isset($video['url'])) {
                            if (strpos($video['format'], 'video') !== false) {
                                // This is a video format, not a thumbnail
                                continue;
                            }
                            
                            // Map format to thumbnail quality
                            if (strpos($video['format'], 'maxres') !== false) {
                                $thumbnails['maxres'] = $video['url'];
                            } elseif (strpos($video['format'], 'sd') !== false) {
                                $thumbnails['sd'] = $video['url'];
                            } elseif (strpos($video['format'], 'hq') !== false) {
                                $thumbnails['hq'] = $video['url'];
                            } elseif (strpos($video['format'], 'mq') !== false) {
                                $thumbnails['mq'] = $video['url'];
                            }
                        }
                    }
                }
                
                // If we got at least one thumbnail, return what we have
                if (!empty($thumbnails)) {
                    return $thumbnails;
                }
            }
        } catch (\Exception $e) {
            // Log the error but don't fail - we'll fallback to YouTube static URLs
            \Log::warning('Piped.video API failed: ' . $e->getMessage());
        }
        
        return [];
    }

    private function getThumbnails($videoId)
    {
        return [
            'default' => "https://img.youtube.com/vi/{$videoId}/default.jpg",
            'hq' => "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg",
            'mq' => "https://img.youtube.com/vi/{$videoId}/mqdefault.jpg",
            'sd' => "https://img.youtube.com/vi/{$videoId}/sddefault.jpg",
            'maxres' => "https://img.youtube.com/vi/{$videoId}/maxresdefault.jpg",
        ];
    }

    public function download(Request $request)
    {
        $request->validate([
            'thumbnail_url' => 'required|url',
        ]);

        try {
            $thumbnailUrl = $request->thumbnail_url;
            $filename = basename(parse_url($thumbnailUrl, PHP_URL_PATH));

            // Validate that it's a YouTube or piped thumbnail
            if (strpos($thumbnailUrl, 'img.youtube.com/vi/') === false && 
                strpos($thumbnailUrl, 'piped') === false) {
                return response()->json(['success' => false, 'message' => 'Invalid thumbnail URL'], 400);
            }

            // Log the tool usage
            ToolLog::create([
                'tool_name' => 'thumbnail_download',
                'user_ip' => $request->ip(),
            ]);

            return response()->stream(function () use ($thumbnailUrl) {
                echo file_get_contents($thumbnailUrl);
            }, 200, [
                'Content-Type' => 'image/jpeg',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error downloading thumbnail: ' . $e->getMessage()
            ], 500);
        }
    }
}