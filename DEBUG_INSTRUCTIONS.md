# Debugging Guide for Gemini API Response Issues

## Quick Debug Steps

If you're experiencing "No text content found in API response" errors with newer Gemini models (gemini-2.5-flash, gemini-2.5-pro), follow these steps:

### What's Fixed in Latest Version
- ✅ **API Endpoint Detection**: Automatically uses v1 API for newer models, v1beta for older ones
- ✅ **Multiple Response Formats**: Supports 8 different response structure patterns
- ✅ **Request Format Fallback**: Tries alternative request formats if primary fails
- ✅ **Enhanced Error Handling**: Better error messages and debugging information

### Step 1: Test with Latest Version
Try generating a summary first - the latest code should handle most issues automatically.

### Step 2: Enable Debug Mode (if still having issues)
Edit `Controllers/SummaryController.php` and change line 12:
```php
// Change this:
private const DEBUG_MODE = false;

// To this:
private const DEBUG_MODE = true;
```

### Step 3: Test and Check Logs
1. Try to generate a summary with the problematic model
2. Check your FreshRSS error logs for detailed response structure
3. Look for log entries showing:
   - Which API version is being used (v1 vs v1beta)
   - The exact response structure from Gemini
   - Which parsing format succeeded or failed

### Step 4: Use the Standalone Debug Script
```bash
cd /path/to/freshrss/extensions/xExtension-Summary/
export GEMINI_API_KEY="your-api-key-here"
php gemini_debug.php
```

This will test all models with both v1 and v1beta APIs and show their exact response structures.

### Step 5: Share Results
If the issue persists, please share:
1. The debug log output showing the response structure
2. Which specific model is failing
3. The output from the debug script
4. Which API version (v1 or v1beta) worked/failed

## Expected Behavior

The extension should automatically:
1. **Use v1 API** for: gemini-2.5-flash, gemini-2.5-pro, gemini-2.0-flash
2. **Use v1beta API** for: older models like gemini-1.5-flash, gemini-1.5-pro
3. **Try alternative request formats** if the primary format fails
4. **Parse multiple response structures** to find the actual text content

## Example Log Output

You should see logs like:
```
Using v1 API for model: gemini-2.5-flash
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
Successfully extracted text using Format 1
```

## Remember to Disable Debug Mode
After debugging, set `DEBUG_MODE = false` to avoid excessive logging in production.