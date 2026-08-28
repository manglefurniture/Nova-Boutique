<?php
declare(strict_types=1);

$lockPath = dirname(__DIR__) . '/deploy/gallery.lock';
$handle = fopen($lockPath, 'r');
if ($handle === false) {
    throw new RuntimeException('No se pudo abrir el lock de galería.');
}

if (!flock($handle, LOCK_EX)) {
    fclose($handle);
    throw new RuntimeException('PHP no pudo adquirir el lock de galería.');
}

$command = 'flock -n ' . escapeshellarg($lockPath) . ' -c true';
exec($command, $output, $statusWhilePhpLocked);
if ($statusWhilePhpLocked === 0) {
    flock($handle, LOCK_UN);
    fclose($handle);
    throw new RuntimeException('El flock de shell ignoró el lock exclusivo de PHP.');
}

flock($handle, LOCK_UN);
fclose($handle);

exec($command, $outputAfterRelease, $statusAfterRelease);
if ($statusAfterRelease !== 0) {
    throw new RuntimeException('El flock de shell no pudo adquirir el lock después de liberarlo.');
}

echo "Nova gallery lock interoperability: OK\n";
