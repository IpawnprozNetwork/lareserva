<?php
require_once __DIR__.'/../includes/auth.php';
exigirSesion();
$db = getDB(); $aviso = null;

if ($_SERVER['REQUEST_METHOD']==='POST' && $db) {
  if (!csrfOk($_POST['csrf'] ?? null)) { $aviso=['error','La sesión expiró.']; }
  else {
    $estados = ['nueva','contactada','coordinada','cerrada','descartada'];
    $e = in_array($_POST['estado'] ?? '', $estados, true) ? $_POST['estado'] : 'nueva';
    try {
      $db->prepare("UPDATE consultas SET estado=:e, notas=:n WHERE id=:i")
         ->execute([':e'=>$e, ':n'=>trim($_POST['notas'] ?? '') ?: null, ':i'=>(int)$_POST['id']]);
      $aviso = ['ok','Consulta actualizada.'];
    } catch(Exception $ex){ $aviso = ['error','No se pudo actualizar.']; }
  }
}

$filtro = $_GET['estado'] ?? '';
$lista = [];
if ($db) {
  try {
    if ($filtro && in_array($filtro,['nueva','contactada','coordinada','cerrada','descartada'],true)) {
      $st = $db->prepare("SELECT * FROM consultas WHERE estado=:e ORDER BY creado DESC LIMIT 200");
      $st->execute([':e'=>$filtro]); $lista = $st->fetchAll();
    } else {
      $lista = $db->query("SELECT * FROM consultas ORDER BY creado DESC LIMIT 200")->fetchAll();
    }
  } catch(Exception $e){}
}
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Consultas · Panel La Reserva</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400&family=Karla:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a1628;--bg2:#0d1e2c;--card:#112233;--hover:#152840;--teal:#00c9a7;--teal-dim:rgba(0,201,167,.12);--teal-b:rgba(0,201,167,.22);--text:#e8f0f7;--text2:#7a9ab8;--text3:#4a6a85;--border:rgba(0,180,150,.15);--border2:rgba(0,180,150,.28);--red:#ff5252;--amber:#ffab40}
*{box-sizing:border-box;margin:0;padding:0}html,body{min-height:100vh;background:var(--bg2);color:var(--text);font-family:'Karla',system-ui,sans-serif;font-size:14px}a{text-decoration:none;color:inherit}
.layout{display:flex;min-height:100vh}
.sb{width:220px;flex-shrink:0;background:var(--bg);border-right:1px solid var(--border);display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto}
.sb-brand{padding:18px 16px 14px;border-bottom:1px solid var(--border)}.sb-brand .name{font-family:'Fraunces',serif;font-weight:300;font-size:1.05rem;color:var(--text)}.sb-brand .sub{font-size:.6rem;letter-spacing:.22em;color:var(--text3);margin-top:3px;text-transform:uppercase}
.sb-section{padding:13px 16px 4px;font-size:.59rem;letter-spacing:.18em;color:var(--text3);font-weight:700;text-transform:uppercase}
.sb-nav{list-style:none;padding:0 8px 4px}.sb-nav li a{display:flex;align-items:center;justify-content:space-between;padding:7px 10px;color:var(--text2);font-size:.83rem;border-radius:4px;border-left:2px solid transparent;transition:.15s}.sb-nav li a:hover{background:var(--teal-dim);color:var(--text)}.sb-nav li a.active{background:var(--teal-dim);color:var(--teal);border-left-color:var(--teal);font-weight:500}
.sb-badge{background:rgba(255,82,82,.2);color:var(--red);font-size:.64rem;padding:2px 7px;border-radius:20px;font-weight:700}
.sb-foot{margin-top:auto;padding:13px 16px;border-top:1px solid var(--border)}.sb-foot a{display:block;font-size:.78rem;color:var(--text3);padding:3px 0}.sb-foot a:hover{color:var(--text2)}
.main{flex:1;min-width:0}.topbar{background:var(--bg);border-bottom:1px solid var(--border);padding:13px 22px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}.topbar h1{font-family:'Fraunces',serif;font-size:1.1rem;font-weight:300;color:var(--text)}
.content{padding:24px}
.aviso{padding:12px 18px;border-radius:4px;font-size:.88rem;margin-bottom:20px;border-left:3px solid}.aviso.ok{background:rgba(0,201,167,.1);border-color:var(--teal);color:var(--teal)}.aviso.error{background:rgba(255,82,82,.1);border-color:var(--red);color:var(--red)}
fieldset{border:1px solid var(--border);padding:22px;background:var(--card);margin-bottom:28px;border-radius:6px}legend{font-family:'Fraunces',serif;font-size:1.2rem;padding:0 10px;color:var(--text);font-weight:300}
.formulario{display:grid;grid-template-columns:1fr 1fr;gap:16px}.campo{display:flex;flex-direction:column;gap:6px}.campo.ancho{grid-column:1/-1}.campo label{font-size:.78rem;letter-spacing:.06em;color:var(--text3);font-weight:500}
.campo input,.campo select,.campo textarea{font:inherit;font-size:.9rem;color:var(--text);background:var(--bg2);border:1px solid var(--border);border-radius:4px;padding:10px 13px;width:100%;transition:.18s}.campo input:focus,.campo select:focus,.campo textarea:focus{outline:none;border-color:var(--teal)}.campo textarea{resize:vertical;min-height:100px}.campo input[type=checkbox]{width:auto}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:4px;font:inherit;font-size:.82rem;cursor:pointer;border:0;transition:.18s;font-weight:500}
.btn-primary{background:var(--teal);color:#0a1628}.btn-primary:hover{background:#00b898}
.btn-outline{background:transparent;border:1px solid var(--border2);color:var(--text2)}.btn-outline:hover{border-color:var(--teal-b);color:var(--teal)}
.btn-sm{padding:6px 12px;font-size:.75rem}.btn-danger{background:rgba(255,82,82,.12);border:1px solid rgba(255,82,82,.25);color:var(--red)}.btn-danger:hover{background:rgba(255,82,82,.22)}
table{width:100%;border-collapse:collapse;font-size:.84rem}th{text-align:left;padding:10px 15px;background:var(--bg);color:var(--text3);font-size:.7rem;letter-spacing:.12em;font-weight:600;border-bottom:1px solid var(--border);text-transform:uppercase}td{padding:11px 15px;border-bottom:1px solid var(--border);vertical-align:middle;color:var(--text)}tr:last-child td{border-bottom:0}tr:hover td{background:var(--hover)}
.badge-admin{background:var(--teal-dim);color:var(--teal);font-size:.68rem;padding:2px 9px;border-radius:20px;font-weight:600}.badge-editor{background:rgba(74,106,133,.15);color:var(--text3);font-size:.68rem;padding:2px 9px;border-radius:20px;font-weight:600}.badge-inactivo{background:rgba(255,82,82,.12);color:var(--red);font-size:.68rem;padding:2px 9px;border-radius:20px;font-weight:600}
.td-actions{display:flex;gap:8px;flex-wrap:wrap}
.filtros{display:flex;gap:12px;margin-bottom:18px;flex-wrap:wrap}.filtros a{font-size:.82rem;color:var(--text3);padding:5px 12px;border-radius:20px;border:1px solid transparent;transition:.15s}.filtros a:hover,.filtros a.on{border-color:var(--teal-b);color:var(--teal);background:var(--teal-dim)}
.ficha{background:var(--card);border:1px solid var(--border);border-radius:6px;padding:18px 20px;margin-bottom:10px}.ficha h3{font-size:1rem;font-weight:600;color:var(--text);margin-bottom:4px}.meta{font-size:.8rem;color:var(--text3);margin-bottom:10px;line-height:1.7}
.empty{padding:32px;text-align:center;color:var(--text3);font-size:.88rem}
.grid2{display:grid;grid-template-columns:1fr 2fr;gap:22px;align-items:start}
@media(max-width:900px){.grid2{grid-template-columns:1fr}}
.sb-toggle{display:none;position:fixed;top:11px;left:11px;z-index:100;width:38px;height:38px;background:var(--bg);border:1px solid var(--border);border-radius:4px;color:var(--text);font-size:18px;cursor:pointer;align-items:center;justify-content:center}
@media(max-width:768px){.sb-toggle{display:flex}.sb{position:fixed;top:0;left:0;height:100vh;z-index:90;transform:translateX(-100%);transition:.25s ease}.sb.open{transform:none}.content{padding:16px}.topbar{padding:12px 16px 12px 54px}}
</style>
</head>
<body>
<div class="layout">
<?php include __DIR__.'/_sidebar.php'; ?>
<div class="main">
  <div class="topbar"><h1>Consultas</h1><a href="index.php" class="btn btn-outline btn-sm">← Dashboard</a></div>
  <div class="content">
    <?php if($aviso): ?><div class="aviso <?= $aviso[0] ?>"><?= e($aviso[1]) ?></div><?php endif; ?>
    <?php if(!$db): ?><div class="aviso error">Sin conexion.</div><?php endif; ?>

  <strong style="font-family:'Fraunces',serif;font-size:1.15rem">Consultas</strong>
  <div><a href="index.php">Inicio</a><a href="retiros.php">Retiros</a><a href="logout.php">Salir</a></div>
</div>

<main class="envoltura" style="padding-top:32px;padding-bottom:64px">
  <?php if($aviso): ?><div class="aviso <?= $aviso[0] ?>"><?= e($aviso[1]) ?></div><?php endif; ?>

  <p class="filtros">
    <a href="consultas.php" class="<?= $filtro===''?'on':'' ?>">Todas</a>
    <?php foreach(['nueva','contactada','coordinada','cerrada','descartada'] as $f): ?>
      <a href="consultas.php?estado=<?= $f ?>" class="<?= $filtro===$f?'on':'' ?>"><?= $f ?></a>
    <?php endforeach; ?>
  </p>

  <?php if(!$lista): ?>
    <p style="color:var(--gris)">No hay consultas para mostrar.</p>
  <?php else: foreach($lista as $c): ?>
    <div class="ficha">
      <h3><?= e($c['nombre']) ?></h3>
      <p class="meta">
        <?= e(date('d/m/Y H:i', strtotime($c['creado']))) ?> ·
        <a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a>
        <?php if($c['telefono']): ?> · <a href="https://wa.me/<?= e(preg_replace('/\D/','',$c['telefono'])) ?>" target="_blank" rel="noopener"><?= e($c['telefono']) ?></a><?php endif; ?>
        · <?= e($c['tipo']) ?>
        <?php if($c['fecha_tentativa']): ?> · fecha tentativa <?= e($c['fecha_tentativa']) ?><?php endif; ?>
        <?php if($c['personas']): ?> · <?= (int)$c['personas'] ?> personas<?php endif; ?>
        <?php if($c['origen']): ?> · desde <?= e($c['origen']) ?><?php endif; ?>
      </p>
      <?php if($c['mensaje']): ?><p><?= nl2br(e($c['mensaje'])) ?></p><?php endif; ?>
      <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-top:14px">
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
        <div class="campo" style="min-width:170px"><label>Estado</label>
          <select name="estado">
            <?php foreach(['nueva','contactada','coordinada','cerrada','descartada'] as $s): ?>
              <option value="<?= $s ?>" <?= $c['estado']===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo" style="flex:1;min-width:220px"><label>Notas internas</label><input name="notas" value="<?= e($c['notas']) ?>"></div>
        <button class="boton" type="submit">Guardar</button>
      </form>
    </div>
  <?php endforeach; endif; ?>
</main>

  </div>
</div></div>
<script>document.getElementById('sb').addEventListener('click',function(e){if(e.target.tagName==='A'&&window.innerWidth<=768)this.classList.remove('open');});</script>
</body></html>