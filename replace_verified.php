<?php
$files = [
    'dashboard/analytics.php',
    'incidents/create_manual.php',
    'incidents/update_status.php',
    'incidents/verify.php',
    'map/incidents.php',
    'map/incidents_bfp.php'
];

foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        $content = file_get_contents($path);
        $content = str_replace("'verified'", "'accepted'", $content);
        $content = str_replace('"verified"', '"accepted"', $content);
        file_put_contents($path, $content);
        echo "Updated $file\n";
    }
}
?>
