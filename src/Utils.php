<?php

// Global methodds helper. This file is loaded automatically
function is_associative_array($a) {
    if (!is_array($a)) return false;
    foreach (array_keys($a) as $key) { if (!is_int($key)) return true;  }
    return false;
}

function startsWith( $haystack, $needle ) {
    $length = strlen( $needle );
    return substr( $haystack, 0, $length ) === $needle;
}

function endsWith( $haystack, $needle ) {
   $length = strlen( $needle );
   if( !$length ) {
       return true;
   }
   return substr( $haystack, -$length ) === $needle;
}

function slugify($text, $lowercase = true) {
    if ($lowercase) $text = strtolower($text); // lowercase
    
    $text = str_replace('é', 'e', $text);
    $text = str_replace('è', 'e', $text);
    $text = str_replace('ê', 'e', $text);
    $text = str_replace('ô', 'o', $text);
    $text = str_replace('ç', 'c', $text);
    $text = str_replace('à', 'a', $text);
    $text = str_replace('â', 'a', $text);
    $text = str_replace('î', 'i', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text); // transliterate
    $text = preg_replace('~[^\pL\d]+~u', '_', $text); // replace non letter by _
    $text = trim($text, '_'); // trim
    $text = preg_replace('~_+~', '_', $text); // remove duplicate -

    if (empty($text)) {
        return '';
    }

    return $text;
}

function getStreamContextOptions($options=[]) {
    $streamContextOptions = [];
    if (is_array($options)) {
        $streamContextOptions = array_merge($streamContextOptions, $options);
    }
    if ($_ENV['NO_SSL_CHECK'] === 'true') {
        $streamContextOptions = array_merge($streamContextOptions, [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);
    }
    return (stream_context_create($streamContextOptions));
}