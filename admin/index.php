<?php
require_once __DIR__.'/../includes/auth.php';
exigirSesion();
$u = usuarioActual();
$db = getDB();

$stats = ['nuevas'=>0,'proximos'=>0,'borradores'=>0,'espacios'=>0];
$ultimas = [];
if ($db) {
  try {
    $stats['nuevas']     = (int)$db->query("SELECT COUNT(*) FROM consultas WHERE estado='nueva'")->fetchColumn();
    $stats['proximos']   = (int)$db->query("SELECT COUNT(*) FROM retiros WHERE estado='publicado' AND fecha_inicio>=CURDATE()")->fetchColumn();
    $stats['borradores'] = (int)$db->query("SELECT COUNT(*) FROM retiros WHERE estado='borrador'")->fetchColumn();
    $stats['espacios']   = (int)$db->query("SELECT COUNT(*) FROM espacios WHERE activo=1")->fetchColumn();
    $ultimas = $db->query("SELECT id,nombre,email,tipo,fecha_tentativa,personas,estado,creado FROM consultas ORDER BY creado DESC LIMIT 10")->fetchAll();
  } catch(Exception $e){ error_log($e->getMessage()); }
}
$hora = (int)date('H');
$saludo = $hora < 12 ? 'Buenos dias' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
$iniciales = strtoupper(implode('', array_map(fn($w)=>$w[0], array_slice(explode(' ', trim($u['nombre'])), 0, 2))));
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Panel · La Reserva</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400&family=Karla:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#0a1628;--bg2:#0d1e2c;--card:#112233;--hover:#152840;
  --teal:#00c9a7;--teal-dim:rgba(0,201,167,.12);--teal-b:rgba(0,201,167,.22);
  --blue:#1a9fd4;--text:#e8f0f7;--text2:#7a9ab8;--text3:#4a6a85;
  --border:rgba(0,180,150,.15);--border2:rgba(0,180,150,.28);
  --green:#00e676;--orange:#ff9800;--red:#ff5252;--amber:#ffab40;--purple:#9c6aff;
}
*{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100vh;background:var(--bg2);color:var(--text);font-family:'Karla',sans-serif;font-size:14px}
a{text-decoration:none;color:inherit}
.layout{display:flex;min-height:100vh}

/* ── SIDEBAR ── */
.sb{width:230px;flex-shrink:0;background:var(--bg);border-right:1px solid var(--border);display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto}

.sb-brand{padding:16px 16px 13px;border-bottom:1px solid var(--border)}
.sb-brand .name{font-family:'Fraunces',serif;font-weight:300;font-size:1.05rem;color:var(--text)}
.sb-brand .sub{font-size:.58rem;letter-spacing:.22em;color:var(--text3);margin-top:3px;text-transform:uppercase}

.sb-user{padding:13px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:11px}
.sb-avatar{width:34px;height:34px;border-radius:50%;background:var(--teal-dim);border:1px solid var(--teal-b);display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;color:var(--teal);flex-shrink:0;letter-spacing:.04em}
.sb-name{font-size:.86rem;font-weight:600;color:var(--text);line-height:1.3}
.sb-role{font-size:.63rem;color:var(--text3);letter-spacing:.1em;text-transform:uppercase}

.sb-view{margin:10px 10px 2px;padding:8px 12px;background:var(--teal-dim);border:1px solid var(--teal-b);border-radius:4px;color:var(--teal);font:inherit;font-size:.78rem;font-weight:500;cursor:pointer;display:flex;align-items:center;justify-content:space-between;transition:.2s;width:calc(100% - 20px)}
.sb-view:hover{background:rgba(0,201,167,.2)}

.sb-section{padding:13px 16px 4px;font-size:.59rem;letter-spacing:.18em;color:var(--text3);font-weight:700;text-transform:uppercase}

