<?php

function dd($data)
{
    var_dump($data);
    die();
}

function slugify(string $text): string
{
    // Replace non-letter or digit characters with underscores
    $text = preg_replace('~[^\pL\d]+~u', '_', $text);
    // Transliterate to ASCII
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    // Remove unwanted characters
    $text = preg_replace('~[^_\w]+~', '', $text);
    // Trim and remove duplicate underscores
    $text = preg_replace('~_+~', '_', trim($text, '_'));
    // Lowercase the result
    return $text !== '' ? strtolower($text) : 'n_a';
}
