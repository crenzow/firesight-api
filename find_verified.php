<?php
$dir = new RecursiveDirectoryIterator(__DIR__);
$ite = new RecursiveIteratorIterator($dir);
$files = new RegexIterator($ite, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

foreach($files as $file) {
    $path = $file[0];
    if (strpos($path, 'vendor') !== false || strpos($path, 'node_modules') !== false) continue;
    $content = file_get_contents($path);
    if (strpos($content, "'verified'") !== false || strpos($content, '"verified"') !== false) {
        echo $path . "\n";
    }
}
?>