.sb-nav{list-style:none;padding:0 8px 2px}
.sb-nav li a{display:flex;align-items:center;justify-content:space-between;padding:7px 10px;color:var(--text2);font-size:.83rem;border-radius:4px;border-left:2px solid transparent;transition:.15s;gap:6px}
.sb-nav li a:hover{background:var(--teal-dim);color:var(--text)}
.sb-nav li a.active{background:var(--teal-dim);color:var(--teal);border-left-color:var(--teal);font-weight:500}
.sb-badge{background:rgba(255,82,82,.2);color:var(--red);font-size:.64rem;padding:2px 7px;border-radius:20px;font-weight:700;flex-shrink:0}
.sb-ext{font-size:.68rem;color:var(--text3)}

.sb-foot{margin-top:auto;padding:13px 16px;border-top:1px solid var(--border)}
.sb-foot a{display:block;font-size:.78rem;color:var(--text3);padding:3px 0;transition:.18s}
.sb-foot a:hover{color:var(--text2)}

/* ── MAIN ── */
.main{flex:1;min-width:0;display:flex;flex-direction:column}

.portal-strip{background:var(--bg);border-bottom:1px solid var(--border);padding:7px 22px;display:flex;gap:6px;overflow-x:auto}
.pc{display:inline-flex;align-items:center;gap:5px;padding:5px 13px;border-radius:20px;font-size:.74rem;font-weight:500;cursor:pointer;border:1px solid var(--border);color:var(--text2);background:transparent;white-space:nowrap;transition:.15s}
.pc:hover,.pc.active{background:var(--teal-dim);border-color:var(--teal-b);color:var(--teal)}

