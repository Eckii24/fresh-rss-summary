# FreshRSS Summary Extension

An AI-powered article summarization extension for FreshRSS using Google Gemini, with support for YouTube video understanding.

## Features

- **Smart Summarization**: Automatically generates concise summaries of articles using Google Gemini AI
- **YouTube Support**: Detects YouTube videos and provides specialized summaries 
- **Dual Prompts**: Separate customizable prompts for regular articles and YouTube videos
- **Easy Toggle**: Summary button at the top of each article with show/hide functionality
- **Multiple Models**: Support for various Gemini models (Flash, Pro, etc.)

## Installation

1. Download or clone this extension to your FreshRSS extensions directory:
   ```
   /path/to/freshrss/extensions/xExtension-Summary/
   ```

2. Enable the extension in FreshRSS:
   - Go to Settings → Extensions
   - Find "Summary" in the list and enable it

3. Configure the extension:
   - Click "Configure" next to the Summary extension
   - Enter your Google Gemini API key (get one from [Google AI Studio](https://ai.google.dev/))
   - Choose your preferred Gemini model
   - Customize the prompts for general articles and YouTube videos

## Configuration

### API Key
Get your free Google Gemini API key from [Google AI Studio](https://ai.google.dev/). The extension supports the latest Gemini models:
- Gemini 2.0 Flash Latest (recommended for speed with latest features)
- Gemini 2.0 Pro Latest (premium model with better quality)

**Note**: Model availability may vary. For the most up-to-date list of available models, check the [Official Gemini API Models Documentation](https://ai.google.dev/gemini-api/docs/models/gemini). If the 2.0 models are not yet available, the extension will fall back to stable 1.5 models.

### Prompts
- **General Articles Prompt**: Used for regular text articles and blog posts
- **YouTube Videos Prompt**: Used specifically for YouTube videos (includes video metadata)

## Usage

1. Open any article in FreshRSS
2. Click the "Summary" button at the top of the article
3. Wait for the AI to generate the summary
4. Click "Hide Summary" to toggle the summary visibility
5. For YouTube videos, you'll see a special ▶ indicator

## Technical Details

- **YouTube Detection**: Automatically detects YouTube URLs using regex patterns
- **Video Processing**: Extracts video metadata (title, description) for better context
- **Error Handling**: Comprehensive error handling with user-friendly messages
- **Responsive Design**: Clean, mobile-friendly interface
- **Security**: Secure API key storage and CSRF protection

## Requirements

- FreshRSS 1.19.0 or later
- PHP 7.4 or later with cURL support
- Google Gemini API key

## License

MIT License - see LICENSE file for details.
