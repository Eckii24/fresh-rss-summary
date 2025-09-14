# Debugging Guide for Gemini API Response Issues

## Quick Debug Steps

If you're experiencing "No text content found in API response" errors with newer Gemini models (gemini-2.5-flash, gemini-2.5-pro), follow these steps:

### Step 1: Enable Debug Mode
Edit `Controllers/SummaryController.php` and change line 9:
```php
// Change this:
private const DEBUG_MODE = false;

// To this:
private const DEBUG_MODE = true;
```

### Step 2: Test and Check Logs
1. Try to generate a summary with the problematic model
2. Check your FreshRSS error logs for detailed response structure
3. Look for log entries starting with "Gemini API Response Structure"

### Step 3: Use the Standalone Debug Script
```bash
cd /path/to/freshrss/extensions/xExtension-Summary/
export GEMINI_API_KEY="your-api-key-here"
php gemini_debug.php
```

This will test all models and show their exact response structures.

### Step 4: Share Results
If the issue persists, please share:
1. The debug log output showing the response structure
2. Which specific model is failing
3. The output from the debug script

## Expected Log Output

You should see logs like:
```
Gemini API Response Structure: {
  "candidates": [
    {
      "content": {
        "parts": [
          {
            "text": "Your summary here..."
          }
        ]
      }
    }
  ]
}
```

## Remember to Disable Debug Mode
After debugging, set `DEBUG_MODE = false` to avoid excessive logging in production.