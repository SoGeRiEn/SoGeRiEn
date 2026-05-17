<?php
declare(strict_types=1);

$query = $_GET;
unset($query['q']);
$target = '/client/my/proxies';
if ($query !== []) {
    $target .= '?' . http_build_query($query);
}
header('Location: ' . $target, true, 302);
Sogerien::markDone();
