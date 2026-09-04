<?php

class Settings
{
    /**
     * Get one setting
     */
    public static function get($key, $default = null)
    {
        return getSetting($key, $default);
    }

    /**
     * Save one setting
     */
    public static function set($key, $value, $userId = null)
    {
        return setSetting($key, $value, $userId);
    }

    /**
     * Get all settings
     */
    public static function all()
    {
        $rows = fetchAll("
            SELECT
                setting_key,
                setting_value
            FROM settings
            ORDER BY setting_key
        ");

        $settings = [];

        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        return $settings;
    }
}