.topbar{background:var(--bg);border-bottom:1px solid var(--border);padding:12px 22px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.topbar-l{font-size:.98rem;font-weight:500;color:var(--text)}
.topbar-l small{color:var(--text3);font-size:.8rem;font-weight:400;margin-left:8px}
.topbar-r{display:flex;gap:8px}
.btn{display:inline-flex;align-items:center;gap:5px;padding:7px 15px;border-radius:4px;font:inherit;font-size:.79rem;cursor:pointer;border:0;transition:.18s;font-weight:500}
.btn-teal{background:var(--teal);color:#0a1628}
.btn-teal:hover{background:#00b898}
.btn-out{background:transparent;border:1px solid var(--border2);color:var(--text2)}
.btn-out:hover{border-color:var(--teal-b);color:var(--teal)}

.content{padding:22px;flex:1}
.page-title{font-family:'Fraunces',serif;font-size:1.45rem;font-weight:300;color:var(--text);margin-bottom:3px}
.page-sub{font-size:.8rem;color:var(--text3);margin-bottom:22px}

/* Stats */
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:22px}
@media(max-width:900px){.stats{grid-template-columns:1fr 1fr}}
.sc{background:var(--card);border:1px solid var(--border);border-radius:6px;padding:16px 18px;position:relative;overflow:hidden}
.sc::before{content:'';position:absolute;top:0;left:0;right:0;height:2px}
.sc.red::before{background:var(--red)}.sc.teal::before{background:var(--teal)}
.sc.amber::before{background:var(--amber)}.sc.blue::before{background:var(--blue)}
.sc-label{font-size:.67rem;letter-spacing:.1em;color:var(--text3);text-transform:uppercase;margin-bottom:5px}
.sc-n{font-size:2.1rem;font-weight:600;line-height:1.1;margin-bottom:6px}
.sc.red .sc-n{color:var(--red)}.sc.teal .sc-n{color:var(--teal)}
.sc.amber .sc-n{color:var(--amber)}.sc.blue .sc-n{color:var(--blue)}
.sc-link{font-size:.73rem;color:var(--teal);opacity:.7}
.sc-link:hover{opacity:1}

/* Grid */
.grid2{display:grid;grid-template-columns:2fr 1fr;gap:16px;align-items:start}
@media(max-width:880px){.grid2{grid-template-columns:1fr}}

/* Card */
.card{background:var(--card);border:1px solid var(--border);border-radius:6px;overflow:hidden;margin-bottom:14px}
.ch{display:flex;align-items:center;justify-content:space-between;padding:13px 17px;border-bottom:1px solid var(--border)}
.ch h2{font-size:.9rem;font-weight:600;color:var(--text)}
table{width:100%;border-collapse:collapse;font-size:.81rem}
th{text-align:left;padding:9px 15px;color:var(--text3);font-size:.66rem;letter-spacing:.12em;font-weight:600;text-transform:uppercase;border-bottom:1px solid var(--border)}
td{padding:10px 15px;border-bottom:1px solid var(--border);vertical-align:middle}
tr:last-child td{border-bottom:0}
tr:hover td{background:var(--hover)}

.pill{display:inline-block;font-size:.64rem;padding:3px 9px;border-radius:20px;font-weight:600;text-transform:uppercase}
.pill.nueva{background:rgba(255,82,82,.15);color:var(--red)}
.pill.contactada{background:rgba(0,201,167,.15);color:var(--teal)}
.pill.coordinada{background:rgba(26,159,212,.15);color:var(--blue)}
.pill.cerrada,.pill.descartada{background:rgba(74,106,133,.12);color:var(--text3)}

/* Quick links */
.ql{display:flex;align-items:center;justify-content:space-between;padding:11px 15px;background:var(--card);border:1px solid var(--border);border-radius:5px;margin-bottom:7px;color:var(--text2);font-size:.83rem;font-weight:500;transition:.18s}
.ql:hover{border-color:var(--teal-b);background:var(--hover);color:var(--text)}
.ql-r{font-size:.75rem;color:var(--text3)}
.ql-badge{font-size:.68rem;color:var(--red);font-weight:700}

.empty{padding:30px;text-align:center;color:var(--text3);font-size:.86rem}

/* Mobile */
.sb-toggle{display:none;position:fixed;top:11px;left:11px;z-index:100;width:38px;height:38px;background:var(--bg);border:1px solid var(--border);border-radius:4px;color:var(--text);font-size:18px;cursor:pointer;align-items:center;justify-content:center}
@media(max-width:768px){
  .sb-toggle{display:flex}
  .sb{position:fixed;top:0;left:0;height:100vh;z-index:90;transform:translateX(-100%);transition:transform .25s ease;width:240px}
  .sb.open{transform:none}
  .content{padding:16px}
  .topbar{padding:12px 16px 12px 54px}
}
</style>
</head>
<body>


<div class="layout">
<?php include __DIR__.'/_sidebar.php'; ?>

<div class="main">

  <!-- Portal strip -->
  <div class="portal-strip">
    <div class="pc active">Admin</div>
    <div class="pc" onclick="window.open('../index.php','_blank')">Sitio publico</div>
    <div class="pc" onclick="window.open('../retiros.php','_blank')">Agenda</div>
    <div class="pc" onclick="window.open('../galeria.php','_blank')">Galeria</div>
    <div class="pc" onclick="window.open('../historia.php','_blank')">Historia</div>
    <div class="pc" onclick="window.open('../contacto.php','_blank')">Formulario</div>
  </div>

  <!-- Topbar -->
  <div class="topbar">
    <div class="topbar-l">
      <?= e($saludo) ?>, <?= e(explode(' ',$u['nombre'])[0]) ?>
      <small><?= date('j \d\e F \d\e Y') ?></small>
    </div>
    <div class="topbar-r">
      <a href="retiros.php" class="btn btn-out">+ Nuevo retiro</a>
      <a href="consultas.php" class="btn btn-teal">
        Consultas<?php if($stats['nuevas']>0): ?> (<?= $stats['nuevas'] ?>)<?php endif; ?>
      </a>
    </div>
  </div>

  <div class="content">
    <h1 class="page-title">Panel de La Reserva</h1>
    <p class="page-sub">La Reserva Naturaleza Arte &middot; Capilla del Senor</p>

    <!-- Stats -->
    <div class="stats">
      <div class="sc red">
        <div class="sc-label">Consultas sin responder</div>
        <div class="sc-n"><?= $stats['nuevas'] ?></div>
        <a href="consultas.php" class="sc-link">Ver bandeja &rarr;</a>
      </div>
      <div class="sc teal">
        <div class="sc-label">Retiros publicados</div>
        <div class="sc-n"><?= $stats['proximos'] ?></div>
        <a href="retiros.php" class="sc-link">Ver agenda &rarr;</a>
      </div>
      <div class="sc amber">
        <div class="sc-label">Retiros en borrador</div>
        <div class="sc-n"><?= $stats['borradores'] ?></div>
        <a href="retiros.php" class="sc-link">Editar &rarr;</a>
      </div>
      <div class="sc blue">
        <div class="sc-label">Espacios activos</div>
        <div class="sc-n"><?= $stats['espacios'] ?></div>
        <a href="espacios.php" class="sc-link">Gestionar &rarr;</a>
      </div>
    </div>

    <!-- Main grid -->
    <div class="grid2">

      <!-- Consultas table -->
      <div class="card">
        <div class="ch">
          <h2>Ultimas consultas</h2>
          <a href="consultas.php" class="btn btn-out" style="padding:5px 11px;font-size:.74rem">Ver todas</a>
        </div>
        <?php if($ultimas): ?>
        <table>
          <tr><th>Nombre</th><th>Motivo</th><th>Fecha tent.</th><th>Estado</th></tr>
          <?php foreach($ultimas as $c): ?>
          <tr>
            <td>
              <div style="font-weight:500"><?= e($c['nombre']) ?></div>
              <div style="font-size:.74rem;color:var(--teal)"><?= e($c['email']) ?></div>
            </td>
            <td>
              <?= e($c['tipo']) ?>
              <?php if($c['personas']): ?><div style="font-size:.73rem;color:var(--text3)"><?= (int)$c['personas'] ?> pers.</div><?php endif; ?>
            </td>
            <td style="color:var(--text2);font-size:.79rem">
              <?= $c['fecha_tentativa'] ? e(date('d/m/Y',strtotime($c['fecha_tentativa']))) : '&mdash;' ?>
            </td>
            <td><span class="pill <?= e($c['estado']) ?>"><?= e($c['estado']) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php else: ?>
        <div class="empty">Todavia no hay consultas.</div>
        <?php endif; ?>
      </div>

      <!-- Quick actions -->
      <div>
        <div class="ch" style="background:var(--card);border:1px solid var(--border);border-radius:6px;margin-bottom:8px">
          <h2>Acciones rapidas</h2>
        </div>
        <a href="retiros.php" class="ql">Nuevo retiro <span class="ql-r">&rarr;</span></a>
        <a href="consultas.php" class="ql">
          Bandeja de consultas
          <?php if($stats['nuevas']>0): ?><span class="ql-badge"><?= $stats['nuevas'] ?> nuevas</span>
          <?php else: ?><span class="ql-r">0 nuevas</span><?php endif; ?>
        </a>
        <a href="espacios.php" class="ql">Gestionar espacios <span class="ql-r">&rarr;</span></a>
        <a href="media.php" class="ql">Imágenes y videos <span class="ql-r">&rarr;</span></a>
        <a href="../galeria.php" target="_blank" class="ql">Ver galeria <span class="ql-r">&#8599;</span></a>
        <a href="../historia.php" target="_blank" class="ql">Pagina de historia <span class="ql-r">&#8599;</span></a>
        <a href="../index.php" target="_blank" class="ql">Ver el sitio <span class="ql-r">&#8599;</span></a>
      </div>

    </div>
  </div>
</div>
</div>

<script>
document.getElementById('sb').addEventListener('click',function(e){
  if(e.target.tagName==='A'&&window.innerWidth<=768)this.classList.remove('open');
});
</script>
</body>
</html>
