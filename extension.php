<?php

require_once __DIR__ . '/GeminiConfig.php';
require_once __DIR__ . '/GeminiModelService.php';

class SummaryExtension extends Minz_Extension
{
    protected array $csp_policies = [
        'default-src' => '*',
    ];

    public function init()
    {
        $this->registerHook('entry_before_display', array($this, 'addSummaryButton'));
        $this->registerController('Summary');
        Minz_View::appendStyle($this->getFileUrl('style.css', 'css'));
        Minz_View::appendScript($this->getFileUrl('script.js', 'js'));
    }

    public function addSummaryButton($entry)
    {
        $url_summary = Minz_Url::display(array(
            'c' => 'Summary',
            'a' => 'summarize',
            'params' => array(
                'id' => $entry->id()
            )
        ));

        $entry->_content(
            '<div class="gemini-summary-wrap">'
            . '<button data-request="' . $url_summary . '" class="gemini-summary-btn">Summary</button>'
            . '<input type="text" class="gemini-custom-prompt" placeholder="Optional: Enter your custom question or prompt here..." />'
            . '<div class="gemini-summary-content"></div>'
            . '</div>'
            . $entry->content()
        );
        return $entry;
    }

    public function handleConfigureAction()
    {
        if (Minz_Request::isPost()) {
            FreshRSS_Context::$user_conf->gemini_api_key = Minz_Request::param('gemini_api_key', '');
            FreshRSS_Context::$user_conf->gemini_model = Minz_Request::param('gemini_model', 'gemini-2.5-flash');
            FreshRSS_Context::$user_conf->gemini_general_prompt = Minz_Request::param('gemini_general_prompt', '');
            FreshRSS_Context::$user_conf->gemini_youtube_prompt = Minz_Request::param('gemini_youtube_prompt', '');
            
            // Use shared clamping logic
            $maxTok = GeminiConfig::clampMaxTokens(Minz_Request::param('gemini_max_tokens', 1024));
            FreshRSS_Context::$user_conf->gemini_max_tokens = $maxTok;
            
            $temp = GeminiConfig::clampTemperature(Minz_Request::param('gemini_temperature', 0.7));
            FreshRSS_Context::$user_conf->gemini_temperature = $temp;
            
            FreshRSS_Context::$user_conf->save();
        }
        
        // Prepare models data for the view
        $api_key = FreshRSS_Context::$user_conf->gemini_api_key ?? '';
        $modelData = GeminiModelService::getModels($api_key);
        $this->registerViewVariable('gemini_models', $modelData['models']);
        $this->registerViewVariable('gemini_models_error', $modelData['error']);
    }
    
    private function registerViewVariable($name, $value)
    {
        // Store in a global that the view can access
        $GLOBALS['summary_extension_vars'][$name] = $value;
    }
}