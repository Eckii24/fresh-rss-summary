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
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";
    
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
        return ['error' => 'CURL Error: ' . curl_error($ch)];
    }
    
    curl_close($ch);

    if ($http_code !== 200) {
        return ['error' => "HTTP {$http_code}: {$response}"];
    }

    $result = json_decode($response, true);
    
    if (!$result) {
        return ['error' => 'Invalid JSON response'];
    }
    
    return $result;
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
    
    echo "✅ Success! Response structure:\n";
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    
    // Analyze the structure
    if (isset($result['candidates'][0])) {
        $candidate = $result['candidates'][0];
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