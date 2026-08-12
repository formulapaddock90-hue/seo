<?php

$username = 'Sql1936639';
$dbname = 'Sql1936639_2';
$hostname = '31.11.39.212';
$password = '7670i01h35';
$ftp_host = 'ftp.formulapaddock.it';
$ftp_user = '4746160@aruba.it';
$ftp_passw = 'Gattipc90!';

$settingsFile = __DIR__ . '/storage/settings.json';
$savedSettings = file_exists($settingsFile) ? (json_decode(file_get_contents($settingsFile), true) ?? []) : [];

$geminiApiKey = !empty($savedSettings['gemini_api_key']) ? $savedSettings['gemini_api_key'] : (getenv('GEMINI_API_KEY') ?: '');
$geminiModelUrl = !empty($savedSettings['gemini_model_url']) ? $savedSettings['gemini_model_url'] : 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';

return array (
  'auth_user' => 'admin',
  'auth_password' => 'Gattipc90!',
  'gemini_api_key' => $geminiApiKey,
  'gemini_model_url' => $geminiModelUrl,
  'gemini_models' => 
  array (
    'gemini-2.0-flash' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent',
    'gemini-1.5-flash' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent',
    'gemini-1.5-pro' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent',
    'gemini-2.0-flash-lite' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite:generateContent',
    'gemini-3.6-flash' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent',
    'gemini-3.5-flash' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash:generateContent',
  ),
  'google_service_account_email' => '',
  'google_service_account_private_key' => '',
  'google_service_account_key_file' => 'config/google-service-account.json',
  'buffer_access_token' => 'GFqEZiylc4lAEJvkXWa3Q1pcyFqzJL20yATjNTJsD4w',
  'timezone' => 'Europe/Rome',
  'openf1_base_url' => 'https://api.openf1.org/v1',
  'drive_output_dir' => '/home/urkv6v6p/domains/seo.formulapaddock.it/public_html/config/../storage/social',
  'drive_infografiche_folder_id' => '1L-fU8_IxVedWIUAuHSGmWUHuwnOB68YA',
  'media_dirs' => 
  array (
    0 => '/home/urkv6v6p/domains/seo.formulapaddock.it/public_html/config/../immagini',
    1 => '/home/urkv6v6p/domains/seo.formulapaddock.it/public_html/config/../uploads',
  ),
  'sitemaps' => 
  array (
    0 => 'https://www.formulapaddock.it/post-sitemap.xml',
    1 => 'https://www.formulapaddock.it/page-sitemap.xml',
    2 => 'https://www.formulapaddock.it/gran_premi-sitemap.xml',
    3 => 'https://www.formulapaddock.it/pirelli-sitemap.xml',
    4 => 'https://www.formulapaddock.it/evergreen-sitemap.xml',
  ),
  'db_username' => 'Sql1936639',
  'db_name' => 'Sql1936639_2',
  'db_hostname' => '31.11.39.212',
  'db_password' => '7670i01h35',
  'ftp_host' => 'ftp.formulapaddock.it',
  'ftp_user' => '4746160@aruba.it',
  'ftp_passw' => 'Gattipc90!',
  'sites' => 
  array (
    'formulapaddock' => 
    array (
      'label' => 'Formula Paddock',
      'url' => 'https://www.formulapaddock.it',
      'username' => 'admin',
      'application_password' => 'QQ18 XmzE Jn3O A7Vj NN1E I0PA',
      'default_category' => '',
      'default_parent_page' => '',
    ),
    'wec' => 
    array (
      'label' => 'Formula Paddock WEC',
      'url' => 'https://www.formulapaddock.it/wec',
      'username' => 'admin',
      'application_password' => '6dF8 xaPU hylZ Gv9d 9jXB PBw4',
      'default_category' => '',
      'default_parent_page' => '',
    ),
    'formula2' => 
    array (
      'label' => 'Formula Paddock Formula 2',
      'url' => 'https://www.formulapaddock.it/f2',
      'username' => 'admin',
      'application_password' => 'IwT6 IhJO mjZ2 YZ4f Fa1b nTy9',
      'default_category' => '',
      'default_parent_page' => '',
    ),
  ),
);