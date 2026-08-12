<?php

declare(strict_types=1);

return [
  'app' => [
    'name' => 'Dashboard Marketing',
    'timezone' => 'America/Bogota',
    'base_path' => '',
    'public_url' => getenv('SKC_PUBLIC_URL') ?: '',
  ],
  'db' => [
    'host' => getenv('SKC_DB_HOST') ?: 'localhost',
    'username' => getenv('SKC_DB_USER') ?: '',
    'password' => getenv('SKC_DB_PASS') ?: '',
    'database' => getenv('SKC_DB_NAME') ?: '',
    'charset' => getenv('SKC_DB_CHARSET') ?: 'utf8mb4',
    'prefix' => getenv('SKC_DB_PREFIX') ?: 'wp_',
  ],
  'auth' => [
    'magic_login_token' => getenv('SKC_MAGIC_LOGIN_TOKEN') ?: '',
  ],
  'inventory' => [
    'api_token' => getenv('SKC_INVENTORY_API_TOKEN') ?: '',
  ],
  'meta' => [
    'graph_version' => getenv('META_GRAPH_VERSION') ?: 'v25.0',
    'access_token' => getenv('META_ACCESS_TOKEN') ?: '',
    'instagram_user_id' => getenv('META_IG_USER_ID') ?: '',
    'facebook_page_id' => getenv('META_FB_PAGE_ID') ?: '',
    'facebook_page_access_token' => getenv('META_FB_PAGE_ACCESS_TOKEN') ?: '',
  ],
  'youtube' => [
    'client_id' => getenv('GOOGLE_YOUTUBE_CLIENT_ID') ?: '',
    'client_secret' => getenv('GOOGLE_YOUTUBE_CLIENT_SECRET') ?: '',
    'redirect_uri' => getenv('GOOGLE_YOUTUBE_REDIRECT_URI') ?: 'https://sucasainmobiliaria.com.co/dashboard-marketing/youtube/callback',
    'channel_id' => getenv('GOOGLE_YOUTUBE_CHANNEL_ID') ?: '',
    'channel_name' => getenv('GOOGLE_YOUTUBE_CHANNEL_NAME') ?: '',
    'token_key' => getenv('GOOGLE_YOUTUBE_TOKEN_KEY') ?: '',
  ],
  'tiktok' => [
    'client_key' => getenv('TIKTOK_CLIENT_KEY') ?: '',
    'client_secret' => getenv('TIKTOK_CLIENT_SECRET') ?: '',
    'redirect_uri' => getenv('TIKTOK_REDIRECT_URI') ?: 'https://sucasainmobiliaria.com.co/dashboard-marketing/tiktok/callback',
    'scopes' => getenv('TIKTOK_SCOPES') ?: 'user.info.basic,video.list',
    'token_key' => getenv('TIKTOK_TOKEN_KEY') ?: '',
  ],
  'tracking' => [
    'geo_lookup' => (getenv('SKC_TRACKING_GEO') ?: '1') !== '0',
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', getenv('SKC_TRACKING_ALLOWED_ORIGINS') ?: 'https://sucasainmobiliaria.com.co,https://www.sucasainmobiliaria.com.co,http://127.0.0.1:8080,http://localhost:8080')))),
  ],
];
