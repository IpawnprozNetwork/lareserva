<?php
/* admin/usuarios.php - VERSION 1.1 */
require_once __DIR__.'/../includes/auth.php';
exigirSesion();
$u = usuarioActual();
/* v1.1: mismo problema que videos.php. Un superadmin no podia entrar a
   Usuarios, que es la pantalla donde se arreglan los roles. */
if (!esAdmin()) { http_response_code(403); die('Acceso denegado.'); }
$db = getDB();
$aviso = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db) {
  if (!csrfOk($_POST['csrf'] ?? null)) {
    $aviso = ['error','Sesion expirada.'];
  } else {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
      $email  = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
      $nombre = limpiar($_POST['nombre'] ?? '');
      $clave  = $_POST['clave'] ?? '';
      $rol    = in_array($_POST['rol'] ?? '', ['admin','editor'], true) ? $_POST['rol'] : 'editor';
      if (!$email || $nombre === '' || strlen($clave) < 10) {
        $aviso = ['error','Completá todos los campos. La clave debe tener al menos 10 caracteres.'];
      } else {
        try {
          $st = $db->prepare("INSERT INTO usuarios (email,pass_hash,nombre,rol) VALUES (:e,:h,:n,:r)");
          $st->execute([':e'=>$email,':h'=>password_hash($clave,PASSWORD_DEFAULT),':n'=>$nombre,':r'=>$rol]);
          $aviso = ['ok','Usuario '.$nombre.' creado correctamente.'];
        } catch (Exception $ex) {
          $aviso = ['error','No se pudo crear: ese e-mail ya existe.'];
        }
      }
    } elseif ($accion === 'toggle') {
      $id = (int)$_POST['id'];
      if ($id !== (int)$u['id']) {
        $db->prepare("UPDATE usuarios SET activo = 1 - activo WHERE id=:i")->execute([':i'=>$id]);
        $aviso = ['ok','Estado actualizado.'];
      } else { $aviso = ['error','No podes desactivarte a vos mismo.']; }
    } elseif ($accion === 'eliminar') {
      $id = (int)$_POST['id'];
      if ($id !== (int)$u['id']) {
        $db->prepare("DELETE FROM usuarios WHERE id=:i")->execute([':i'=>$id]);
        $aviso = ['ok','Usuario eliminado.'];
      } else { $aviso = ['error','No podes eliminarte a vos mismo.']; }
    } elseif ($accion === 'cambiar_clave') {
      $id = (int)$_POST['id'];
      $clave = $_POST['nueva_clave'] ?? '';
      if (strlen($clave) < 10) { $aviso = ['error','La clave debe tener al menos 10 caracteres.']; }
      else {
        $db->prepare("UPDATE usuarios SET pass_hash=:h WHERE id=:i")->execute([':h'=>password_hash($clave,PASSWORD_DEFAULT),':i'=>$id]);
        $aviso = ['ok','Clave actualizada.'];
      }
    }
  }
}

