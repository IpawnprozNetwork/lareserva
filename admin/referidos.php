<?php
/* admin/referidos.php - VERSION 1.4
   Referral codes, the funnel per code, and a manual payout ledger.
   Needs referidos_tables.sql, plus eventos and consultas.

   Nothing here computes money owed. The system has no payment data: a
   consulta is an enquiry, not a paid booking, and espacio enquiries carry no
   price at all. Commission is a text note and payouts are recorded by hand. */

@ini_set('session.gc_maxlifetime', 7200);
require_once __DIR__.'/../includes/auth.php';
exigirSesion();
@ini_set('display_errors', '1');
error_reporting(E_ALL);

$db = getDB();
$aviso = null;
$falta = false;

function slugCodigo(string $s): string {
    $s = strtolower(trim($s));
    $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u']);
    $s = preg_replace('/[^a-z0-9._-]+/', '-', $s);
    return substr(trim($s, '-'), 0, 40);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db) {
    if (!csrfOk($_POST['csrf'] ?? null)) {
        $aviso = ['error', 'Sesion expirada.'];
    } else {
        $accion = $_POST['accion'] ?? '';
        try {
            if ($accion === 'crear' || $accion === 'editar') {
                $nombre = limpiar($_POST['nombre'] ?? '');
                $codigo = slugCodigo($_POST['codigo'] ?? '') ?: slugCodigo($nombre);
                if ($nombre === '' || $codigo === '') {
                    $aviso = ['error', 'Faltan el nombre o el codigo.'];
                } elseif ($accion === 'crear') {
                    $db->prepare("INSERT INTO referidos (codigo,nombre,contacto,comision,notas)
                                  VALUES (:c,:n,:ct,:cm,:no)")
                       ->execute([':c'=>$codigo, ':n'=>$nombre,
                                  ':ct'=>limpiar($_POST['contacto'] ?? '') ?: null,
                                  ':cm'=>limpiar($_POST['comision'] ?? '') ?: null,
                                  ':no'=>limpiar($_POST['notas'] ?? '') ?: null]);
                    $aviso = ['ok', 'Codigo creado: '.$codigo];
                } else {
                    $db->prepare("UPDATE referidos SET nombre=:n, contacto=:ct, comision=:cm,
                                  notas=:no, activo=:a WHERE id=:i")
                       ->execute([':n'=>$nombre,
                                  ':ct'=>limpiar($_POST['contacto'] ?? '') ?: null,
                                  ':cm'=>limpiar($_POST['comision'] ?? '') ?: null,
                                  ':no'=>limpiar($_POST['notas'] ?? '') ?: null,
                                  ':a'=>!empty($_POST['activo']) ? 1 : 0,
                                  ':i'=>(int)($_POST['id'] ?? 0)]);
                    $aviso = ['ok', 'Codigo actualizado.'];
                }
            } elseif ($accion === 'eliminar') {
                $db->prepare("DELETE FROM referidos WHERE id=:i")->execute([':i'=>(int)$_POST['id']]);
                $aviso = ['ok', 'Codigo eliminado. Los eventos y consultas ya registrados no se tocan.'];
            } elseif ($accion === 'pago') {
                $db->prepare("INSERT INTO referido_pagos (codigo,monto,nota) VALUES (:c,:m,:n)")
                   ->execute([':c'=>slugCodigo($_POST['codigo'] ?? ''),
                              ':m'=>(float)str_replace(',', '.', $_POST['monto'] ?? '0'),
                              ':n'=>limpiar($_POST['nota'] ?? '') ?: null]);
                $aviso = ['ok', 'Pago registrado.'];
            } elseif ($accion === 'borrar_pago') {
                $db->prepare("DELETE FROM referido_pagos WHERE id=:i")->execute([':i'=>(int)$_POST['id']]);
                $aviso = ['ok', 'Pago eliminado.'];
            }
        } catch (PDOException $ex) {
            /* 23000 is the integrity-constraint SQLSTATE. Matching on the word
               "Duplicate" works on MySQL and nowhere else. */
            $aviso = ['error', $ex->getCode() === '23000'
                ? 'Ese codigo ya existe. Elegi otro.' : 'Error BD: '.$ex->getMessage()];
        } catch (Exception $ex) {
            $aviso = ['error', 'Error BD: '.$ex->getMessage()];
        }
    }
}

$editar = null;
$codigos = []; $stats = []; $pagos = []; $pagado = []; $huerfanos = []; $botones = [];

if ($db) {
    try {
        $codigos = $db->query("SELECT * FROM referidos ORDER BY activo DESC, nombre ASC")->fetchAll();

        foreach ($db->query(
            "SELECT ref,
                    SUM(tipo IN('abrir_retiro','abrir_espacio')) AS aperturas,
                    SUM(tipo IN('click_rsvp'))                   AS envios,
                    SUM(tipo='click_wa')                         AS whatsapp,
                    COUNT(*)                                     AS eventos,
                    MAX(creado)                                  AS ultima
             FROM eventos WHERE ref IS NOT NULL AND ref<>'' GROUP BY ref")->fetchAll() as $r) {
            $stats[$r['ref']] = $r;
        }

        foreach ($db->query(
            "SELECT ref, COUNT(*) AS consultas,
                    SUM(estado IN('coordinada','cerrada')) AS concretadas
             FROM consultas WHERE ref IS NOT NULL AND ref<>'' GROUP BY ref")->fetchAll() as $r) {
            $stats[$r['ref']]['consultas']   = (int)$r['consultas'];
            $stats[$r['ref']]['concretadas'] = (int)$r['concretadas'];
        }

        /* Button tags. Separate axis from ref: ref is where they came from,
           boton is what they clicked once they were here. */
        foreach ($db->query(
            "SELECT boton,
                    SUM(tipo='click_wa')                            AS wa,
                    SUM(tipo='click_ig')                            AS ig,
                    SUM(tipo='click_rsvp')                          AS envios,
                    SUM(tipo IN('rsvp_ok','consulta_esp_ok'))       AS ok,
                    COUNT(*)                                        AS total,
                    MAX(creado)                                     AS ultima
             FROM eventos WHERE boton IS NOT NULL AND boton<>'' GROUP BY boton
             ORDER BY total DESC")->fetchAll() as $r) {
            $botones[$r['boton']] = $r;
        }
        foreach ($db->query(
            "SELECT boton, COUNT(*) c, SUM(estado IN('coordinada','cerrada')) cc
             FROM consultas WHERE boton IS NOT NULL AND boton<>'' GROUP BY boton")->fetchAll() as $r) {
            $botones[$r['boton']]['consultas']   = (int)$r['c'];
            $botones[$r['boton']]['concretadas'] = (int)$r['cc'];
        }

        foreach ($db->query("SELECT codigo, SUM(monto) t FROM referido_pagos GROUP BY codigo")->fetchAll() as $r) {
            $pagado[$r['codigo']] = (float)$r['t'];
        }

        $pagos = $db->query("SELECT * FROM referido_pagos ORDER BY creado DESC LIMIT 40")->fetchAll();

        /* Codes seen in the wild that were never registered here. Usually a
           typo in a shared link, occasionally a code someone invented. */
        $conocidos = array_column($codigos, 'codigo');
        foreach ($stats as $ref => $_) {
            /* 'directo' is assigned by config.php to anyone arriving without a
               code. It has no owner by design, so it is not an orphan. It gets
               its own row above instead. */
            if ($ref === 'directo') continue;
            if (!in_array($ref, $conocidos, true)) $huerfanos[] = $ref;
        }

        if (isset($_GET['id'])) {
            $q = $db->prepare("SELECT * FROM referidos WHERE id=:i");
            $q->execute([':i'=>(int)$_GET['id']]);
            $editar = $q->fetch() ?: null;
        }
    } catch (Exception $e) {
        $falta = true;
        error_log('[LR referidos] '.$e->getMessage());
    }
}

$detalle = null; $consultasRef = [];
if ($db && !empty($_GET['ver'])) {
    $detalle = slugCodigo($_GET['ver']);
    try {
        $q = $db->prepare("SELECT id,nombre,email,tipo,estado,origen,creado FROM consultas
                           WHERE ref=:r ORDER BY creado DESC LIMIT 100");
        $q->execute([':r'=>$detalle]);
        $consultasRef = $q->fetchAll();
    } catch (Exception $e) {}
}

$base = 'https://'.SITE_DOMAIN;
function pct($a, $b) { return $b > 0 ? round($a / $b * 100).'%' : '-'; }
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<!-- admin/referidos.php VERSION 1.4 -->
<title>Referidos · Panel La Reserva</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300&family=Karla:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100vh;background:#0d1e2c;color:#e8f0f7;font-family:'Karla',sans-serif;font-size:14px}
a{text-decoration:none;color:inherit}
.layout{display:flex;min-height:100vh}
.main{flex:1;min-width:0}
.topbar{background:#0a1628;border-bottom:1px solid rgba(0,180,150,.15);padding:13px 22px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.topbar h1{font-family:'Fraunces',serif;font-size:1.1rem;font-weight:300}
.content{padding:22px}
.aviso{padding:12px 18px;border-radius:4px;font-size:.88rem;margin-bottom:18px;border-left:3px solid}
.aviso.ok{background:rgba(0,201,167,.1);border-color:#00c9a7;color:#00c9a7}
.aviso.error{background:rgba(255,82,82,.1);border-color:#ff5252;color:#ff5252}
.aviso.info{background:rgba(122,154,184,.08);border-color:#4a6a85;color:#7a9ab8}
h2{font-family:'Fraunces',serif;font-weight:300;font-size:1rem;margin:26px 0 10px}
h2:first-of-type{margin-top:0}
.cols{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:22px;align-items:start;
  max-width:1180px;margin:0 auto}
/* 1280, not 1000. The admin sidebar takes 230px, so a 1180px content area
   needs ~1420px of viewport. At 100% zoom on a 1366 screen the right panel
   overflowed; at 80% it fitted, which is what made it look like a zoom bug. */
@media(max-width:1280px){.cols{grid-template-columns:1fr;max-width:760px}}
/* Nothing created yet: one centred column instead of a form stranded against
   the right edge of a wide monitor. */
.cols.vacio{grid-template-columns:minmax(0,620px);justify-content:center}
/* Right panel is collapsible. Under 1280px it becomes four stacked cards
   below the tables, so it gets a toggle instead of a long scroll past it. */
.panel-btn{display:none;width:100%;margin-bottom:12px;padding:11px 14px;border-radius:6px;
  background:rgba(0,201,167,.12);border:1px solid rgba(0,201,167,.28);color:#00c9a7;
  font:inherit;font-size:.86rem;font-weight:600;cursor:pointer;text-align:left;
  align-items:center;justify-content:space-between}
.panel-btn b{font-size:1rem;transition:transform .18s}
.panel-btn.abierto b{transform:rotate(90deg)}
@media(max-width:1280px){
  .panel-btn{display:flex}
  .panel{display:none}
  .panel.abierto{display:block}
}
.cols.vacio .lista{display:none}
table{width:100%;border-collapse:collapse;font-size:.83rem;background:#112233;border:1px solid rgba(0,180,150,.15);border-radius:6px;overflow:hidden}
.tw{overflow-x:auto;-webkit-overflow-scrolling:touch;margin-bottom:4px}
.tw table{min-width:660px}
th{text-align:left;padding:9px 12px;background:#0a1628;color:#4a6a85;font-size:.64rem;letter-spacing:.1em;font-weight:600;text-transform:uppercase;border-bottom:1px solid rgba(0,180,150,.15)}
td{padding:9px 12px;border-bottom:1px solid rgba(0,180,150,.15);vertical-align:middle}
tr:last-child td{border-bottom:0}tr:hover td{background:#152840}
.card{background:#112233;border:1px solid rgba(0,180,150,.15);border-radius:6px;padding:18px}
.campo{display:flex;flex-direction:column;gap:5px;margin-bottom:12px}
.campo label{font-size:.7rem;color:#4a6a85;letter-spacing:.06em;text-transform:uppercase}
.campo input,.campo textarea{font:inherit;font-size:.88rem;color:#e8f0f7;background:#0d1e2c;border:1px solid rgba(0,180,150,.2);border-radius:4px;padding:9px 11px;width:100%}
.campo input:focus,.campo textarea:focus{outline:none;border-color:#00c9a7}
.campo textarea{min-height:54px;resize:vertical}
.btn{display:inline-flex;align-items:center;padding:7px 15px;border-radius:4px;font:inherit;font-size:.78rem;cursor:pointer;border:0;font-weight:500}
.btn-t{background:#00c9a7;color:#0a1628}
.btn-o{background:transparent;border:1px solid rgba(0,180,150,.2);color:#7a9ab8}
.btn-d{background:rgba(255,82,82,.12);border:1px solid rgba(255,82,82,.25);color:#ff5252}
.mono{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.78rem}
.link{background:#0d1e2c;border:1px solid rgba(0,180,150,.18);border-radius:4px;padding:6px 9px;color:#00c9a7;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.72rem;width:100%;margin-bottom:5px}
.tag{font-size:.64rem;padding:2px 8px;border-radius:12px;background:rgba(0,201,167,.12);color:#00c9a7}
.tag.off{background:rgba(74,106,133,.18);color:#4a6a85}
.tag.red{background:rgba(255,82,82,.15);color:#ff5252}
.nota{color:#4a6a85;font-size:.78rem;line-height:1.6;margin-top:8px}
.big{font-family:'Fraunces',serif;font-size:1.3rem;color:#00c9a7}
</style>
</head>
<body>
<div class="layout">
<?php include __DIR__.'/_sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <h1>Referidos</h1>
    <a href="seguridad.php" class="btn btn-o">Trafico y seguridad</a>
  </div>

  <div class="content">
    <?php if ($aviso): ?><div class="aviso <?= $aviso[0] ?>"><?= e($aviso[1]) ?></div><?php endif; ?>
    <?php if ($falta): ?>
      <div class="aviso error">Faltan las tablas. Corre referidos_tables.sql en phpMyAdmin.</div>
    <?php endif; ?>

    <div class="cols<?= ($codigos || isset($stats['directo'])) ? '' : ' vacio' ?>">
      <div class="lista">
        <h2>Codigos</h2>
        <?php if ($codigos || isset($stats['directo'])): ?>
        <div class="tw">
        <table>
          <tr><th>Persona</th><th>Codigo</th><th>Aperturas</th><th>Consultas</th><th>Concretadas</th>
              <th>Conv.</th><th>WhatsApp</th><th>Pagado</th><th></th></tr>
          <?php foreach ($codigos as $c):
            $st = $stats[$c['codigo']] ?? [];
            $ap = (int)($st['aperturas'] ?? 0);
            $co = (int)($st['consultas'] ?? 0);
            $cc = (int)($st['concretadas'] ?? 0);
          ?>
          <tr>
            <td><strong><?= e($c['nombre']) ?></strong>
                <?php if(!$c['activo']): ?> <span class="tag off">inactivo</span><?php endif; ?>
                <?php if($c['comision']): ?><br><small style="color:#4a6a85"><?= e($c['comision']) ?></small><?php endif; ?></td>
            <td class="mono"><?= e($c['codigo']) ?></td>
            <td><?= $ap ?></td>
            <td><a href="?ver=<?= urlencode($c['codigo']) ?>" style="color:#00c9a7"><?= $co ?></a></td>
            <td><strong style="color:#00c9a7"><?= $cc ?></strong></td>
            <td><?= pct($cc, $ap) ?></td>
            <td><?= (int)($st['whatsapp'] ?? 0) ?></td>
            <td><?= isset($pagado[$c['codigo']]) ? '$'.number_format($pagado[$c['codigo']], 0, ',', '.') : '-' ?></td>
            <td><a href="?id=<?= (int)$c['id'] ?>" class="btn btn-o">Editar</a></td>
          </tr>
          <?php endforeach; ?>
          <?php if (isset($stats['directo'])): $sd = $stats['directo']; ?>
          <tr>
            <td><strong>Trafico directo</strong><br><small style="color:#4a6a85">sin codigo, escribieron la direccion o llegaron por buscador</small></td>
            <td class="mono">directo</td>
            <td><?= (int)($sd['aperturas'] ?? 0) ?></td>
            <td><a href="?ver=directo" style="color:#00c9a7"><?= (int)($sd['consultas'] ?? 0) ?></a></td>
            <td><strong style="color:#00c9a7"><?= (int)($sd['concretadas'] ?? 0) ?></strong></td>
            <td><?= pct((int)($sd['concretadas'] ?? 0), (int)($sd['aperturas'] ?? 0)) ?></td>
            <td><?= (int)($sd['whatsapp'] ?? 0) ?></td>
            <td>-</td>
            <td></td>
          </tr>
          <?php endif; ?>
        </table>
        </div>
        <p class="nota">Aperturas = veces que alguien llegado con ese codigo abrio un retiro o un espacio.
        Concretadas = consultas cuyo estado pasaste a coordinada o cerrada en la bandeja. Si nunca movés
        ese estado, esta columna queda en cero para siempre.</p>
        <?php else: ?>
        <p class="nota">Todavia no hay codigos.</p>
        <?php endif; ?>

        <?php if ($huerfanos): ?>
        <h2>Codigos vistos sin registrar</h2>
        <div class="tw">
        <table>
          <tr><th>Codigo</th><th>Eventos</th><th>Ultima vez</th></tr>
          <?php foreach ($huerfanos as $hf): ?>
          <tr><td class="mono"><?= e($hf) ?> <span class="tag red">sin dueño</span></td>
              <td><?= (int)($stats[$hf]['eventos'] ?? 0) ?></td>
              <td style="color:#4a6a85"><?= e($stats[$hf]['ultima'] ?? '') ?></td></tr>
          <?php endforeach; ?>
        </table>
        </div>
        <p class="nota">Trafico que llego con un ?ref= que no corresponde a ningun codigo de esta lista.
        Suele ser un error de tipeo en un link compartido.</p>
        <?php endif; ?>

        <?php if ($detalle !== null): ?>
        <h2>Consultas de <span class="mono"><?= e($detalle) ?></span></h2>
        <?php if ($consultasRef): ?>
        <div class="tw">
        <table>
          <tr><th>Fecha</th><th>Nombre</th><th>E-mail</th><th>Tipo</th><th>Origen</th><th>Estado</th></tr>
          <?php foreach ($consultasRef as $cr): ?>
          <tr>
            <td style="color:#4a6a85;font-size:.78rem"><?= e(date('d/m/Y H:i', strtotime($cr['creado']))) ?></td>
            <td><?= e($cr['nombre']) ?></td>
            <td style="color:#7a9ab8"><?= e($cr['email']) ?></td>
            <td><?= e($cr['tipo']) ?></td>
            <td class="mono" style="color:#4a6a85"><?= e($cr['origen']) ?></td>
            <td><span class="tag<?= in_array($cr['estado'], ['coordinada','cerrada'], true) ? '' : ' off' ?>"><?= e($cr['estado']) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </table>
        </div>
        <?php else: ?><p class="nota">Ese codigo todavia no genero consultas.</p><?php endif; ?>
        <?php endif; ?>

        <h2>Botones del sitio</h2>
        <?php if ($botones): ?>
        <div class="tw">
        <table>
          <tr><th>Boton</th><th>Donde esta</th><th>Clicks</th><th>Consultas</th><th>Concretadas</th><th>Ultima vez</th></tr>
          <?php
          $donde = [
            'lr'        => 'Solicitar lugar / Solicitar fecha',
            'whatsapp'  => 'Consultar por WhatsApp',
            'johnny'    => 'Instagram en el panel de espacios',
            'instagram' => 'Ver mas en Instagram (galeria)',
            'contacto'  => 'Enviar consulta / Consultar disponibilidad',
          ];
          foreach ($botones as $bk => $bv): ?>
          <tr>
            <td class="mono"><?= e($bk) ?></td>
            <td style="color:#7a9ab8"><?= e($donde[$bk] ?? '') ?></td>
            <td><?= (int)$bv['total'] ?></td>
            <td><?= (int)($bv['consultas'] ?? 0) ?></td>
            <td><strong style="color:#00c9a7"><?= (int)($bv['concretadas'] ?? 0) ?></strong></td>
            <td style="color:#4a6a85;font-size:.78rem"><?= e($bv['ultima'] ?? '') ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
        </div>
        <p class="nota">Cada boton del sitio ya lleva su etiqueta incorporada: no hace falta compartir
        ningun link para que cuente. Esto es distinto de los codigos de arriba, que dicen de donde
        vino la persona. Un mismo visitante puede venir de <span class="mono">instagram</span> y
        convertir por el boton <span class="mono">whatsapp</span>.</p>
        <?php else: ?>
        <p class="nota">Todavia no hay clicks registrados en los botones.</p>
        <?php endif; ?>

        <h2>Etiquetas del sitio</h2>
        <div class="tw"><table>
          <tr><th>Etiqueta</th><th>Que significa</th><th>Donde esta</th><th>Se registra como</th></tr>
          <tr><td class="mono">lr</td><td>Formulario propio del sitio</td>
              <td>Solicitar lugar, Solicitar fecha, Conocer los espacios</td>
              <td class="mono">click_rsvp, rsvp_ok</td></tr>
          <tr><td class="mono">whatsapp</td><td>Se fue a WhatsApp</td>
              <td>Consultar por WhatsApp en retiros, espacios y acceso</td>
              <td class="mono">click_wa</td></tr>
          <tr><td class="mono">instagram</td><td>Se fue a Instagram</td>
              <td>Los tres links de Instagram en galeria</td>
              <td class="mono">click_ig</td></tr>
          <tr><td class="mono">johnny</td><td>Contacto directo con Johnny</td>
              <td>Instagram en el panel de espacios, Consultar disponibilidad</td>
              <td class="mono">click_rsvp, click_ig</td></tr>
          <tr><td class="mono">contacto</td><td>Formulario de contacto</td>
              <td>Enviar consulta, Organiza tu retiro, Enviar mensaje</td>
              <td class="mono">click_rsvp, contacto_ok</td></tr>
        </table></div>
        <p class="nota">Estas etiquetas estan escritas en el codigo del sitio y no se editan desde aca.
        Son fijas a proposito: si cambiaran, los numeros historicos dejarian de compararse con los nuevos.
        Para agregar una etiqueta nueva hay que tocar la pagina donde vive el boton.</p>

        <h2>Pagos registrados</h2>
        <?php if ($pagos): ?>
        <div class="tw">
        <table>
          <tr><th>Fecha</th><th>Codigo</th><th>Monto</th><th>Nota</th><th></th></tr>
          <?php foreach ($pagos as $pg): ?>
          <tr>
            <td style="color:#4a6a85;font-size:.78rem"><?= e(date('d/m/Y', strtotime($pg['creado']))) ?></td>
            <td class="mono"><?= e($pg['codigo']) ?></td>
            <td>$<?= number_format((float)$pg['monto'], 0, ',', '.') ?></td>
            <td style="color:#7a9ab8"><?= e($pg['nota']) ?></td>
            <td>
              <form method="post" onsubmit="return confirm('Eliminar este pago?')">
                <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="accion" value="borrar_pago">
                <input type="hidden" name="id" value="<?= (int)$pg['id'] ?>">
                <button class="btn btn-d">X</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </table>
        </div>
        <?php else: ?><p class="nota">Sin pagos registrados.</p><?php endif; ?>
      </div>

      <div>
        <button class="panel-btn" id="pbtn" onclick="togPanel()">
          <span><?= $editar ? 'Editar codigo, links y pagos' : 'Crear un codigo nuevo' ?></span><b>&#8250;</b>
        </button>
        <div class="panel" id="panel">
        <h2><?= $editar ? 'Editar codigo' : 'Nuevo codigo' ?></h2>
        <div class="card">
          <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="accion" value="<?= $editar ? 'editar' : 'crear' ?>">
            <?php if ($editar): ?><input type="hidden" name="id" value="<?= (int)$editar['id'] ?>"><?php endif; ?>
            <div class="campo"><label>Persona *</label>
              <input name="nombre" required value="<?= $editar ? e($editar['nombre']) : '' ?>" placeholder="Jonathan Ramirez"></div>
            <div class="campo"><label>Codigo</label>
              <input name="codigo" value="<?= $editar ? e($editar['codigo']) : '' ?>"
                     <?= $editar ? 'readonly style="opacity:.6"' : '' ?> placeholder="johnny">
              <?php if (!$editar): ?><small style="color:#4a6a85;font-size:.72rem">Si lo dejas vacio se genera del nombre.</small><?php endif; ?>
            </div>
            <div class="campo"><label>Contacto</label>
              <input name="contacto" value="<?= $editar ? e($editar['contacto']) : '' ?>" placeholder="mail o WhatsApp"></div>
            <div class="campo"><label>Comision acordada</label>
              <input name="comision" value="<?= $editar ? e($editar['comision']) : '' ?>" placeholder="10% o $20.000 por reserva"></div>
            <div class="campo"><label>Notas</label>
              <textarea name="notas"><?= $editar ? e($editar['notas']) : '' ?></textarea></div>
            <?php if ($editar): ?>
            <div class="campo" style="flex-direction:row;align-items:center;gap:8px">
              <input type="checkbox" id="act" name="activo" value="1" style="width:auto"<?= $editar['activo'] ? ' checked' : '' ?>>
              <label for="act" style="text-transform:none;color:#e8f0f7">Activo</label>
            </div>
            <?php endif; ?>
            <button class="btn btn-t"><?= $editar ? 'Guardar' : 'Crear codigo' ?></button>
            <?php if ($editar): ?><a href="referidos.php" class="btn btn-o" style="margin-left:6px">Cancelar</a><?php endif; ?>
          </form>
        </div>

        <?php if ($editar): ?>
        <h2>Links para compartir</h2>
        <div class="card">
          <?php foreach ([['Agenda','/retiros.php'],['Espacios','/espacios.php'],['Inicio','/index.php'],['Galeria','/galeria.php']] as $lk):
            $url = $base.$lk[1].'?ref='.rawurlencode($editar['codigo']); ?>
            <label style="font-size:.7rem;color:#4a6a85;text-transform:uppercase"><?= $lk[0] ?></label>
            <input class="link" readonly value="<?= e($url) ?>" onclick="this.select();document.execCommand('copy')">
          <?php endforeach; ?>
          <p class="nota">Clic en un link para copiarlo. El codigo queda guardado en la sesion del visitante,
          asi que sigue contando aunque despues navegue a otra pagina antes de completar el formulario.</p>
        </div>

        <h2>Registrar un pago</h2>
        <div class="card">
          <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="accion" value="pago">
            <input type="hidden" name="codigo" value="<?= e($editar['codigo']) ?>">
            <div class="campo"><label>Monto</label><input name="monto" required placeholder="20000"></div>
            <div class="campo"><label>Nota</label><input name="nota" placeholder="Reserva Summer Retreat"></div>
            <button class="btn btn-t">Registrar pago</button>
          </form>
          <p class="nota">Nada se calcula solo. El sistema no sabe cuanto se cobro: una consulta es una
          consulta, no un pago recibido. Este es un registro manual de lo que ya le pagaste.</p>
        </div>

        <div style="margin-top:14px">
          <form method="post" onsubmit="return confirm('Eliminar el codigo? Las consultas ya atribuidas no se borran.')">
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="accion" value="eliminar">
            <input type="hidden" name="id" value="<?= (int)$editar['id'] ?>">
            <button class="btn btn-d">Eliminar codigo</button>
          </form>
        </div>
        <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
</div>
<script>
function togPanel(){
  document.getElementById('panel').classList.toggle('abierto');
  document.getElementById('pbtn').classList.toggle('abierto');
}
</script>
</body>
</html>
