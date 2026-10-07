<?php
require_once __DIR__.'/includes/config.php';
header('Content-Type: application/xml; charset=UTF-8');

$urls = [
  ['index.php',   '1.0', 'weekly'],
  ['retiros.php', '0.9', 'weekly'],
  ['espacios.php','0.8', 'monthly'],
  ['galeria.php', '0.6', 'monthly'],
  ['contacto.php','0.7', 'yearly'],
];

$retiros = [];
if ($db = getDB()) {
  try {
    $retiros = $db->query("SELECT slug,actualizado FROM retiros
                           WHERE estado IN('publicado','agotado')
                           ORDER BY fecha_inicio DESC LIMIT 200")->fetchAll();
  } catch (Exception $e) {}
}

echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as [$u,$p,$f]): ?>
  <url>
    <loc><?= SITE_URL ?>/<?= $u === 'index.php' ? '' : substr($u, 0, -4) ?></loc>
    <changefreq><?= $f ?></changefreq>
    <priority><?= $p ?></priority>
  </url>
<?php endforeach; ?>
<?php foreach ($retiros as $r): ?>
  <url>
    <loc><?= SITE_URL ?>/retiro?r=<?= urlencode($r['slug']) ?></loc>
    <lastmod><?= date('Y-m-d', strtotime($r['actualizado'])) ?></lastmod>
    <priority>0.7</priority>
  </url>
<?php endforeach; ?>
</urlset>
