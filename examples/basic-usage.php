<?php

declare(strict_types=1);

/**
 * Example: Working with the ps_socialfollow PrestaShop module.
 *
 * ps_socialfollow renders social media follow buttons/icons in the storefront
 * footer (Facebook, Twitter/X, Instagram, YouTube, Pinterest, LinkedIn, etc.).
 * Links point to the merchant's social media profile pages.
 *
 * This file documents common usage patterns.
 */

// --- The module renders automatically in the footer ---
// Hook: displayFooter
// PrestaShop dispatches this hook automatically.

// --- Widget invocation in Smarty/Twig template ---
// {widget name="ps_socialfollow" hook="displayFooter"}

// --- Back Office configuration ---
// Modules > Follow us on social media:
//   - Facebook page URL
//   - Twitter/X profile URL
//   - Instagram profile URL
//   - YouTube channel URL
//   - Pinterest profile URL
//   - LinkedIn company page URL
//   - RSS feed URL
// Leave a field blank to hide that icon.

// --- Reading social URLs programmatically ---
// The module stores each URL in Configuration:
//
// $facebook  = Configuration::get('PS_SOCIAL_FACEBOOK');
// $twitter   = Configuration::get('PS_SOCIAL_TWITTER');
// $instagram = Configuration::get('PS_SOCIAL_INSTAGRAM');
// $youtube   = Configuration::get('PS_SOCIAL_YOUTUBE');
// $pinterest = Configuration::get('PS_SOCIAL_PINTEREST');
// $linkedin  = Configuration::get('PS_SOCIAL_LINKEDIN');
//
// if ($facebook) {
//     echo "Facebook: $facebook\n";
// }

// --- Template override ---
// themes/{theme}/modules/ps_socialfollow/views/templates/hook/ps_socialfollow.tpl
