<?php

return [
    /*
     * Captcha provider to use: 'none', 'recaptcha', or 'turnstile'
     */
    'provider' => env('CAPTCHA_PROVIDER', 'recaptcha'),

    /*
     * Google reCAPTCHA settings
     */
    'recaptcha' => [
        'domain' => env('RECAPTCHA_DOMAIN', 'https://www.google.com/recaptcha/api/siteverify'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY', '6LcJcjwUAAAAALOcDJqAEYKTDhwELCkzUkNDQ0J5'),
        'website_key' => env('RECAPTCHA_WEBSITE_KEY', '6LcJcjwUAAAAAO_Xqjrtj9wWufUpYRnK6BW8lnfn'),
        '_shipped_secret_key' => '6LcJcjwUAAAAALOcDJqAEYKTDhwELCkzUkNDQ0J5',
        '_shipped_website_key' => '6LcJcjwUAAAAAO_Xqjrtj9wWufUpYRnK6BW8lnfn',
    ],

    /*
     * Cloudflare Turnstile settings
     */
    'turnstile' => [
        'domain' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        'secret_key' => env('TURNSTILE_SECRET_KEY', ''),
        'site_key' => env('TURNSTILE_SITE_KEY', ''),
    ],

    /*
     * Domain verification is enabled by default and compares the domain used when solving the captcha
     * as public keys can't have domain verification on google's side enabled (obviously).
     */
    'verify_domain' => true,
];
