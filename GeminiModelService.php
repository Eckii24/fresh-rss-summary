<?php

class GeminiModelService
{
    private const CACHE_TTL = 3600; // 1 hour cache
    private const GEMINI_MODELS_API = 'https://generativelanguage.googleapis.com/v1beta/models';
    
    /**
     * Get Gemini models with caching support
     */
    public static function getModels($apiKey)
    {
        if (empty($apiKey)) {
            return ['models' => [], 'error' => ''];
        }
        
        // Check cache first
        $cacheKey = 'gemini_models_' . md5($apiKey);
        $cached = self::getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        // Fetch models from API
        $result = self::fetchModelsFromAPI($apiKey);
        
        // Cache the result
        self::setCache($cacheKey, $result);
        
        return $result;
    }
    
    private static function fetchModelsFromAPI($apiKey)
    {
        $models = [];
        $error = '';
        
        try {
            $baseUrl = self::GEMINI_MODELS_API;
            $pageToken = null;
            
            do {
                $url = $baseUrl . '?pageSize=1000';
                if (!empty($pageToken)) {
                    $url .= '&pageToken=' . urlencode($pageToken);
                }
                
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 15,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_HTTPHEADER => [
                        'x-goog-api-key: ' . $apiKey,
                    ],
                ]);
                
                $resp = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_err = curl_error($ch);
                curl_close($ch);

                if (!empty($curl_err) || $http_code !== 200) {
                    $error = 'Unable to fetch models list (HTTP ' . intval($http_code) . ')'
                        . (!empty($curl_err) ? ' - ' . mb_substr($curl_err, 0, 200) : '') . '.';
                    break;
                }

                $payload = json_decode($resp, true);
                if (isset($payload['models']) && is_array($payload['models'])) {
                    foreach ($payload['models'] as $m) {
                        $supported = $m['supportedGenerationMethods'] ?? [];
                        if (!in_array('generateContent', $supported, true)) {
                            continue; // Only show models that support generateContent
                        }
                        $name = $m['name'] ?? null; // Full model resource name: models/xxx
                        $baseId = $m['baseModelId'] ?? null; // Friendly id
                        $display = $m['displayName'] ?? $baseId ?? $name;
                        if ($name) {
                            // Prefer using full resource name to avoid version mismatches
                            $models[$name] = $display;
                        } elseif ($baseId) {
                            $models[$baseId] = $display;
                        }
                    }
                }
                $pageToken = $payload['nextPageToken'] ?? null;
            } while (!empty($pageToken));

            if (empty($models)) {
                if (empty($error)) {
                    $error = 'No models supporting generateContent were returned.';
                }
            } else {
                asort($models, SORT_NATURAL | SORT_FLAG_CASE);
            }
        } catch (Exception $e) {
            $error = 'Error while loading models: ' . $e->getMessage();
        }
        
        return ['models' => $models, 'error' => $error];
    }
    
    private static function getFromCache($key)
    {
        $cacheFile = sys_get_temp_dir() . '/freshrss_gemini_' . $key . '.cache';
        
        if (!file_exists($cacheFile)) {
            return null;
        }
        
        $cacheData = file_get_contents($cacheFile);
        if ($cacheData === false) {
            return null;
        }
        
        $cached = json_decode($cacheData, true);
        if (!is_array($cached) || !isset($cached['timestamp'], $cached['data'])) {
            return null;
        }
        
        // Check if cache is expired
        if (time() - $cached['timestamp'] > self::CACHE_TTL) {
            unlink($cacheFile);
            return null;
        }
        
        return $cached['data'];
    }
    
    private static function setCache($key, $data)
    {
        $cacheFile = sys_get_temp_dir() . '/freshrss_gemini_' . $key . '.cache';
        $cacheData = json_encode([
            'timestamp' => time(),
            'data' => $data
        ]);
        
        file_put_contents($cacheFile, $cacheData);
    }
}