<?php

class GeminiConfig
{
    // Gemini model configuration bounds
    const GEMINI_MAX_TOKENS_MIN = 100;
    const GEMINI_MAX_TOKENS_MAX = 8192;
    const GEMINI_TEMPERATURE_MIN = 0.0;
    const GEMINI_TEMPERATURE_MAX = 2.0;
    const GEMINI_REQUEST_TIMEOUT_MIN = 10;
    const GEMINI_REQUEST_TIMEOUT_MAX = 300;
    const GEMINI_REQUEST_TIMEOUT_DEFAULT = 60;
    
    /**
     * Clamp max tokens to valid range
     */
    public static function clampMaxTokens($value)
    {
        $value = (int)$value;
        if ($value < self::GEMINI_MAX_TOKENS_MIN) {
            return self::GEMINI_MAX_TOKENS_MIN;
        }
        if ($value > self::GEMINI_MAX_TOKENS_MAX) {
            return self::GEMINI_MAX_TOKENS_MAX;
        }
        return $value;
    }
    
    /**
     * Clamp temperature to valid range
     */
    public static function clampTemperature($value)
    {
        $value = (float)$value;
        if ($value < self::GEMINI_TEMPERATURE_MIN) {
            return self::GEMINI_TEMPERATURE_MIN;
        }
        if ($value > self::GEMINI_TEMPERATURE_MAX) {
            return self::GEMINI_TEMPERATURE_MAX;
        }
        return $value;
    }
    
    /**
     * Clamp request timeout to valid range
     */
    public static function clampRequestTimeout($value)
    {
        $value = (int)$value;
        if ($value < self::GEMINI_REQUEST_TIMEOUT_MIN) {
            return self::GEMINI_REQUEST_TIMEOUT_MIN;
        }
        if ($value > self::GEMINI_REQUEST_TIMEOUT_MAX) {
            return self::GEMINI_REQUEST_TIMEOUT_MAX;
        }
        return $value;
    }
}