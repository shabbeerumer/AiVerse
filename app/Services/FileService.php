<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class FileService
{
    /**
     * Save file to storage and return URL
     *
     * @param string $data
     * @param string $directory
     * @param string $extension
     * @return string|null
     */
    public static function saveFile($data, $directory, $extension = 'png')
    {
        try {
            $filename = $directory . '/' . Str::random(40) . '.' . $extension;
            Storage::disk('public')->put($filename, $data);
            return Storage::url($filename);
        } catch (\Exception $e) {
            \Log::error('File save failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create a ZIP archive from array of file URLs
     *
     * @param array $fileUrls
     * @param string $zipName
     * @return string|null
     */
    public static function createZipArchive($fileUrls, $zipName = null)
    {
        try {
            if (!$zipName) {
                $zipName = 'archive_' . time() . '.zip';
            }
            
            $zipPath = 'zips/' . $zipName;
            $zipFullPath = storage_path('app/public/' . $zipPath);
            
            // Ensure directory exists
            $zipDir = dirname($zipFullPath);
            if (!file_exists($zipDir)) {
                mkdir($zipDir, 0755, true);
            }
            
            $zip = new ZipArchive();
            if ($zip->open($zipFullPath, ZipArchive::CREATE) === TRUE) {
                foreach ($fileUrls as $fileUrl) {
                    // Convert URL to local path
                    $relativePath = str_replace('/storage/', '', $fileUrl);
                    $localPath = storage_path('app/public/' . $relativePath);
                    
                    if (file_exists($localPath)) {
                        $fileName = basename($localPath);
                        $zip->addFile($localPath, $fileName);
                    }
                }
                $zip->close();
                
                return Storage::url($zipPath);
            }
            
            return null;
        } catch (\Exception $e) {
            \Log::error('ZIP creation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Clean up temporary files
     *
     * @param array $filePaths
     * @return void
     */
    public static function cleanupFiles($filePaths)
    {
        foreach ($filePaths as $filePath) {
            try {
                // Convert URL to local path if needed
                if (strpos($filePath, '/storage/') !== false) {
                    $relativePath = str_replace('/storage/', '', $filePath);
                    $localPath = storage_path('app/public/' . $relativePath);
                } else {
                    $localPath = $filePath;
                }
                
                if (file_exists($localPath)) {
                    unlink($localPath);
                }
            } catch (\Exception $e) {
                \Log::warning('File cleanup failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Validate uploaded file
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param array $allowedMimes
     * @param int $maxSize
     * @return array
     */
    public static function validateFile($file, $allowedMimes = [], $maxSize = 5120)
    {
        // Check if file exists
        if (!$file || !$file->isValid()) {
            return [
                'valid' => false,
                'error' => 'Invalid file upload'
            ];
        }
        
        // Check file size
        if ($file->getSize() > $maxSize * 1024) {
            return [
                'valid' => false,
                'error' => "File size exceeds {$maxSize}KB limit"
            ];
        }
        
        // Check MIME type if specified
        if (!empty($allowedMimes)) {
            $mimeType = $file->getMimeType();
            $extension = $file->getClientOriginalExtension();
            
            $valid = false;
            foreach ($allowedMimes as $allowed) {
                // Check both MIME type and extension
                if ($mimeType === $allowed || $extension === str_replace(['image/', 'audio/'], '', $allowed)) {
                    $valid = true;
                    break;
                }
            }
            
            if (!$valid) {
                return [
                    'valid' => false,
                    'error' => 'Invalid file type. Allowed types: ' . implode(', ', $allowedMimes)
                ];
            }
        }
        
        return [
            'valid' => true
        ];
    }

    /**
     * Parse CSV content into array of prompts
     *
     * @param string $csvContent
     * @return array
     */
    public static function parseCsvPrompts($csvContent)
    {
        $prompts = [];
        
        // Split by lines
        $lines = explode("\n", trim($csvContent));
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                // Handle CSV with commas (split by comma but respect quoted strings)
                $items = str_getcsv($line);
                foreach ($items as $item) {
                    $item = trim($item);
                    if (!empty($item)) {
                        $prompts[] = $item;
                    }
                }
            }
        }
        
        return array_unique($prompts);
    }
}