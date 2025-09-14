<?php
/*
 * Simplified Gemini API Response Debugger
 * 
 * This script can be used to test the actual response format from different Gemini models
 * Place this in your FreshRSS root directory and run it with a valid API key
 * 
 * Usage:
 * 1. Set your GEMINI_API_KEY environment variable or edit the script
 * 2. Run: php gemini_debug.php
 */

// Configuration - edit these values
$api_key = getenv('GEMINI_API_KEY') ?: 'your-api-key-here';
$models_to_test = [
    'gemini-1.5-flash',
    'gemini-2.5-flash', 
    'gemini-2.5-pro',
    'gemini-2.0-flash'
];

function testGeminiModel($model, $api_key) {
    // Test both API versions for newer models
    $api_versions = [];
    
    // Newer models that might need v1 API
    $v1_models = ['gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-2.0-flash'];
    
    if (in_array($model, $v1_models)) {
        $api_versions = [
            'v1' => "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key={$api_key}",
            'v1beta' => "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}"
        ];
    } else {
        $api_versions = [
            'v1beta' => "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}",
            'v1' => "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key={$api_key}"
        ];
    }
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => 'Write a short summary of artificial intelligence in 2 sentences.'
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 200,
            'topP' => 0.9,
            'topK' => 40
        ]
    ];

    $json_data = json_encode($data);
    
    // Try each API version
    foreach ($api_versions as $version => $url) {
        echo "  Testing {$version} API...\n";
        
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
            echo "    ❌ CURL Error: " . curl_error($ch) . "\n";
            continue;
        }
        
        curl_close($ch);

        if ($http_code !== 200) {
            echo "    ❌ HTTP {$http_code}: {$response}\n";
            continue;
        }

        $result = json_decode($response, true);
        
        if (!$result) {
            echo "    ❌ Invalid JSON response\n";
            continue;
        }
        
        echo "    ✅ Success with {$version} API!\n";
        return ['result' => $result, 'api_version' => $version];
    }
    
    return ['error' => 'Failed with all API versions'];
}

if ($api_key === 'your-api-key-here') {
    echo "Please set your GEMINI_API_KEY environment variable or edit this script with your API key.\n";
    exit(1);
}

foreach ($models_to_test as $model) {
    echo "\n" . str_repeat('=', 50) . "\n";
    echo "Testing model: {$model}\n";
    echo str_repeat('=', 50) . "\n";
    
    $result = testGeminiModel($model, $api_key);
    
    if (isset($result['error'])) {
        echo "❌ Error: {$result['error']}\n";
        continue;
    }
    
    $api_version = $result['api_version'];
    $response_data = $result['result'];
    
    echo "✅ Success with {$api_version} API! Response structure:\n";
    echo json_encode($response_data, JSON_PRETTY_PRINT) . "\n";
    
    // Analyze the structure
    if (isset($response_data['candidates'][0])) {
        $candidate = $response_data['candidates'][0];
        echo "\n--- Analysis ---\n";
        echo "Candidate keys: " . implode(', ', array_keys($candidate)) . "\n";
        
        // Test different access patterns
        $text_found = false;
        
        // Pattern 1: content.parts[0].text
        if (isset($candidate['content']['parts'][0]['text'])) {
            echo "✅ Pattern 1 works: content.parts[0].text\n";
            echo "   Text: " . substr($candidate['content']['parts'][0]['text'], 0, 100) . "...\n";
            $text_found = true;
        }
        
        // Pattern 2: parts[0].text
        if (isset($candidate['parts'][0]['text'])) {
            echo "✅ Pattern 2 works: parts[0].text\n";
            echo "   Text: " . substr($candidate['parts'][0]['text'], 0, 100) . "...\n";
            $text_found = true;
        }
        
        // Pattern 3: text
        if (isset($candidate['text'])) {
            echo "✅ Pattern 3 works: text\n";
            echo "   Text: " . substr($candidate['text'], 0, 100) . "...\n";
            $text_found = true;
        }
        
        // Pattern 4: content.text
        if (isset($candidate['content']['text'])) {
            echo "✅ Pattern 4 works: content.text\n";
            echo "   Text: " . substr($candidate['content']['text'], 0, 100) . "...\n";
            $text_found = true;
        }
        
        if (!$text_found) {
            echo "❌ No text found with standard patterns\n";
            echo "Full candidate structure:\n";
            echo json_encode($candidate, JSON_PRETTY_PRINT) . "\n";
        }
    }
}

echo "\nDebugging complete!\n";
?>