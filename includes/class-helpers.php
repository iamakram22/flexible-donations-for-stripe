<?php
/**
 * Helper functions for Flexible Donations for Stripe
 */

if (!defined('ABSPATH')) {
    exit;
}

class FDFS_Helpers
{
    /**
     * Get currency symbol from ISO currency code
     *
     * @param string $currency 3-letter ISO currency code
     * @return string Currency symbol or uppercase code as fallback
     */
    public static function get_currency_symbol($currency)
    {
        $symbols = array(
            'usd' => '$', 'eur' => '€', 'gbp' => '£', 'jpy' => '¥',
            'inr' => '₹', 'cad' => 'CA$', 'aud' => 'A$', 'chf' => 'CHF',
            'cny' => '¥', 'sgd' => 'S$', 'hkd' => 'HK$', 'nzd' => 'NZ$',
            'sek' => 'kr', 'nok' => 'kr', 'dkk' => 'kr', 'pln' => 'zł',
            'czk' => 'Kč', 'huf' => 'Ft', 'ron' => 'lei', 'bgn' => 'лв',
            'hrk' => 'kn', 'isk' => 'kr', 'try' => '₺', 'zar' => 'R',
            'brl' => 'R$', 'mxn' => 'MX$', 'clp' => 'CLP$', 'cop' => 'COP$',
            'pen' => 'S/', 'uyu' => '$U', 'ars' => 'ARS$', 'krw' => '₩',
            'twd' => 'NT$', 'thb' => '฿', 'vnd' => '₫', 'idr' => 'Rp',
            'myr' => 'RM', 'php' => '₱', 'ils' => '₪', 'aed' => 'AED',
            'sar' => 'SAR', 'qar' => 'QR', 'kwd' => 'KD', 'bhd' => 'BD',
            'omr' => 'OMR', 'jod' => 'JD', 'egp' => 'E£', 'mad' => 'MAD',
            'tnd' => 'TND', 'ngn' => '₦', 'ghs' => 'GH₵', 'kes' => 'KSh',
            'ugx' => 'USh', 'tzs' => 'TSh', 'zmw' => 'ZMW', 'mwk' => 'MK',
            'bwp' => 'P', 'mur' => '₨', 'scr' => '₨', 'mvr' => 'MVR',
            'lkr' => '₨', 'npr' => '₨', 'bdt' => '৳', 'pkr' => '₨',
            'afn' => '؋', 'gel' => '₾', 'amd' => '֏', 'azn' => '₼',
            'bys' => 'Br', 'mdl' => 'MDL', 'all' => 'Lek', 'mkd' => 'ден',
            'rsd' => 'дин', 'bam' => 'KM',
        );
        return $symbols[strtolower($currency)] ?? strtoupper($currency);
    }
}

// Backward compatibility function
function fdfs_get_currency_symbol($currency)
{
    return FDFS_Helpers::get_currency_symbol($currency);
}