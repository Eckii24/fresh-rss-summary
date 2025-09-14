<?php

class FreshExtension_Summary_Controller extends Minz_ActionController
{
    private const GEMINI_API_BASE = 'https://generativelanguage.googleapis.com/v1beta';
    private const GEMINI_API_BASE_V1 = 'https://generativelanguage.googleapis.com/v1';
    private const YOUTUBE_PATTERNS = [
        '/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/',
        '/youtube\.com\/watch\?.*v=([a-zA-Z0-9_-]{11})/',
    ];
    
    // Set to true to enable debug logging for troubleshooting API response issues
    private const DEBUG_MODE = false;
    
    // Models that might need v1 API instead of v1beta
    private const V1_MODELS = ['gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-2.0-flash'];

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
    
    private function getApiUrl($model)
    {
        // Use v1 API for newer models that might not be available in v1beta
        if (in_array($model, self::V1_MODELS)) {
            if (self::DEBUG_MODE) {
                error_log("Using v1 API for model: {$model}");
            }
            return self::GEMINI_API_BASE_V1;
        }
        
        // Use v1beta for older/standard models
        if (self::DEBUG_MODE) {
            error_log("Using v1beta API for model: {$model}");
        }
        return self::GEMINI_API_BASE;
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
        
        $url = $this->getApiUrl($model) . "/models/{$model}:generateContent?key={$api_key}";
        
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
        
        // Use different request format based on API version
        $is_v1_model = in_array($model, self::V1_MODELS);
        
        if ($is_v1_model) {
            // v1 API format - requires explicit role
            $data = [
                'contents' => [
                    [
                        'role' => 'user',
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
        } else {
            // v1beta API format (original)
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
        }

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
        $url = $this->getApiUrl($model) . "/models/{$model}:generateContent?key={$api_key}";
        
        // Convert HTML to plain text for better processing
        $text_content = $this->htmlToText($content);
        
        // Use different request format based on API version
        $is_v1_model = in_array($model, self::V1_MODELS);
        
        if ($is_v1_model) {
            // v1 API format - requires explicit role
            $data = [
                'contents' => [
                    [
                        'role' => 'user',
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
        } else {
            // v1beta API format (original)
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
        }

        return $this->callGeminiAPI($url, $data);
    }

    private function callGeminiAPI($url, $data)
    {
        $json_data = json_encode($data);
        
        // Debug: Log the request for troubleshooting
        if (self::DEBUG_MODE) {
            error_log('Gemini API Request URL: ' . $url);
            error_log('Gemini API Request Data: ' . $json_data);
        }
        
        // Try the primary request format first
        $result = $this->makeGeminiRequest($url, $data);
        if ($result !== null) {
            try {
                return $this->parseGeminiResponse($result);
            } catch (Exception $e) {
                if (self::DEBUG_MODE) {
                    error_log('Primary format failed during parsing: ' . $e->getMessage());
                }
                // Continue to try alternatives
            }
        }
        
        // If primary format failed, try alternative API version first
        $alternative_url = $this->getAlternativeApiUrl($url);
        if ($alternative_url !== $url) {
            if (self::DEBUG_MODE) {
                error_log("Trying alternative API endpoint: {$alternative_url}");
            }
            
            $result = $this->makeGeminiRequest($alternative_url, $data);
            if ($result !== null) {
                try {
                    return $this->parseGeminiResponse($result);
                } catch (Exception $e) {
                    if (self::DEBUG_MODE) {
                        error_log('Alternative API endpoint failed during parsing: ' . $e->getMessage());
                    }
                }
            }
        }
        
        // If API endpoint switching failed, try alternative request formats
        if (self::DEBUG_MODE) {
            error_log('API endpoint switching failed, trying alternative request formats...');
        }
        
        // Try multiple alternative formats with original URL
        for ($i = 0; $i < 3; $i++) {
            $alternative_data = $this->getAlternativeRequestFormat($data, $i);
            if ($alternative_data === $data) {
                break; // No more alternatives
            }
            
            if (self::DEBUG_MODE) {
                error_log("Trying alternative format #{$i}: " . json_encode($alternative_data));
            }
            
            $result = $this->makeGeminiRequest($url, $alternative_data);
            if ($result !== null) {
                try {
                    return $this->parseGeminiResponse($result);
                } catch (Exception $e) {
                    if (self::DEBUG_MODE) {
                        error_log("Alternative format #{$i} failed during parsing: " . $e->getMessage());
                    }
                    // Continue to next alternative
                }
            }
        }
        
        throw new Exception('All request formats and API endpoints failed. This may indicate the model is not available or the API configuration is incorrect.');
    }
    
    private function getAlternativeApiUrl($original_url)
    {
        // Switch between v1 and v1beta APIs
        if (strpos($original_url, '/v1beta/') !== false) {
            return str_replace('/v1beta/', '/v1/', $original_url);
        } elseif (strpos($original_url, '/v1/') !== false) {
            return str_replace('/v1/', '/v1beta/', $original_url);
        }
        return $original_url;
    }
    
    private function makeGeminiRequest($url, $data)
    {
        $json_data = json_encode($data);
        
        // Debug: Log request details
        if (self::DEBUG_MODE) {
            error_log("Making request to URL: {$url}");
            error_log("Request payload: " . $json_data);
        }
        
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
        
        // Debug: Log the raw response
        if (self::DEBUG_MODE) {
            error_log('Gemini API Raw Response (HTTP ' . $http_code . '): ' . $response);
        }
        
        if (curl_error($ch)) {
            $curl_error = curl_error($ch);
            curl_close($ch);
            if (self::DEBUG_MODE) {
                error_log("CURL Error: {$curl_error}");
            }
            throw new Exception('CURL Error: ' . $curl_error);
        }
        
        curl_close($ch);

        if ($http_code !== 200) {
            if (self::DEBUG_MODE) {
                error_log("API request failed with HTTP {$http_code}. Response: {$response}");
            }
            
            // For debugging, let's parse the error response to provide better info
            $error_data = json_decode($response, true);
            if ($error_data && isset($error_data['error'])) {
                $error_message = $error_data['error']['message'] ?? 'Unknown error';
                if (self::DEBUG_MODE) {
                    error_log("Parsed error message: {$error_message}");
                }
                throw new Exception("API Error (HTTP {$http_code}): {$error_message}");
            }
            
            // Return null to try alternative format instead of throwing immediately
            return null;
        }

        $result = json_decode($response, true);
        
        if (!$result) {
            if (self::DEBUG_MODE) {
                error_log("Failed to decode JSON response: {$response}");
            }
            throw new Exception('Invalid JSON response from Gemini API: ' . $response);
        }
        
        return $result;
    }
    
    private function getAlternativeRequestFormat($original_data, $alternative_index = 0)
    {
        // Alternative formats for different API versions and models
        if (isset($original_data['contents'][0]['parts'][0]['text'])) {
            $text = $original_data['contents'][0]['parts'][0]['text'];
            
            $alternatives = [];
            
            // Alternative 0: v1 API format with explicit role
            $alternatives[0] = [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $text]
                        ]
                    ]
                ]
            ];
            
            // Alternative 1: Simplified format (some models might expect this)
            $alternatives[1] = [
                'prompt' => [
                    'text' => $text
                ]
            ];
            
            // Alternative 2: Text completion format (legacy API style)
            $alternatives[2] = [
                'input' => [
                    'text' => $text
                ]
            ];
            
            // Return the requested alternative or the original if index is out of bounds
            if (isset($alternatives[$alternative_index])) {
                $alternative = $alternatives[$alternative_index];
                
                // Copy generation config if present and adjust for API version
                if (isset($original_data['generationConfig'])) {
                    $gen_config = $original_data['generationConfig'];
                    
                    // For v1 API, some parameter names might be different
                    if ($alternative_index === 0) {
                        // Ensure compatibility with v1 API
                        $alternative['generationConfig'] = [
                            'temperature' => $gen_config['temperature'] ?? 0.7,
                            'maxOutputTokens' => $gen_config['maxOutputTokens'] ?? 1024,
                            'topP' => $gen_config['topP'] ?? 0.9,
                            'topK' => $gen_config['topK'] ?? 40
                        ];
                    } else {
                        $alternative['generationConfig'] = $gen_config;
                    }
                }
                
                if (self::DEBUG_MODE) {
                    error_log("Returning alternative request format #{$alternative_index}: " . json_encode($alternative, JSON_PRETTY_PRINT));
                }
                
                return $alternative;
            }
        }
        
        return $original_data;
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
        // Add debug logging for response structure
        if (self::DEBUG_MODE) {
            error_log('Gemini API Response Structure: ' . json_encode($result, JSON_PRETTY_PRINT));
        }
        
        // Check for API error response
        if (isset($result['error'])) {
            $error_message = $result['error']['message'] ?? 'Unknown API error';
            throw new Exception("Gemini API Error: {$error_message}");
        }
        
        // Check for candidates array
        if (!isset($result['candidates']) || !is_array($result['candidates']) || empty($result['candidates'])) {
            throw new Exception('No candidates found in API response. Response: ' . json_encode($result));
        }
        
        $candidate = $result['candidates'][0];
        if (self::DEBUG_MODE) {
            error_log('First candidate structure: ' . json_encode($candidate, JSON_PRETTY_PRINT));
        }
        
        // Special handling for incomplete responses due to MAX_TOKENS or other issues
        if (isset($candidate['finishReason'])) {
            $finish_reason = $candidate['finishReason'];
            if (in_array($finish_reason, ['SAFETY', 'RECITATION', 'PROHIBITED_CONTENT'])) {
                throw new Exception('Content was blocked by safety filters');
            }
            if ($finish_reason === 'MAX_TOKENS') {
                // Content was truncated but might still be usable
                if (self::DEBUG_MODE) {
                    error_log('Warning: Content was truncated due to MAX_TOKENS - will still try to extract available content');
                }
                
                // For MAX_TOKENS, if content is incomplete (missing parts), we need to handle this specifically
                if (isset($candidate['content']) && isset($candidate['content']['role']) && 
                    $candidate['content']['role'] === 'model' && 
                    !isset($candidate['content']['parts'])) {
                    
                    if (self::DEBUG_MODE) {
                        error_log('Detected MAX_TOKENS issue with incomplete content structure - this suggests request format may be incorrect for this model');
                    }
                    
                    throw new Exception('API response indicates token limit reached but content structure is incomplete. This may indicate a request format issue with this model. Try reducing max_tokens or check model compatibility.');
                }
                
                // Continue processing - don't throw error immediately for MAX_TOKENS, try to extract available content first
            }
        }
        
        // Try to extract text from various possible response formats
        
        // Format 1: Standard format - candidates[0].content.parts[0].text
        if (isset($candidate['content']['parts']) && is_array($candidate['content']['parts'])) {
            $text = $this->combineTextParts($candidate['content']['parts']);
            if (!empty($text)) {
                if (self::DEBUG_MODE) {
                    error_log('Successfully extracted text using Format 1. Text length: ' . strlen($text));
                    error_log('Format 1 - First 500 chars: ' . substr($text, 0, 500));
                    if (strlen($text) > 500) {
                        error_log('Format 1 - Last 200 chars: ' . substr($text, -200));
                    }
                }
                return $text;
            }
        }
        
        // Format 2: Alternative format - candidates[0].parts[0].text  
        if (isset($candidate['parts']) && is_array($candidate['parts'])) {
            $text = $this->combineTextParts($candidate['parts']);
            if (!empty($text)) {
                if (self::DEBUG_MODE) {
                    error_log('Successfully extracted text using Format 2');
                }
                return $text;
            }
        }
        
        // Format 3: Direct text in candidate
        if (isset($candidate['text']) && !empty($candidate['text'])) {
            if (self::DEBUG_MODE) {
                error_log('Successfully extracted text using Format 3');
            }
            return $candidate['text'];
        }
        
        // Format 4: Check for output property (newer API version)
        if (isset($candidate['output']) && !empty($candidate['output'])) {
            if (self::DEBUG_MODE) {
                error_log('Successfully extracted text using Format 4 (output)');
            }
            return $candidate['output'];
        }
        
        // Format 5: Check for message content (alternative structure)
        if (isset($candidate['message']['content']) && !empty($candidate['message']['content'])) {
            if (self::DEBUG_MODE) {
                error_log('Successfully extracted text using Format 5 (message.content)');
            }
            return $candidate['message']['content'];
        }
        
        // Format 6: Check for candidates[0].content.text (direct text in content)
        if (isset($candidate['content']['text']) && !empty($candidate['content']['text'])) {
            if (self::DEBUG_MODE) {
                error_log('Successfully extracted text using Format 6 (content.text)');
            }
            return $candidate['content']['text'];
        }
        
        // Format 7: Check if content is directly a string
        if (isset($candidate['content']) && is_string($candidate['content'])) {
            if (self::DEBUG_MODE) {
                error_log('Successfully extracted text using Format 7 (content as string)');
            }
            return $candidate['content'];
        }
        
        // Format 8: Check for response in different property names
        $potential_text_fields = ['response', 'generated_text', 'completion', 'answer'];
        foreach ($potential_text_fields as $field) {
            if (isset($candidate[$field]) && !empty($candidate[$field])) {
                if (self::DEBUG_MODE) {
                    error_log("Successfully extracted text using Format 8 ({$field})");
                }
                return $candidate[$field];
            }
        }
        
        // If no text found, provide detailed debugging info
        $debug_info = [
            'candidate_keys' => array_keys($candidate),
            'candidate_structure' => $candidate
        ];
        
        throw new Exception('No text content found in API response. Debug info: ' . json_encode($debug_info));
    }

    private function combineTextParts($parts)
    {
        if (!is_array($parts)) {
            if (self::DEBUG_MODE) {
                error_log('combineTextParts: parts is not an array: ' . gettype($parts));
            }
            return '';
        }
        
        if (self::DEBUG_MODE) {
            error_log('combineTextParts: processing ' . count($parts) . ' parts');
            error_log('combineTextParts: full parts structure: ' . json_encode($parts, JSON_PRETTY_PRINT));
        }
        
        $text_parts = [];
        foreach ($parts as $index => $part) {
            if (self::DEBUG_MODE) {
                error_log("Part {$index}: " . json_encode($part));
            }
            
            $part_text = '';
            
            if (is_string($part)) {
                // Handle case where part is directly a string
                $part_text = $part;
            } elseif (isset($part['text'])) {
                $part_text = $part['text'];
            } elseif (isset($part['content'])) {
                // Alternative content field
                $part_text = $part['content'];
            }
            
            // Only add non-empty text parts after trimming
            if (!empty(trim($part_text))) {
                $text_parts[] = trim($part_text);
                if (self::DEBUG_MODE) {
                    error_log("Added part {$index} text (length: " . strlen(trim($part_text)) . "): " . substr(trim($part_text), 0, 100) . "...");
                }
            } else {
                if (self::DEBUG_MODE) {
                    error_log("Skipped empty part {$index}");
                }
            }
        }
        
        $combined = implode(' ', $text_parts);
        if (self::DEBUG_MODE) {
            error_log('combineTextParts: found ' . count($text_parts) . ' non-empty parts');
            error_log('combineTextParts: combined result length: ' . strlen($combined));
            error_log('combineTextParts: first 500 chars: ' . substr($combined, 0, 500));
            if (strlen($combined) > 500) {
                error_log('combineTextParts: last 200 chars: ' . substr($combined, -200));
            }
        }
        
        return $combined;
    }
}