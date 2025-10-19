<?php

class GeminiConfig
{
    // Gemini model configuration bounds
    const GEMINI_MAX_TOKENS_MIN = 100;
    const GEMINI_MAX_TOKENS_MAX = 4096;
    const GEMINI_TEMPERATURE_MIN = 0.0;
    const GEMINI_TEMPERATURE_MAX = 2.0;
    
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
}