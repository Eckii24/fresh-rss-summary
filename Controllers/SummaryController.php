<?php

class FreshExtension_Summary_Controller extends Minz_ActionController
{
    private const GEMINI_API_BASE = 'https://generativelanguage.googleapis.com/v1beta';
    private const YOUTUBE_PATTERNS = [
        '/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/',
        '/youtube\.com\/watch\?.*v=([a-zA-Z0-9_-]{11})/',
    ];

    public function summarizeAction()
    {
        $this->view->_layout(false);
        header('Content-Type: application/json');

        // Get configuration
        $api_key = FreshRSS_Context::$user_conf->gemini_api_key ?? '';
        $model = FreshRSS_Context::$user_conf->gemini_model ?? 'gemini-1.5-flash';
        $general_prompt = FreshRSS_Context::$user_conf->gemini_general_prompt ?? 'Please provide a concise summary of the following article content:';
        $youtube_prompt = FreshRSS_Context::$user_conf->gemini_youtube_prompt ?? 'Please provide a concise summary of this YouTube video:';

        // Validate configuration
        if (empty($api_key) || empty($model)) {
            echo json_encode([
                'success' => false,
                'error' => 'Missing Google Gemini API configuration. Please configure the extension first.'
            ]);
            return;
        }

        // Get entry
        $entry_id = Minz_Request::param('id');
        $entry_dao = FreshRSS_Factory::createEntryDao();
        $entry = $entry_dao->searchById($entry_id);

        if ($entry === null) {
            echo json_encode(['success' => false, 'error' => 'Article not found']);
            return;
        }

        $article_url = $entry->link();
        $content = $entry->content();

        try {
            // Check if this is a YouTube URL
            $youtube_video_id = $this->extractYouTubeVideoId($article_url);
            
            if ($youtube_video_id) {
                $summary = $this->summarizeYouTubeVideo($youtube_video_id, $youtube_prompt, $api_key, $model);
            } else {
                $summary = $this->summarizeTextContent($content, $general_prompt, $api_key, $model);
            }

            echo json_encode([
                'success' => true,
                'summary' => $summary,
                'is_youtube' => $youtube_video_id !== null
            ]);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to generate summary: ' . $e->getMessage()
            ]);
        }
    }

    private function extractYouTubeVideoId($url)
    {
        foreach (self::YOUTUBE_PATTERNS as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }

    private function summarizeYouTubeVideo($video_id, $prompt, $api_key, $model)
    {
        // For YouTube videos, we first need to upload the video reference to Gemini
        // However, YouTube direct processing might require different approach
        // For now, we'll use the text-based approach with video metadata
        
        $youtube_url = "https://www.youtube.com/watch?v={$video_id}";
        
        // Try to get video title and description using a fallback approach
        $video_info = $this->getYouTubeVideoInfo($video_id);
        
        $url = self::GEMINI_API_BASE . "/models/{$model}:generateContent?key={$api_key}";
        
        $video_context = "YouTube Video: {$youtube_url}\n";
        if ($video_info) {
            $video_context .= "Title: {$video_info['title']}\n";
            if (!empty($video_info['description'])) {
                $video_context .= "Description: {$video_info['description']}\n";
            }
        }
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $prompt . "\n\n" . $video_context . "\n\nPlease provide a summary based on this YouTube video information."
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 1024,
            ]
        ];

        return $this->callGeminiAPI($url, $data);
    }
    
    private function getYouTubeVideoInfo($video_id)
    {
        // Simple approach to get video info from YouTube page
        // This is a basic implementation - in production you might want to use YouTube API
        try {
            $url = "https://www.youtube.com/watch?v={$video_id}";
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            
            $html = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200 && $html) {
                $title = '';
                $description = '';
                
                // Extract title
                if (preg_match('/<title>([^<]*)<\/title>/i', $html, $matches)) {
                    $title = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
                    $title = str_replace(' - YouTube', '', $title);
                }
                
                // Extract description from meta tag
                if (preg_match('/<meta name="description" content="([^"]*)"/', $html, $matches)) {
                    $description = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
                }
                
                return [
                    'title' => $title,
                    'description' => substr($description, 0, 500) // Limit description length
                ];
            }
        } catch (Exception $e) {
            // If we can't get video info, just continue without it
        }
        
        return null;
    }

    private function summarizeTextContent($content, $prompt, $api_key, $model)
    {
        $url = self::GEMINI_API_BASE . "/models/{$model}:generateContent?key={$api_key}";
        
        // Convert HTML to plain text for better processing
        $text_content = $this->htmlToText($content);
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $prompt . "\n\n" . $text_content
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 1024,
            ]
        ];

        return $this->callGeminiAPI($url, $data);
    }

    private function callGeminiAPI($url, $data)
    {
        $json_data = json_encode($data);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json_data,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json_data)
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            curl_close($ch);
            throw new Exception('CURL Error: ' . curl_error($ch));
        }
        
        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception("API Error: HTTP {$http_code} - {$response}");
        }

        $result = json_decode($response, true);
        
        if (!$result || !isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception('Invalid API response format');
        }

        return $result['candidates'][0]['content']['parts'][0]['text'];
    }

    private function htmlToText($html)
    {
        // Create DOMDocument to parse HTML
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        // Remove script and style elements
        $xpath = new DOMXPath($dom);
        $scripts = $xpath->query('//script | //style');
        foreach ($scripts as $script) {
            $script->parentNode->removeChild($script);
        }

        // Get text content and clean it up
        $text = $dom->textContent;
        
        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        return $text;
    }
}