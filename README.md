# AiVerse - AI Tools Website

A fully functional AI Tools Website built with Laravel 12 and Blade templates, featuring 7 powerful AI tools powered by free APIs and open-source models.

## Features

1. **Bulk Image Generation** - Generate AI images from text prompts using Stable Diffusion
2. **Background Remover** - Remove image backgrounds with U²-Net model
3. **Image Quality Enhancer** - Enhance image quality with Real-ESRGAN
4. **Voice Clone** - Clone voices with XTTS-v2 model
5. **Thumbnail Downloader** - Download YouTube video thumbnails
6. **Tag Downloader** - Extract YouTube video tags and metadata
7. **Audio Downloader** - Download YouTube audio as MP3

## Requirements

- PHP 8.2 or higher
- Composer
- Node.js and NPM
- SQLite (default) or MySQL database
- yt-dlp (for audio downloading)

## Installation

1. Clone the repository:
   ```bash
   git clone <repository-url>
   cd AiVerse
   ```

2. Install PHP dependencies:
   ```bash
   composer install
   ```

3. Install Node dependencies:
   ```bash
   npm install
   ```

4. Copy and configure the environment file:
   ```bash
   cp .env.example .env
   ```
   
   Update the following variables in `.env`:
   - `HUGGINGFACE_API_TOKEN` - Your Hugging Face API token
   - `YOUTUBE_API_KEY` - Your YouTube Data API v3 key
   - `YT_DLP_PATH` - Path to yt-dlp executable

5. Generate application key:
   ```bash
   php artisan key:generate
   ```

6. Run database migrations:
   ```bash
   php artisan migrate
   ```

7. Create a symbolic link for storage:
   ```bash
   php artisan storage:link
   ```

8. Build frontend assets:
   ```bash
   npm run build
   ```

9. Start the development server:
   ```bash
   php artisan serve
   ```

## Tools API Integration

### Image Generation
- Uses Stable Diffusion 2.1 model from Hugging Face
- API Endpoint: `https://api-inference.huggingface.co/models/stabilityai/stable-diffusion-2-1`

### Background Removal
- Uses U²-Net model from Hugging Face
- API Endpoint: `https://api-inference.huggingface.co/models/clovaai/remove-bg`

### Image Enhancement
- Uses Real-ESRGAN model from Hugging Face
- API Endpoint: `https://api-inference.huggingface.co/models/nightmareai/real-esrgan`

### Voice Cloning
- Uses XTTS-v2 model from Hugging Face
- API Endpoint: `https://api-inference.huggingface.co/models/coqui/XTTS-v2`

### YouTube Integration
- Uses YouTube Data API v3 for metadata extraction
- Uses yt-dlp for audio downloading

## Directory Structure

```
app/
├── Http/Controllers/AI/
│   ├── ImageGenController.php
│   ├── BgRemoveController.php
│   ├── EnhanceController.php
│   ├── VoiceCloneController.php
│   ├── ThumbnailController.php
│   ├── TagController.php
│   └── AudioController.php
├── Models/
│   └── ToolLog.php
config/
├── services.php
└── filesystems.php
database/
├── migrations/
│   └── 2025_11_11_000003_create_tool_logs_table.php
resources/
├── views/
│   ├── layouts/
│   │   └── app.blade.php
│   ├── welcome.blade.php
│   └── ai/
│       ├── image-generator.blade.php
│       ├── bg-remover.blade.php
│       ├── enhancer.blade.php
│       ├── voice-clone.blade.php
│       ├── thumbnail-downloader.blade.php
│       ├── tag-downloader.blade.php
│       └── audio-downloader.blade.php
routes/
└── web.php
```

## Usage

1. Visit `http://localhost:8000` in your browser
2. Navigate to any of the 7 AI tools using the navigation bar
3. Use each tool according to its specific instructions

## Logging

All tool usage is logged in the `tool_logs` table with:
- Tool name
- User IP address
- Timestamp
- Additional details (when applicable)

## License

This project is open-source and available under the MIT License.