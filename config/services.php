<?php

return [

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'telegram' => [
        'token' => env('TELEGRAM_BOT_TOKEN', ''),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET', ''),
        'queue_updates' => (bool) env('TELEGRAM_QUEUE_UPDATES', false),
        'admin_ids' => array_values(array_filter(array_map(
            static fn ($id) => (int) trim($id),
            explode(',', (string) env('TELEGRAM_ADMIN_IDS', ''))
        ))),
        'free_matches_per_day' => (int) env('FREE_MATCHES_PER_DAY', 10),
        'pack_credits' => (int) env('PAID_PACK_CREDITS', 50),
        'pack_stars' => (int) env('PAID_PACK_STARS', 100),
        'custom_payment_url' => env('CUSTOM_PAYMENT_URL', ''),
        'usage_timezone' => env('USAGE_TIMEZONE', 'UTC'),
        'message_rate_limit_per_minute' => (int) env('MESSAGE_RATE_LIMIT_PER_MINUTE', 25),
        'search_cooldown_seconds' => (int) env('SEARCH_COOLDOWN_SECONDS', 3),
        'queue_timeout_minutes' => (int) env('QUEUE_TIMEOUT_MINUTES', 30),
        'message_max_length' => (int) env('MESSAGE_MAX_LENGTH', 2000),
        'retention_days_metadata' => (int) env('RETENTION_DAYS_METADATA', 30),
        'terms_url' => env('TERMS_URL', 'http://localhost/terms'),
        'privacy_url' => env('PRIVACY_URL', 'http://localhost/privacy'),
        'support_contact' => env('SUPPORT_CONTACT', '@Support'),
        'minimum_age' => (int) env('MINIMUM_AGE', 18),
        'supported_languages' => [
            'en' => '🇬🇧 English',
            'es' => '🇪🇸 Español',
            'fr' => '🇫🇷 Français',
            'de' => '🇩🇪 Deutsch',
            'pt' => '🇧🇷 Português',
            'ru' => '🇷🇺 Русский',
            'ar' => '🇸🇦 العربية',
            'hi' => '🇮🇳 हिन्दी',
            'id' => '🇮🇩 Bahasa Indonesia',
            'tr' => '🇹🇷 Türkçe',
        ],
        'report_reasons' => [
            'spam' => '📢 Spam / Advertising',
            'harassment' => '🚫 Harassment / Abuse',
            'nsfw' => '🔞 Inappropriate / NSFW Content',
            'scam' => '⚠️ Scam / Phishing',
            'underage_safety' => '🛡️ Age / Safety Violation',
            'other' => '📝 Other Violation',
        ],
    ],

];
