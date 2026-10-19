<?php
/* admin/trafico.php - VERSION 1.0
   Click counts, conversion per retiro, recent events, and IP blocking.
   Needs tracking_tables.sql and includes/antispam.php.
   1.2: suma los eventos de espacios (abrir_espacio, consulta_esp_ok) a los KPIs.
   1.1: usa el sidebar compartido en admin/_sidebar.php. El rango de dias se
   interpola (ya viene acotado a 1..365) porque un placeholder dentro de
   INTERVAL no es fiable con prepares del servidor. */

@ini_set('session.gc_maxlifetime', 7200);
require_once __DIR__.'/../includes/auth.php';
/* v1.3: solo superadmin. Esta pantalla muestra direcciones IP y user
   agents, que son datos personales. Admin no alcanza. */
exigirSesion();
/* v1.0: la version de Trafico para rol admin.
   Es trafico.php sin nada que muestre datos personales: no hay IPs, no hay
   user agents, no hay log de eventos en crudo y no se puede bloquear a nadie.
   Quien maneja el sitio igual ve de donde viene la gente y que convierte,
   que es lo unico que necesita para decidir donde poner el esfuerzo. */
require_once __DIR__.'/../includes/antispam.php';
@ini_set('display_errors', '1');
error_reporting(E_ALL);

$db = getDB();

/* Esta pagina es de solo lectura. Bloquear IPs y purgar eventos viven en
   seguridad.php, que es solo para superadmin. */

$dias  = max(1, min(365, (int)($_GET['dias'] ?? 30)));
$falta = false;
$totales = []; $porRetiro = []; $porRef = [];

