<?php
header('Content-Type: text/plain; charset=utf-8');
$file = __DIR__ . '/config/db.php';
echo "File path: $file\n";
echo "Exists: " . (file_exists($file) ? 'yes' : 'no') . "\n";
echo "Line 91 contents: " . file($file)[90] . "\n";
echo "Line 92 contents: " . file($file)[91] . "\n";
echo "Line 93 contents: " . file($file)[92] . "\n";
echo "Size: " . filesize($file) . "\n";
echo "Full content of getDB function:\n";
$lines = file($file);
for ($i = 78; $i < 112; $i++) {
    echo ($i+1) . ": " . $lines[$i];
}
