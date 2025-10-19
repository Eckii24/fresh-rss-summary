<?php

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
            . '<div class="gemini-summary-content"></div>'
            . '</div>'
            . $entry->content()
        );
        return $entry;
    }

    public function handleConfigureAction()
    {
        if (Minz_Request::isPost()) {
            $selected_model = Minz_Request::param('gemini_model', 'gemini-2.0-flash-latest');
            $custom_model = Minz_Request::param('gemini_model_custom', '');
            
            // If custom is selected and custom_model is provided, use it
            if ($selected_model === 'custom' && !empty($custom_model)) {
                FreshRSS_Context::$user_conf->gemini_model = trim($custom_model);
            } else {
                FreshRSS_Context::$user_conf->gemini_model = $selected_model;
            }
            
            FreshRSS_Context::$user_conf->gemini_api_key = Minz_Request::param('gemini_api_key', '');
            FreshRSS_Context::$user_conf->gemini_general_prompt = Minz_Request::param('gemini_general_prompt', '');
            FreshRSS_Context::$user_conf->gemini_youtube_prompt = Minz_Request::param('gemini_youtube_prompt', '');
            FreshRSS_Context::$user_conf->gemini_max_tokens = (int)Minz_Request::param('gemini_max_tokens', 1024);
            FreshRSS_Context::$user_conf->gemini_temperature = (float)Minz_Request::param('gemini_temperature', 0.7);
            FreshRSS_Context::$user_conf->save();
        }
    }
}