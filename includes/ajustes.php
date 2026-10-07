<?php
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
