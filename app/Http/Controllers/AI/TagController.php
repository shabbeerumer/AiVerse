<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\ToolLog;
use App\Services\ApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TagController extends Controller
{
    public function index()
    {
        return view('ai.tag-downloader');
    }

    public function fetchTags(Request $request)
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
            $cacheKey = 'yt_metadata_' . $videoId;
            $metadata = Cache::get($cacheKey);
            
            if (!$metadata) {
                // Fetch video metadata using YouTube Data API
                $metadata = $this->fetchMetadata($videoId);
                
                if (!$metadata) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to fetch video metadata'
                    ], 500);
                }
                
                // Cache for 1 hour
                Cache::put($cacheKey, $metadata, 3600);
            }

            // Log the tool usage
            ToolLog::create([
                'tool_name' => 'tag_downloader',
                'user_ip' => $request->ip(),
                'details' => json_encode(['video_id' => $videoId])
            ]);

            return response()->json([
                'success' => true,
                'metadata' => $metadata
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching tags: ' . $e->getMessage()
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

    private function fetchMetadata($videoId)
    {
        $result = ApiService::youtubeRequest('videos', [
            'id' => $videoId,
            'part' => 'snippet,statistics'
        ]);

        if ($result['success']) {
            $data = $result['data'];
            
            if (isset($data['items'][0])) {
                $item = $data['items'][0];
                return [
                    'title' => $item['snippet']['title'] ?? '',
                    'description' => $item['snippet']['description'] ?? '',
                    'tags' => $item['snippet']['tags'] ?? [],
                    'viewCount' => $item['statistics']['viewCount'] ?? 0,
                    'likeCount' => $item['statistics']['likeCount'] ?? 0,
                    'commentCount' => $item['statistics']['commentCount'] ?? 0,
                    'publishedAt' => $item['snippet']['publishedAt'] ?? '',
                ];
            }
        } else {
            \Log::error('YouTube API error: ' . $result['error']);
        }

        return null;
    }
}