$lista = [];
if ($db) {
  try { $lista = $db->query("SELECT id,email,nombre,rol,activo,ultimo_ingreso,creado FROM usuarios ORDER BY creado ASC")->fetchAll(); }
  catch(Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Usuarios · Panel La Reserva</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400&family=Karla:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a1628;--bg2:#0d1e2c;--card:#112233;--hover:#152840;--teal:#00c9a7;--teal-dim:rgba(0,201,167,.12);--teal-b:rgba(0,201,167,.22);--text:#e8f0f7;--text2:#7a9ab8;--text3:#4a6a85;--border:rgba(0,180,150,.15);--red:#ff5252}
*{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100vh;background:var(--bg2);color:var(--text);font-family:'Karla',sans-serif;font-size:14px}
a{text-decoration:none;color:inherit}
.layout{display:flex;min-height:100vh}
.sb{width:220px;flex-shrink:0;background:var(--bg);border-right:1px solid var(--border);display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto}
.sb-brand{padding:18px 16px 14px;border-bottom:1px solid var(--border)}.sb-brand .name{font-family:'Fraunces',serif;font-weight:300;font-size:1.05rem;color:var(--text)}.sb-brand .sub{font-size:.6rem;letter-spacing:.22em;color:var(--text3);margin-top:3px;text-transform:uppercase}
.sb-section{padding:13px 16px 4px;font-size:.59rem;letter-spacing:.18em;color:var(--text3);font-weight:700;text-transform:uppercase}
.sb-nav{list-style:none;padding:0 8px 4px}.sb-nav li a{display:flex;align-items:center;justify-content:space-between;padding:7px 10px;color:var(--text2);font-size:.83rem;border-radius:4px;border-left:2px solid transparent;transition:.15s}.sb-nav li a:hover{background:var(--teal-dim);color:var(--text)}.sb-nav li a.active{background:var(--teal-dim);color:var(--teal);border-left-color:var(--teal);font-weight:500}
.sb-foot{margin-top:auto;padding:13px 16px;border-top:1px solid var(--border)}.sb-foot a{display:block;font-size:.78rem;color:var(--text3);padding:3px 0}.sb-foot a:hover{color:var(--text2)}
.main{flex:1;min-width:0}
.topbar{background:var(--bg);border-bottom:1px solid var(--border);padding:13px 22px;display:flex;align-items:center;justify-content:space-between}
.topbar h1{font-family:'Fraunces',serif;font-size:1.1rem;font-weight:300;color:var(--text)}
.content{padding:24px}
.aviso{padding:12px 18px;border-radius:4px;font-size:.88rem;margin-bottom:20px;border-left:3px solid}.aviso.ok{background:rgba(0,201,167,.1);border-color:var(--teal);color:var(--teal)}.aviso.error{background:rgba(255,82,82,.1);border-color:var(--red);color:var(--red)}
.grid2{display:grid;grid-template-columns:380px 1fr;gap:24px;align-items:start}
@media(max-width:960px){.grid2{grid-template-columns:1fr}}
.card{background:var(--card);border:1px solid var(--border);border-radius:6px;padding:22px;margin-bottom:16px}
.card h2{font-size:.95rem;font-weight:600;margin-bottom:18px;color:var(--text);border-bottom:1px solid var(--border);padding-bottom:10px}
.field{display:flex;flex-direction:column;gap:5px;margin-bottom:14px}.field label{font-size:.76rem;letter-spacing:.06em;color:var(--text3)}.field input,.field select{font:inherit;font-size:.9rem;color:var(--text);background:var(--bg2);border:1px solid var(--border);border-radius:4px;padding:10px 12px;width:100%;transition:.18s}.field input:focus,.field select:focus{outline:none;border-color:var(--teal)}.field small{font-size:.72rem;color:var(--text3)}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:4px;font:inherit;font-size:.82rem;cursor:pointer;border:0;transition:.18s;font-weight:500}
.btn-teal{background:var(--teal);color:#0a1628}.btn-teal:hover{background:#00b898}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--text2)}.btn-outline:hover{border-color:var(--teal-b);color:var(--teal)}
.btn-sm{padding:6px 12px;font-size:.75rem}.btn-danger{background:rgba(255,82,82,.12);border:1px solid rgba(255,82,82,.25);color:var(--red)}.btn-danger:hover{background:rgba(255,82,82,.22)}
table{width:100%;border-collapse:collapse;font-size:.84rem}
th{text-align:left;padding:10px 14px;background:var(--bg);color:var(--text3);font-size:.68rem;letter-spacing:.12em;font-weight:600;border-bottom:1px solid var(--border);text-transform:uppercase}
td{padding:11px 14px;border-bottom:1px solid var(--border);vertical-align:middle}tr:last-child td{border-bottom:0}tr:hover td{background:var(--hover)}
.badge{display:inline-block;font-size:.68rem;padding:3px 9px;border-radius:20px;font-weight:600}
.badge-admin{background:var(--teal-dim);color:var(--teal)}.badge-editor{background:rgba(74,106,133,.12);color:var(--text3)}.badge-inactivo{background:rgba(255,82,82,.12);color:var(--red)}
.td-actions{display:flex;gap:8px}
.sb-toggle{display:none;position:fixed;top:11px;left:11px;z-index:100;width:38px;height:38px;background:var(--bg);border:1px solid var(--border);border-radius:4px;color:var(--text);font-size:18px;cursor:pointer;align-items:center;justify-content:center}
@media(max-width:768px){.sb-toggle{display:flex}.sb{position:fixed;top:0;left:0;height:100vh;z-index:90;transform:translateX(-100%);transition:.25s ease}.sb.open{transform:none}.content{padding:16px}.topbar{padding:12px 16px 12px 54px}}
</style>
</head>
<body>
<div class="layout">
<?php include __DIR__.'/_sidebar.php'; ?>

<div class="main">
  <div class="topbar"><h1>Usuarios del panel</h1><a href="index.php" class="btn btn-outline btn-sm">← Dashboard</a></div>
  <div class="content">
    <?php if($aviso): ?><div class="aviso <?= $aviso[0] ?>"><?= e($aviso[1]) ?></div><?php endif; ?>
    <?php if(!$db): ?><div class="aviso error">Sin conexion a la base de datos.</div><?php endif; ?>

    <div class="grid2">
      <!-- LEFT: forms -->
      <div>
        <div class="card">
          <h2>Crear nuevo usuario</h2>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="accion" value="crear">
            <div class="field"><label>Nombre</label><input type="text" name="nombre" required placeholder="Nombre completo"></div>
            <div class="field"><label>E-mail</label><input type="email" name="email" required placeholder="usuario@email.com"></div>
            <div class="field"><label>Clave inicial</label><input type="text" name="clave" required placeholder="Minimo 10 caracteres" autocomplete="new-password"><small>La persona puede cambiarla despues.</small></div>
            <div class="field"><label>Rol</label>
              <select name="rol">
                <option value="editor">Editor - gestiona retiros, espacios y consultas</option>
                <option value="admin">Admin - acceso completo, incluye usuarios</option>
              </select>
            </div>
            <button type="submit" class="btn btn-teal">Crear usuario</button>
          </form>
        </div>

        <div class="card">
          <h2>Cambiar clave</h2>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="accion" value="cambiar_clave">
            <div class="field"><label>Usuario</label>
              <select name="id">
                <?php foreach($lista as $lu): ?>
                  <option value="<?= (int)$lu['id'] ?>"><?= e($lu['nombre']) ?> - <?= e($lu['email']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field"><label>Nueva clave</label><input type="text" name="nueva_clave" required placeholder="Minimo 10 caracteres" autocomplete="new-password"></div>
            <button type="submit" class="btn btn-teal">Cambiar clave</button>
          </form>
        </div>
      </div>

      <!-- RIGHT: user list -->
      <div class="card" style="padding:0;overflow:hidden">
        <div style="padding:16px 18px;border-bottom:1px solid var(--border)">
          <h2 style="margin:0;border:0;padding:0">Usuarios actuales (<?= count($lista) ?>)</h2>
        </div>
        <?php if($lista): ?>
        <table>
          <tr><th>Nombre / Email</th><th>Rol</th><th>Ultimo ingreso</th><th>Acciones</th></tr>
          <?php foreach($lista as $lu): ?>
          <tr style="<?= !$lu['activo'] ? 'opacity:.5' : '' ?>">
            <td>
              <div style="font-weight:500;color:var(--text)"><?= e($lu['nombre']) ?></div>
              <div style="font-size:.75rem;color:var(--teal)"><?= e($lu['email']) ?></div>
              <?php if(!$lu['activo']): ?><span class="badge badge-inactivo" style="margin-top:4px">INACTIVO</span><?php endif; ?>
            </td>
            <td><span class="badge badge-<?= $lu['rol'] ?>"><?= strtoupper(e($lu['rol'])) ?></span></td>
            <td style="font-size:.78rem;color:var(--text3)"><?= $lu['ultimo_ingreso'] ? e(date('d/m/Y H:i',strtotime($lu['ultimo_ingreso']))) : 'Nunca' ?></td>
            <td>
              <div class="td-actions">
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                  <input type="hidden" name="accion" value="toggle">
                  <input type="hidden" name="id" value="<?= (int)$lu['id'] ?>">
                  <button type="submit" class="btn btn-outline btn-sm" <?= (int)$lu['id']===(int)$u['id']?'disabled':'' ?>><?= $lu['activo']?'Desactivar':'Activar' ?></button>
                </form>
                <form method="post" style="display:inline" onsubmit="return confirm('Eliminar a <?= e(addslashes($lu['nombre'])) ?>?')">
                  <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                  <input type="hidden" name="accion" value="eliminar">
                  <input type="hidden" name="id" value="<?= (int)$lu['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm" <?= (int)$lu['id']===(int)$u['id']?'disabled':'' ?>>Eliminar</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php else: ?>
          <p style="padding:24px;color:var(--text3)">No hay usuarios.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</div>
<script>document.getElementById('sb').addEventListener('click',function(e){if(e.target.tagName==='A'&&window.innerWidth<=768)this.classList.remove('open');});</script>
</body></html>
