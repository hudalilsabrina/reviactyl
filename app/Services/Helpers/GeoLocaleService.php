<?php

namespace App\Services\Helpers;

class GeoLocaleService
{
    private const COUNTRY_LOCALE_MAP = [
        // Arabic
        'AE' => 'ar', 'BH' => 'ar', 'DZ' => 'ar', 'EG' => 'ar',
        'IQ' => 'ar', 'JO' => 'ar', 'KW' => 'ar', 'LB' => 'ar',
        'LY' => 'ar', 'MA' => 'ar', 'OM' => 'ar', 'QA' => 'ar',
        'SA' => 'ar', 'SD' => 'ar', 'SY' => 'ar', 'TN' => 'ar',
        'YE' => 'ar',

        // German
        'AT' => 'de', 'CH' => 'de', 'DE' => 'de', 'LI' => 'de',
        'LU' => 'de',

        // Spanish
        'AR' => 'es', 'BO' => 'es', 'CL' => 'es', 'CO' => 'es',
        'CR' => 'es', 'CU' => 'es', 'DO' => 'es', 'EC' => 'es',
        'ES' => 'es', 'GQ' => 'es', 'GT' => 'es', 'HN' => 'es',
        'MX' => 'es', 'NI' => 'es', 'PA' => 'es', 'PE' => 'es',
        'PR' => 'es', 'PY' => 'es', 'SV' => 'es', 'UY' => 'es',
        'VE' => 'es',

        // French
        'BE' => 'fr', 'BF' => 'fr', 'BI' => 'fr', 'BJ' => 'fr',
        'CD' => 'fr', 'CF' => 'fr', 'CG' => 'fr', 'CI' => 'fr',
        'CM' => 'fr', 'DJ' => 'fr', 'FR' => 'fr', 'GA' => 'fr',
        'GN' => 'fr', 'HT' => 'fr', 'MC' => 'fr', 'MG' => 'fr',
        'ML' => 'fr', 'MR' => 'fr', 'NE' => 'fr', 'RW' => 'fr',
        'SC' => 'fr', 'SN' => 'fr', 'TD' => 'fr', 'TG' => 'fr',

        // Hindi
        'IN' => 'hi',

        // Indonesian
        'ID' => 'id',

        // Portuguese
        'AO' => 'pt', 'BR' => 'pt', 'CV' => 'pt', 'GW' => 'pt',
        'MZ' => 'pt', 'PT' => 'pt', 'ST' => 'pt', 'TL' => 'pt',

        // Swedish
        'SE' => 'sv',

        // Turkish
        'TR' => 'tr',

        // Chinese
        'CN' => 'zh', 'HK' => 'zh', 'MO' => 'zh', 'TW' => 'zh',

        // English (explicit mappings for non-default-English countries)
        'AU' => 'en', 'CA' => 'en', 'GB' => 'en', 'IE' => 'en',
        'NZ' => 'en', 'PH' => 'en', 'SG' => 'en', 'US' => 'en',
        'ZA' => 'en',
    ];

    /**
     * Resolve a country code to the best available language locale.
     *
     * @param  string[]  $availableLocales
     */
    public function resolveLocale(string $countryCode, array $availableLocales): ?string
    {
        $locale = self::COUNTRY_LOCALE_MAP[strtoupper($countryCode)] ?? null;

        if ($locale !== null && in_array($locale, $availableLocales, true)) {
            return $locale;
        }

        return null;
    }
}
