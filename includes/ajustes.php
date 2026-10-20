<?php
/* includes/ajustes.php - VERSION 1.1 */
/* includes/ajustes.php - VERSION 1.0
   Lectura de textos editables desde la tabla ajustes.

   Todo lo que hay aca falla abierto. Si la tabla no existe, si le faltan
   columnas, o si la consulta explota, lrAjuste() devuelve el texto por
   defecto que le pasa la pagina. Nunca una pantalla en blanco ni un 500:
   el peor caso es que la portada muestre el texto original, que es
   exactamente lo que mostraba antes de que esto existiera.

   Una sola consulta por request. El resultado queda en memoria. */

if (!function_exists('lrAjustes')) {

function lrAjustes(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    try {
        if (!function_exists('getDB')) return $cache;
        $db = getDB();
        if (!$db) return $cache;
        foreach ($db->query("SELECT clave, valor FROM ajustes")->fetchAll() as $r) {
            $cache[$r['clave']] = $r['valor'];
        }
    } catch (Throwable $e) {
        /* Tabla ausente o distinta. Se sigue con los valores por defecto. */
        error_log('[LR ajustes] '.$e->getMessage());
    }
    return $cache;
}

/* $defecto es el texto que se muestra si la clave no esta cargada.
   Poner siempre el texto real, no una cadena vacia: asi la pagina se ve
   bien incluso con la tabla recien creada y vacia. */
function lrAjuste(string $clave, string $defecto = ''): string {
    $a = lrAjustes();
    $v = $a[$clave] ?? '';
    return ($v === '' || $v === null) ? $defecto : $v;
}

/* Para textos con saltos de linea que van a HTML. */
function lrAjusteHtml(string $clave, string $defecto = ''): string {
    return nl2br(htmlspecialchars(lrAjuste($clave, $defecto), ENT_QUOTES, 'UTF-8'));
}

}

/* ── Aviso de consulta nueva ─────────────────────────────────────────────
   v1.1: antes solo contacto.php mandaba aviso. Los formularios de espacios,
   retiros y verano2027 guardaban en la base y no avisaban a nadie, asi que
   una consulta podia quedar dias sin que nadie la viera.

   Manda a SITE_EMAIL y, si esta definida, tambien a SITE_EMAIL_COPIA.

   IMPORTANTE: mail() no es confiable en este hosting. Esto es una comodidad,
   no un canal. La fuente de verdad sigue siendo admin/consultas.php. Por eso
   la funcion no devuelve nada y nunca frena el guardado: si el mail falla, la
   consulta ya quedo guardada igual. */
function lrAvisarConsulta(array $d): void {
    $lineas = [
        'Nombre:   ' . ($d['nombre']   ?? '-'),
        'E-mail:   ' . ($d['email']    ?? '-'),
        'Telefono: ' . ($d['telefono'] ?: '-'),
        'Personas: ' . ($d['personas'] ?: '-'),
        'Fecha:    ' . ($d['fecha_tentativa'] ?: '-'),
        'Origen:   ' . ($d['origen']   ?? '-'),
        'Vino de:  ' . ($d['ref']      ?? 'directo'),
        '',
        $d['mensaje'] ?: '(sin mensaje)',
        '',
        'Responder en: https://' . SITE_DOMAIN . '/admin/consultas.php',
    ];
    $cuerpo  = implode("\n", $lineas) . "\n";
    $asunto  = 'Consulta web: ' . ($d['nombre'] ?? 'sin nombre');
    $cabecera = "From: web@" . SITE_DOMAIN . "\r\n"
              . "Reply-To: " . ($d['email'] ?? '') . "\r\n"
              . "Content-Type: text/plain; charset=UTF-8";

    /* La copia sale de la tabla `ajustes`, no de config.php. Asi se cambia la
       direccion desde la base sin editar codigo, y no hace falta tocar
       config.php, que es el unico archivo que no esta en el repositorio.
       La constante sigue funcionando por si alguna vez conviene fijarla. */
    $destinos = [SITE_EMAIL];
    $copia = trim((string)lrAjuste('mail_copia', ''));
    if ($copia === '' && defined('SITE_EMAIL_COPIA')) $copia = trim((string)SITE_EMAIL_COPIA);
    foreach (preg_split('/[,;\s]+/', $copia, -1, PREG_SPLIT_NO_EMPTY) as $c) {
        if (filter_var($c, FILTER_VALIDATE_EMAIL)) $destinos[] = $c;
    }
    $destinos = array_values(array_unique($destinos));

    foreach ($destinos as $para) {
        if (!@mail($para, $asunto, $cuerpo, $cabecera)) {
            error_log('[LR aviso] no se pudo enviar a ' . $para);
        }
    }
}