if ($db) {
    try {
        $q = $db->prepare("SELECT tipo, COUNT(*) n FROM eventos
                           WHERE creado > (NOW() - INTERVAL {$dias} DAY) GROUP BY tipo");
        $q->execute();
        foreach ($q->fetchAll() as $r) { $totales[$r['tipo']] = (int)$r['n']; }

        $q = $db->prepare(
          "SELECT e.retiro_id, r.titulo,
                  SUM(e.tipo='abrir_retiro')  AS aperturas,
                  SUM(e.tipo='click_rsvp')    AS intentos,
                  SUM(e.tipo='rsvp_ok')       AS reservas,
                  SUM(e.tipo='rsvp_rechazado')AS rechazos,
                  SUM(e.tipo='click_wa')      AS whatsapp
           FROM eventos e LEFT JOIN retiros r ON r.id = e.retiro_id
           WHERE e.creado > (NOW() - INTERVAL {$dias} DAY) AND e.retiro_id IS NOT NULL
           GROUP BY e.retiro_id, r.titulo ORDER BY aperturas DESC");
        $q->execute();
        $porRetiro = $q->fetchAll();

        $q = $db->prepare("SELECT ref, COUNT(*) n, SUM(tipo='rsvp_ok') reservas FROM eventos
                           WHERE creado > (NOW() - INTERVAL {$dias} DAY) AND ref IS NOT NULL AND ref<>''
                           GROUP BY ref ORDER BY n DESC LIMIT 20");
        $q->execute();
        $porRef = $q->fetchAll();

    } catch (Exception $e) {
        $falta = true;
        error_log('[LR trafico] '.$e->getMessage());
    }
}

function pct($a, $b) { return $b > 0 ? round($a / $b * 100) . '%' : '-'; }
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<!-- admin/trafico.php VERSION 1.0 -->
<title>Trafico · Panel La Reserva</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300&family=Karla:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a1628;--bg2:#0d1e2c;--card:#112233;--hover:#152840;--teal:#00c9a7;--td:rgba(0,201,167,.12);--text:#e8f0f7;--text2:#7a9ab8;--text3:#4a6a85;--border:rgba(0,180,150,.15);--red:#ff5252;--amber:#ffab40}
*{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100vh;background:var(--bg2);color:var(--text);font-family:'Karla',sans-serif;font-size:14px}
a{text-decoration:none;color:inherit}
.layout{display:flex;min-height:100vh}
.sb{width:220px;flex-shrink:0;background:var(--bg);border-right:1px solid var(--border);display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto}
.sb-brand{padding:18px 16px 14px;border-bottom:1px solid var(--border)}
.sb-brand .name{font-family:'Fraunces',serif;font-weight:300;font-size:1.05rem}
.sb-brand .sub{font-size:.6rem;letter-spacing:.22em;color:var(--text3);margin-top:3px;text-transform:uppercase}
.sb-sect{padding:13px 16px 4px;font-size:.59rem;letter-spacing:.18em;color:var(--text3);font-weight:700;text-transform:uppercase}
.sb-nav{list-style:none;padding:0 8px 4px}
.sb-nav li a{display:block;padding:7px 10px;color:var(--text2);font-size:.83rem;border-radius:4px;border-left:2px solid transparent}
.sb-nav li a:hover{background:var(--td);color:var(--text)}
.sb-nav li a.active{background:var(--td);color:var(--teal);border-left-color:var(--teal)}
.sb-foot{margin-top:auto;padding:13px 16px;border-top:1px solid var(--border)}
.sb-foot a{display:block;font-size:.78rem;color:var(--text3);padding:3px 0}
.main{flex:1;min-width:0}
.topbar{background:var(--bg);border-bottom:1px solid var(--border);padding:13px 22px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.topbar h1{font-family:'Fraunces',serif;font-size:1.1rem;font-weight:300}
.content{padding:22px}
.aviso{padding:12px 18px;border-radius:4px;font-size:.88rem;margin-bottom:18px;border-left:3px solid}
.aviso.ok{background:rgba(0,201,167,.1);border-color:var(--teal);color:var(--teal)}
.aviso.error{background:rgba(255,82,82,.1);border-color:var(--red);color:var(--red)}
h2{font-family:'Fraunces',serif;font-weight:300;font-size:1rem;margin:26px 0 10px;color:var(--text)}
h2:first-of-type{margin-top:0}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:8px}
.kpi{background:var(--card);border:1px solid var(--border);border-radius:6px;padding:14px 16px}
.kpi .n{font-family:'Fraunces',serif;font-size:1.7rem;color:var(--teal);line-height:1.1}
.kpi .l{font-size:.7rem;letter-spacing:.1em;color:var(--text3);text-transform:uppercase;margin-top:4px}
table{width:100%;border-collapse:collapse;font-size:.83rem;background:var(--card);border:1px solid var(--border);border-radius:6px;overflow:hidden}
th{text-align:left;padding:9px 14px;background:var(--bg);color:var(--text3);font-size:.66rem;letter-spacing:.1em;font-weight:600;text-transform:uppercase;border-bottom:1px solid var(--border)}
td{padding:9px 14px;border-bottom:1px solid var(--border);vertical-align:middle}
tr:last-child td{border-bottom:0}tr:hover td{background:var(--hover)}
.mono{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.8rem}
.btn{display:inline-flex;align-items:center;padding:6px 13px;border-radius:4px;font:inherit;font-size:.75rem;cursor:pointer;border:0;font-weight:500}
.btn-t{background:var(--teal);color:#0a1628}
.btn-o{background:transparent;border:1px solid var(--border);color:var(--text2)}
.btn-d{background:rgba(255,82,82,.12);border:1px solid rgba(255,82,82,.25);color:var(--red)}
input[type=text]{font:inherit;font-size:.85rem;color:var(--text);background:var(--bg2);border:1px solid var(--border);border-radius:4px;padding:8px 11px}
.rango a{display:inline-block;padding:5px 12px;border-radius:16px;border:1px solid var(--border);color:var(--text2);font-size:.75rem;margin-right:6px}
.rango a.on{border-color:rgba(0,201,167,.35);background:var(--td);color:var(--teal)}
.nota{color:var(--text3);font-size:.78rem;margin-top:6px}
.tag{font-size:.68rem;padding:2px 8px;border-radius:12px;background:var(--td);color:var(--teal)}
.tag.red{background:rgba(255,82,82,.15);color:var(--red)}
</style>
</head>
<body>
<div class="layout">

<?php include __DIR__.'/_sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <h1>Trafico</h1>
    <div class="rango">
      <?php foreach ([7,30,90,365] as $d): ?>
        <a href="trafico.php?dias=<?= $d ?>" class="<?= $dias===$d?'on':'' ?>"><?= $d ?> dias</a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="content">
    <?php if ($falta): ?>
      <div class="aviso error">Faltan las tablas de tracking. Corre tracking_tables.sql en phpMyAdmin.</div>
    <?php endif; ?>

    <h2>Ultimos <?= $dias ?> dias</h2>
    <div class="cards">
      <?php
      $etq = ['abrir_retiro'=>'Retiros abiertos','abrir_espacio'=>'Espacios abiertos',
              'click_rsvp'=>'Formularios enviados','rsvp_ok'=>'Reservas guardadas',
              'consulta_esp_ok'=>'Consultas de espacio','rsvp_rechazado'=>'Rechazados',
              'consulta_esp_rechazada'=>'Rechazados espacio','click_wa'=>'Clicks WhatsApp'];
      foreach ($etq as $k => $lbl): ?>
        <div class="kpi"><div class="n"><?= (int)($totales[$k] ?? 0) ?></div><div class="l"><?= $lbl ?></div></div>
      <?php endforeach; ?>
    </div>
    <p class="nota">Un click de WhatsApp significa que la persona abrio wa.me. No hay forma de saber si
    despues envio el mensaje: WhatsApp no devuelve ninguna senal al sitio.</p>

    <h2>Por retiro</h2>
    <?php if ($porRetiro): ?>
    <table>
      <tr><th>Retiro</th><th>Aperturas</th><th>Envios</th><th>Reservas</th><th>Conversion</th><th>Rechazos</th><th>WhatsApp</th></tr>
      <?php foreach ($porRetiro as $r): ?>
      <tr>
        <td><?= e($r['titulo'] ?: '(retiro '.$r['retiro_id'].')') ?></td>
        <td><?= (int)$r['aperturas'] ?></td>
        <td><?= (int)$r['intentos'] ?></td>
        <td><strong style="color:var(--teal)"><?= (int)$r['reservas'] ?></strong></td>
        <td><?= pct((int)$r['reservas'], (int)$r['aperturas']) ?></td>
        <td><?= (int)$r['rechazos'] ?></td>
        <td><?= (int)$r['whatsapp'] ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?><p class="nota">Sin datos todavia.</p><?php endif; ?>

    <h2>Por origen (?ref=)</h2>
    <?php if ($porRef): ?>
    <table>
      <tr><th>ref</th><th>Eventos</th><th>Reservas</th></tr>
      <?php foreach ($porRef as $r): ?>
      <tr><td class="mono"><?= e($r['ref']) ?></td><td><?= (int)$r['n'] ?></td><td><?= (int)$r['reservas'] ?></td></tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p class="nota">Sin datos. Compartí un link con ?ref=algo al final, por ejemplo
    <span class="mono">https://lareserva.net.ar/retiros.php?ref=instagram</span>, y las visitas que lleguen
    por ahi quedan marcadas hasta que reservan.</p>
    <?php endif; ?>

    <div class="nota" style="margin-top:28px">
      Un click de WhatsApp significa que la persona abrio wa.me. No hay forma de
      saber si despues envio el mensaje: WhatsApp no devuelve ninguna senal al sitio.
    </div>
    <div class="nota">
      <strong>Concretadas</strong> cuenta las consultas que pasaste a coordinada o
      cerrada en la bandeja. Si no moves ese estado, esta columna queda en cero.
    </div>
</body>
</html>
