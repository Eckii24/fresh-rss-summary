# FreshRSS Summary Extension - Installation Guide

## Quick Installation

1. **Download the Extension**
   - Clone or download this repository
   - Copy all files to your FreshRSS extensions directory: `[FreshRSS]/extensions/xExtension-Summary/`

2. **Enable the Extension**
   - In FreshRSS, go to Settings → Extensions
   - Find "Summary" in the list and click "Enable"

3. **Configure Google Gemini**
   - Click "Configure" next to the Summary extension
   - Get your API key from [Google AI Studio](https://ai.google.dev/)
   - Enter your API key and customize settings

4. **Test the Extension**
   - Open any article in FreshRSS
   - Click the "Summary" button at the top of the article
   - Wait for the AI-generated summary to appear

## Directory Structure
```
[FreshRSS]/extensions/xExtension-Summary/
├── metadata.json              # Extension metadata
├── extension.php             # Main extension class
├── configure.phtml           # Configuration form
├── Controllers/
│   └── SummaryController.php # API request handler
├── static/
│   ├── style.css            # UI styling
│   └── script.js            # Frontend functionality
└── README.md               # Documentation
```

## Configuration Options

- **API Key**: Your Google Gemini API key (required)
- **Model**: Choose between Flash (fast) or Pro (higher quality)
- **General Prompt**: Custom prompt for regular articles
- **YouTube Prompt**: Custom prompt for YouTube videos
- **Max Tokens**: Maximum length of generated summaries (100-4096)
- **Temperature**: Controls creativity (0.0-2.0, recommended: 0.7)

## Troubleshooting

### Extension Not Showing
- Check file permissions (PHP needs read access)
- Ensure correct directory structure
- Check FreshRSS logs for errors

### API Errors
- Verify your Gemini API key is correct
- Check your Google Cloud API quotas
- Ensure your server can make HTTPS requests

### No Summary Button
- Refresh the page after enabling the extension
- Check browser console for JavaScript errors
- Verify the extension is properly enabled

## Support

For issues, check:
1. FreshRSS logs (`[FreshRSS]/data/logs/`)
2. Browser developer console
3. PHP error logs
4. Google Gemini API status

## Requirements

- FreshRSS 1.19.0+
- PHP 7.4+ with cURL
- Google Gemini API key (free tier available)
- Internet connection for API calls