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
        $model = FreshRSS_Context::$user_conf->gemini_model ?? 'gemini-2.5-flash';
        $general_prompt = FreshRSS_Context::$user_conf->gemini_general_prompt ?? 'Please provide a concise summary of the following article content:';
        $youtube_prompt = FreshRSS_Context::$user_conf->gemini_youtube_prompt ?? 'Please provide a concise summary of this YouTube video:';
        $max_tokens = FreshRSS_Context::$user_conf->gemini_max_tokens ?? 1024;
        $temperature = FreshRSS_Context::$user_conf->gemini_temperature ?? 0.7;

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
                $summary = $this->summarizeYouTubeVideo($youtube_video_id, $youtube_prompt, $api_key, $model, $max_tokens, $temperature);
            } else {
                $summary = $this->summarizeTextContent($content, $general_prompt, $api_key, $model, $max_tokens, $temperature);
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

    private function summarizeYouTubeVideo($video_id, $prompt, $api_key, $model, $max_tokens, $temperature)
    {
        // For YouTube videos, we have two approaches:
        // 1. Use video file upload API (complex, requires file processing)
        // 2. Use text-based approach with video metadata (simpler, more reliable)
        
        // We'll use approach 2 for better reliability and easier implementation
        $youtube_url = "https://www.youtube.com/watch?v={$video_id}";
        
        // Get video metadata for better context
        $video_info = $this->getYouTubeVideoInfo($video_id);
        
        $url = self::GEMINI_API_BASE . "/models/{$model}:generateContent?key={$api_key}";
        
        $video_context = "YouTube Video Analysis Request\n";
        $video_context .= "Video URL: {$youtube_url}\n";
        
        if ($video_info) {
            $video_context .= "Video Title: {$video_info['title']}\n";
            if (!empty($video_info['description'])) {
                $video_context .= "Video Description: {$video_info['description']}\n";
            }
        }
        
        $video_context .= "\nNote: This is a YouTube video. Please provide a summary based on the video title and description, ";
        $video_context .= "focusing on the main topics, key points, and overall content theme.";
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $prompt . "\n\n" . $video_context
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $temperature,
                'maxOutputTokens' => $max_tokens,
                'topP' => 0.9,
                'topK' => 40
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

    private function summarizeTextContent($content, $prompt, $api_key, $model, $max_tokens, $temperature)
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
                'temperature' => $temperature,
                'maxOutputTokens' => $max_tokens,
                'topP' => 0.9,
                'topK' => 40
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
        
        if (!$result) {
            throw new Exception('Invalid JSON response from Gemini API');
        }
        
        return $this->parseGeminiResponse($result);
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

    private function parseGeminiResponse($result)
    {
        // Check for API error response
        if (isset($result['error'])) {
            $error_message = $result['error']['message'] ?? 'Unknown API error';
            throw new Exception("Gemini API Error: {$error_message}");
        }
        
        // Check for candidates array
        if (!isset($result['candidates']) || !is_array($result['candidates']) || empty($result['candidates'])) {
            throw new Exception('No candidates found in API response');
        }
        
        $candidate = $result['candidates'][0];
        
        // Check for safety/content filtering
        if (isset($candidate['finishReason'])) {
            $finish_reason = $candidate['finishReason'];
            if (in_array($finish_reason, ['SAFETY', 'RECITATION', 'PROHIBITED_CONTENT'])) {
                throw new Exception('Content was blocked by safety filters');
            }
            if ($finish_reason === 'MAX_TOKENS') {
                // Content was truncated but might still be usable
            }
        }
        
        // Try to extract text from various possible response formats
        
        // Format 1: Standard format - candidates[0].content.parts[0].text
        if (isset($candidate['content']['parts'][0]['text'])) {
            return $this->combineTextParts($candidate['content']['parts']);
        }
        
        // Format 2: Alternative format - candidates[0].parts[0].text
        if (isset($candidate['parts'][0]['text'])) {
            return $this->combineTextParts($candidate['parts']);
        }
        
        // Format 3: Direct text in candidate
        if (isset($candidate['text'])) {
            return $candidate['text'];
        }
        
        // If no text found, throw specific error
        throw new Exception('No text content found in API response');
    }

    private function combineTextParts($parts)
    {
        if (!is_array($parts)) {
            return '';
        }
        
        $text_parts = [];
        foreach ($parts as $part) {
            if (isset($part['text']) && !empty(trim($part['text']))) {
                $text_parts[] = trim($part['text']);
            }
        }
        
        return implode(' ', $text_parts);
    }
}