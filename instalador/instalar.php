<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Instalador de un solo archivo (docs/especificacion/instalacion-actualizaciones.md §2). Se sube al
 * hosting y se abre en el navegador: analiza el servidor, pide la base de datos y el administrador,
 * descomprime el paquete firmado que lleva dentro y se borra solo.
 *
 * Va sin sintaxis moderna a propósito: tiene que poder leerse en un PHP viejo para decirle a la
 * persona que su PHP es viejo, en vez de dejarla ante una página en blanco. `alojaflow:empaquetar`
 * cambia los @@VALORES@@ y añade el zip después de __halt_compiler.
 */

error_reporting(E_ALL);
@ini_set('display_errors', '0');
@ini_set('memory_limit', '256M');

define('AF_VERSION', '@@VERSION@@');
define('AF_CLAVE_PUBLICA', '@@CLAVE_PUBLICA@@');
define('AF_PHP_MINIMO', '@@PHP_MINIMO@@');
define('AF_EXTENSIONES', '@@EXTENSIONES@@');
define('AF_AQUI', dirname(__FILE__));
define('AF_COOKIE', 'alojaflow_instalador');

/* ---------------------------------------------------------------- Textos ---------------- */

function af_idioma()
{
    static $idioma = null;
    if ($idioma === null) {
        $aceptados = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']) : 'es';
        $idioma = strpos(substr($aceptados, 0, 2), 'es') === 0 || strpos($aceptados, 'en') !== 0 ? 'es' : 'en';
    }

    return $idioma;
}

function af_t($clave, $valores = array())
{
    static $textos = null;
    if ($textos === null) {
        $textos = af_textos();
    }
    $idioma = af_idioma();
    $texto = isset($textos[$idioma][$clave]) ? $textos[$idioma][$clave] : (isset($textos['es'][$clave]) ? $textos['es'][$clave] : $clave);
    foreach ($valores as $nombre => $valor) {
        $texto = str_replace(':'.$nombre, $valor, $texto);
    }

    return $texto;
}

function af_textos()
{
    return array(
        'es' => array(
            'titulo' => 'Instalar AlojaFlow',
            'paso1' => 'Revisión del servidor',
            'paso2' => 'Tus datos',
            'paso3' => 'Instalación',
            'analizando' => 'Revisando tu servidor…',
            'analisis_ok' => 'Tu servidor es compatible.',
            'analisis_avisos' => 'Tu servidor es compatible, con algunos avisos que puedes atender después.',
            'analisis_error' => 'Hay que corregir lo que aparece en rojo para poder instalar.',
            'reanalizar' => 'Volver a revisar',
            'continuar' => 'Continuar',
            'base_titulo' => 'Base de datos',
            'base_ayuda' => 'Créala vacía en el panel de tu hosting y escribe aquí sus datos.',
            'base_servidor' => 'Servidor',
            'base_servidor_ayuda' => 'Suele ser «localhost». En algunos hostings es otro nombre, como mysql.tudominio.com.',
            'base_nombre' => 'Nombre de la base',
            'base_usuario' => 'Usuario',
            'base_contrasena' => 'Contraseña',
            'base_puerto' => 'Puerto',
            'admin_titulo' => 'Tu usuario',
            'admin_ayuda' => 'Con este correo y contraseña vas a entrar a AlojaFlow.',
            'admin_nombre' => 'Tu nombre',
            'admin_correo' => 'Correo',
            'admin_contrasena' => 'Contraseña (mínimo 12 caracteres)',
            'admin_repite' => 'Repite la contraseña',
            'demo' => 'Crear también un hotel de demostración con datos ficticios, para probar (se quita con un botón)',
            'instalar' => 'Instalar',
            'atras' => 'Atrás',
            'probando_base' => 'Probando la conexión con la base de datos…',
            'p_preparar' => 'Comprobando el paquete firmado',
            'p_extraer' => 'Copiando los archivos',
            'p_proteger' => 'Protegiendo los archivos privados',
            'p_configurar' => 'Guardando la configuración',
            'p_migrar' => 'Creando las tablas',
            'p_admin' => 'Creando tu usuario',
            'p_demo' => 'Creando el hotel de demostración',
            'p_comprobar' => 'Comprobando que abre',
            'p_terminar' => 'Terminando',
            'listo_titulo' => '¡AlojaFlow ya está instalado!',
            'listo_texto' => 'Ya puedes entrar con tu correo y tu contraseña.',
            'entrar' => 'Entrar a AlojaFlow',
            'cron_titulo' => 'Un último paso recomendado: la tarea programada',
            'cron_texto' => 'Sirve para que las sincronizaciones, los mensajes y las copias de seguridad corran a su hora. En el panel de tu hosting, crea una tarea programada (cron) cada minuto, o con el intervalo más corto que te permita, con este comando:',
            'cron_sin' => 'Si no la creas, AlojaFlow igual funciona: esas tareas corren cuando alguien está usando el sistema.',
            'copiar' => 'Copiar',
            'copiado' => 'Copiado',
            'no_borrado' => 'No pude borrar este instalador. Bórralo tú por FTP: :archivo',
            'error_titulo' => 'No se pudo terminar',
            'reintentar' => 'Reintentar',
            'error_red' => 'El servidor respondió con un error. :detalle',
            'ocupado_titulo' => 'Hay otra instalación en curso',
            'ocupado_texto' => 'Este instalador ya se abrió en otro navegador. Si fuiste tú y quieres empezar de nuevo, borra por FTP la carpeta .alojaflow-instalacion y vuelve a abrir esta página.',
            'ya_titulo' => 'AlojaFlow ya está instalado',
            'ya_texto' => 'Por seguridad, este instalador no hace nada en una instalación existente.',
            'sin_construir' => 'Este archivo es la plantilla del instalador. Usa el que genera «php artisan alojaflow:empaquetar» (instalar-alojaflow-x.y.z.php).',
            'php_viejo_extra' => 'El instalador necesita PHP :minimo. Cámbialo en el panel de tu hosting y vuelve a abrir esta página.',
            // Análisis.
            'a_php' => 'Versión de PHP',
            'a_php_valor' => ':actual (se necesita :minimo o más)',
            'a_php_ayuda' => 'Cambia la versión de PHP de este sitio en el panel de tu hosting a 8.3 o 8.4 y vuelve a revisar.',
            'a_ext' => 'Extensiones de PHP',
            'a_ext_ok' => 'Todas las necesarias',
            'a_ext_faltan' => 'Faltan: :faltan',
            'a_ext_ayuda' => 'Actívalas en el panel de tu hosting (selector de PHP o extensiones) o pídeselo a su soporte.',
            'a_escritura' => 'Permiso para escribir',
            'a_escritura_ok' => 'Se puede escribir en :carpeta',
            'a_escritura_no' => 'No se puede escribir en :carpeta',
            'a_escritura_ayuda' => 'Dale permisos 755 a esa carpeta con tu programa de FTP.',
            'a_disco' => 'Espacio libre',
            'a_disco_valor' => ':libre MB libres (se usan unos 150 MB)',
            'a_disco_ayuda' => 'Libera espacio en tu hosting antes de instalar.',
            'a_disco_nomide' => 'No se pudo medir',
            'a_memoria' => 'Memoria de PHP',
            'a_memoria_ayuda' => 'Se recomiendan 256 MB. Puedes subir memory_limit en el panel de tu hosting.',
            'a_tiempo' => 'Tiempo límite de PHP',
            'a_tiempo_valor' => ':segundos segundos',
            'a_tiempo_sin' => 'Sin límite',
            'a_tiempo_ayuda' => 'El instalador trabaja por partes, así que no es problema. Para sincronizaciones largas, conviene subirlo a 120 segundos.',
            'a_subida' => 'Tamaño máximo de subida',
            'a_subida_ayuda' => 'Las actualizaciones pesan unos 25 MB. Si no alcanza, súbelas por FTP o aumenta upload_max_filesize y post_max_size.',
            'a_https' => 'Conexión segura (HTTPS)',
            'a_https_si' => 'Activa',
            'a_https_no' => 'No está activa',
            'a_https_ayuda' => 'Activa el certificado gratuito en el panel de tu hosting: sin HTTPS, las contraseñas viajan sin cifrar.',
            'a_servidor' => 'Servidor web',
            'a_servidor_nginx' => 'En este servidor (:servidor) hay que apuntar el dominio a la carpeta public/ en lugar de esta.',
            'a_carpeta' => 'Carpeta de instalación',
            'a_carpeta_vacia' => ':carpeta está lista',
            'a_carpeta_otros' => 'Ya tiene otros archivos: :archivos',
            'a_carpeta_otros_ayuda' => 'Lo que había en esta carpeta dejará de verse en internet. Lo mejor es usar una carpeta vacía o un subdominio.',
            'a_carpeta_choca' => 'Ya tiene archivos que chocan con AlojaFlow: :archivos',
            'a_carpeta_choca_ayuda' => 'Usa una carpeta vacía o un subdominio.',
            'a_procesos' => 'Tareas en segundo plano',
            'a_procesos_si' => 'Disponibles',
            'a_procesos_no' => 'proc_open está desactivada',
            'a_procesos_ayuda' => 'Las tareas programadas tendrán que correr al usar el sistema, o pide a tu hosting que la active.',
            // Errores.
            'e_datos' => 'Revisa los datos marcados.',
            'e_correo' => 'Escribe un correo válido.',
            'e_nombre' => 'Escribe tu nombre.',
            'e_contrasena' => 'La contraseña debe tener al menos 12 caracteres.',
            'e_repite' => 'Las dos contraseñas no coinciden.',
            'e_base_conexion' => 'No pude conectarme al servidor «:servidor». Revisa el nombre del servidor. Si acabas de crear la base, puede tardar unos minutos en estar disponible.',
            'e_base_acceso' => 'El servidor rechazó ese usuario o contraseña.',
            'e_base_nombre' => 'No existe una base llamada «:base», o tu usuario no tiene acceso a ella. Revisa el nombre.',
            'e_base_version' => 'La base de datos es :version. AlojaFlow necesita MySQL 8.0.16 o MariaDB 10.6 o más nuevas.',
            'e_base_llena' => 'Esta base ya tiene :tablas tablas. Usa una base vacía para no mezclar datos.',
            'e_base_permiso' => 'Tu usuario no puede crear tablas en esta base. Dale todos los permisos sobre ella en el panel de tu hosting.',
            'e_base_otro' => 'La base de datos respondió con un error: :detalle',
            'e_paquete_falta' => 'Este instalador no trae el paquete, y no encontré un archivo alojaflow-:version.zip junto a él. Súbelo a esta misma carpeta.',
            'e_paquete_zip' => 'No se pudo abrir el paquete. Puede haberse dañado al subirlo: vuelve a subir el instalador en modo binario.',
            'e_paquete_firma' => 'La firma del paquete no es válida. El archivo se dañó o fue alterado: descárgalo otra vez.',
            'e_paquete_archivo' => 'El archivo :archivo del paquete no coincide con su firma. Vuelve a subir el instalador en modo binario.',
            'e_escribir' => 'No se pudo escribir :archivo. Revisa los permisos de la carpeta.',
            'e_protegida' => 'Tu servidor no protege los archivos privados en esta carpeta. Apunta el dominio a la carpeta public/ en el panel de tu hosting y luego abre :archivo desde esa nueva dirección. Ya dejé una copia del instalador ahí.',
            'e_htaccess' => 'Tu servidor no aceptó el archivo .htaccess de protección. Apunta el dominio a la carpeta public/ en el panel de tu hosting y luego abre :archivo desde esa nueva dirección. Ya dejé una copia del instalador ahí.',
            'e_no_abre' => 'Los archivos ya están, pero la aplicación respondió con el código :codigo. :detalle',
        ),
        'en' => array(
            'titulo' => 'Install AlojaFlow',
            'paso1' => 'Server check',
            'paso2' => 'Your details',
            'paso3' => 'Installation',
            'analizando' => 'Checking your server…',
            'analisis_ok' => 'Your server is compatible.',
            'analisis_avisos' => 'Your server is compatible, with a few notes you can deal with later.',
            'analisis_error' => 'What appears in red must be fixed before installing.',
            'reanalizar' => 'Check again',
            'continuar' => 'Continue',
            'base_titulo' => 'Database',
            'base_ayuda' => 'Create it empty in your hosting panel and enter its details here.',
            'base_servidor' => 'Server',
            'base_servidor_ayuda' => 'Usually «localhost». Some hosts use another name, such as mysql.yourdomain.com.',
            'base_nombre' => 'Database name',
            'base_usuario' => 'User',
            'base_contrasena' => 'Password',
            'base_puerto' => 'Port',
            'admin_titulo' => 'Your user',
            'admin_ayuda' => 'You will sign in to AlojaFlow with this email and password.',
            'admin_nombre' => 'Your name',
            'admin_correo' => 'Email',
            'admin_contrasena' => 'Password (at least 12 characters)',
            'admin_repite' => 'Repeat the password',
            'demo' => 'Also create a demo hotel with made-up data, to try things out (removed with one button)',
            'instalar' => 'Install',
            'atras' => 'Back',
            'probando_base' => 'Testing the database connection…',
            'p_preparar' => 'Checking the signed package',
            'p_extraer' => 'Copying the files',
            'p_proteger' => 'Protecting the private files',
            'p_configurar' => 'Saving the configuration',
            'p_migrar' => 'Creating the tables',
            'p_admin' => 'Creating your user',
            'p_demo' => 'Creating the demo hotel',
            'p_comprobar' => 'Checking that it opens',
            'p_terminar' => 'Finishing',
            'listo_titulo' => 'AlojaFlow is installed!',
            'listo_texto' => 'You can now sign in with your email and password.',
            'entrar' => 'Go to AlojaFlow',
            'cron_titulo' => 'One last recommended step: the scheduled task',
            'cron_texto' => 'It makes synchronisation, messages and backups run on time. In your hosting panel, create a scheduled task (cron) every minute, or at the shortest interval it allows, with this command:',
            'cron_sin' => 'If you skip it, AlojaFlow still works: those tasks run while someone is using the system.',
            'copiar' => 'Copy',
            'copiado' => 'Copied',
            'no_borrado' => 'I could not delete this installer. Please delete it by FTP: :archivo',
            'error_titulo' => 'It could not finish',
            'reintentar' => 'Try again',
            'error_red' => 'The server replied with an error. :detalle',
            'ocupado_titulo' => 'Another installation is in progress',
            'ocupado_texto' => 'This installer was already opened in another browser. If that was you and you want to start over, delete the .alojaflow-instalacion folder by FTP and open this page again.',
            'ya_titulo' => 'AlojaFlow is already installed',
            'ya_texto' => 'For safety, this installer does nothing on an existing installation.',
            'sin_construir' => 'This file is the installer template. Use the one built by «php artisan alojaflow:empaquetar» (instalar-alojaflow-x.y.z.php).',
            'php_viejo_extra' => 'The installer needs PHP :minimo. Change it in your hosting panel and open this page again.',
            'a_php' => 'PHP version',
            'a_php_valor' => ':actual (:minimo or newer is needed)',
            'a_php_ayuda' => 'Change this site\'s PHP version to 8.3 or 8.4 in your hosting panel and check again.',
            'a_ext' => 'PHP extensions',
            'a_ext_ok' => 'All the required ones',
            'a_ext_faltan' => 'Missing: :faltan',
            'a_ext_ayuda' => 'Enable them in your hosting panel (PHP selector or extensions) or ask its support.',
            'a_escritura' => 'Write permission',
            'a_escritura_ok' => ':carpeta is writable',
            'a_escritura_no' => ':carpeta is not writable',
            'a_escritura_ayuda' => 'Give that folder 755 permissions with your FTP program.',
            'a_disco' => 'Free space',
            'a_disco_valor' => ':libre MB free (about 150 MB are used)',
            'a_disco_ayuda' => 'Free up space on your hosting before installing.',
            'a_disco_nomide' => 'Could not be measured',
            'a_memoria' => 'PHP memory',
            'a_memoria_ayuda' => '256 MB is recommended. You can raise memory_limit in your hosting panel.',
            'a_tiempo' => 'PHP time limit',
            'a_tiempo_valor' => ':segundos seconds',
            'a_tiempo_sin' => 'No limit',
            'a_tiempo_ayuda' => 'The installer works in parts, so this is not a problem. For long synchronisations, 120 seconds is better.',
            'a_subida' => 'Maximum upload size',
            'a_subida_ayuda' => 'Updates weigh about 25 MB. If this is too low, upload them by FTP or raise upload_max_filesize and post_max_size.',
            'a_https' => 'Secure connection (HTTPS)',
            'a_https_si' => 'Active',
            'a_https_no' => 'Not active',
            'a_https_ayuda' => 'Turn on the free certificate in your hosting panel: without HTTPS, passwords travel unencrypted.',
            'a_servidor' => 'Web server',
            'a_servidor_nginx' => 'On this server (:servidor) the domain must point to the public/ folder instead of this one.',
            'a_carpeta' => 'Installation folder',
            'a_carpeta_vacia' => ':carpeta is ready',
            'a_carpeta_otros' => 'It already has other files: :archivos',
            'a_carpeta_otros_ayuda' => 'What was in this folder will no longer be visible online. An empty folder or a subdomain is best.',
            'a_carpeta_choca' => 'It has files that clash with AlojaFlow: :archivos',
            'a_carpeta_choca_ayuda' => 'Use an empty folder or a subdomain.',
            'a_procesos' => 'Background tasks',
            'a_procesos_si' => 'Available',
            'a_procesos_no' => 'proc_open is disabled',
            'a_procesos_ayuda' => 'Scheduled tasks will have to run while the system is in use, or ask your host to enable it.',
            'e_datos' => 'Check the highlighted details.',
            'e_correo' => 'Enter a valid email.',
            'e_nombre' => 'Enter your name.',
            'e_contrasena' => 'The password must have at least 12 characters.',
            'e_repite' => 'The two passwords do not match.',
            'e_base_conexion' => 'I could not connect to server «:servidor». Check the server name. If you just created the database, it may take a few minutes to become available.',
            'e_base_acceso' => 'The server rejected that user or password.',
            'e_base_nombre' => 'There is no database called «:base», or your user has no access to it. Check the name.',
            'e_base_version' => 'The database is :version. AlojaFlow needs MySQL 8.0.16 or MariaDB 10.6 or newer.',
            'e_base_llena' => 'This database already has :tablas tables. Use an empty one so data does not get mixed.',
            'e_base_permiso' => 'Your user cannot create tables in this database. Give it all permissions on it in your hosting panel.',
            'e_base_otro' => 'The database replied with an error: :detalle',
            'e_paquete_falta' => 'This installer does not carry the package, and I found no alojaflow-:version.zip next to it. Upload it to this same folder.',
            'e_paquete_zip' => 'The package could not be opened. It may have been damaged while uploading: upload the installer again in binary mode.',
            'e_paquete_firma' => 'The package signature is not valid. The file was damaged or altered: download it again.',
            'e_paquete_archivo' => 'File :archivo in the package does not match its signature. Upload the installer again in binary mode.',
            'e_escribir' => 'Could not write :archivo. Check the folder permissions.',
            'e_protegida' => 'Your server does not protect the private files in this folder. Point the domain to the public/ folder in your hosting panel and then open :archivo at that new address. I left a copy of the installer there.',
            'e_htaccess' => 'Your server did not accept the protective .htaccess file. Point the domain to the public/ folder in your hosting panel and then open :archivo at that new address. I left a copy of the installer there.',
            'e_no_abre' => 'The files are in place, but the application replied with code :codigo. :detalle',
        ),
    );
}

/* ---------------------------------------------------------------- Rutas ----------------- */

/**
 * Dónde se instala. Si el dominio ya apunta a una carpeta public/ vacía, la instalación va en la
 * carpeta de arriba (la forma más segura); si no, aquí mismo, y el .htaccess de la raíz protege.
 */
function af_destino()
{
    static $destino = null;
    if ($destino !== null) {
        return $destino;
    }
    $destino = AF_AQUI;
    if (basename(AF_AQUI) === 'public') {
        $padre = dirname(AF_AQUI);
        if (is_file($padre.'/artisan') || (is_writable($padre) && count(af_contenido($padre, array('public'))) <= 3)) {
            $destino = $padre;
        }
    }

    return $destino;
}

function af_temporal()
{
    return af_destino().'/.alojaflow-instalacion';
}

/**
 * Lo que hay en una carpeta, sin lo que crean los hostings por su cuenta.
 */
function af_contenido($carpeta, $ignorar = array())
{
    $resultado = array();
    $omitir = array_merge($ignorar, array(
        '.', '..', '.alojaflow-instalacion', '.well-known', '.dh-diag', '.ftpquota', '.htaccess', 'cgi-bin',
        'error_log', 'favicon.gif', 'favicon.ico', 'php.ini', '.user.ini', 'robots.txt',
    ));
    $listado = @scandir($carpeta);
    if ($listado === false) {
        return $resultado;
    }
    foreach ($listado as $nombre) {
        if (! in_array($nombre, $omitir, true) && strpos($nombre, 'instalar') !== 0) {
            $resultado[] = $nombre;
        }
    }

    return $resultado;
}

function af_url_base()
{
    $seguro = (isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off' && $_SERVER['HTTPS'] !== '')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    $anfitrion = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $anfitrion = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', $anfitrion);
    $carpeta = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])) : '';
    $carpeta = rtrim($carpeta, '/.');

    return ($seguro ? 'https://' : 'http://').$anfitrion.$carpeta;
}

function af_es_https()
{
    return strpos(af_url_base(), 'https://') === 0;
}

function af_borrar_carpeta($carpeta)
{
    $listado = @scandir($carpeta);
    if ($listado === false) {
        return;
    }
    foreach ($listado as $nombre) {
        if ($nombre === '.' || $nombre === '..') {
            continue;
        }
        $ruta = $carpeta.'/'.$nombre;
        if (is_dir($ruta) && ! is_link($ruta)) {
            af_borrar_carpeta($ruta);
        } else {
            @unlink($ruta);
        }
    }
    @rmdir($carpeta);
}

/* ---------------------------------------------------------------- Dueño de la instalación -- */

function af_instalado()
{
    $destino = af_destino();

    return is_file($destino.'/storage/app/private/instalado.json') && is_file($destino.'/.env');
}

/**
 * Solo el navegador que abrió primero el instalador puede usarlo. Un candado de más de 6 horas
 * ya se considera abandonado.
 */
function af_comprobar_dueno()
{
    $temporal = af_temporal();
    $candado = $temporal.'/propietario';
    $galleta = isset($_COOKIE[AF_COOKIE]) ? (string) $_COOKIE[AF_COOKIE] : '';
    if (is_file($candado) && filemtime($candado) > time() - 21600) {
        return $galleta !== '' && strcmp(trim((string) @file_get_contents($candado)), sha1($galleta)) === 0;
    }
    if (! is_dir($temporal) && ! @mkdir($temporal, 0755, true)) {
        return true; // Sin carpeta temporal no hay nada que proteger: el análisis lo marca en rojo.
    }
    @file_put_contents($temporal.'/.htaccess', "Require all denied\n");
    $nuevo = function_exists('random_bytes') ? bin2hex(random_bytes(20)) : sha1(uniqid((string) mt_rand(), true));
    @file_put_contents($candado, sha1($nuevo));
    setcookie(AF_COOKIE, $nuevo, 0, '', '', af_es_https(), true);
    $_COOKIE[AF_COOKIE] = $nuevo;

    return true;
}

/* ---------------------------------------------------------------- Análisis del servidor -- */

function af_bytes($valor)
{
    $valor = trim((string) $valor);
    if ($valor === '' || $valor === '-1') {
        return -1;
    }
    $numero = (float) $valor;
    $unidad = strtolower(substr($valor, -1));
    if ($unidad === 'g') {
        return $numero * 1073741824;
    }
    if ($unidad === 'm') {
        return $numero * 1048576;
    }
    if ($unidad === 'k') {
        return $numero * 1024;
    }

    return $numero;
}

function af_fila($titulo, $valor, $estado, $ayuda = '')
{
    return array('titulo' => $titulo, 'valor' => $valor, 'estado' => $estado, 'ayuda' => $ayuda);
}

function af_analizar()
{
    $filas = array();
    $destino = af_destino();

    $php_ok = version_compare(PHP_VERSION, AF_PHP_MINIMO, '>=');
    $filas[] = af_fila(af_t('a_php'), af_t('a_php_valor', array('actual' => PHP_VERSION, 'minimo' => AF_PHP_MINIMO)), $php_ok ? 'ok' : 'error', $php_ok ? '' : af_t('a_php_ayuda'));

    $faltan = array();
    foreach (explode(',', AF_EXTENSIONES) as $extension) {
        if ($extension !== '' && ! extension_loaded($extension)) {
            $faltan[] = $extension;
        }
    }
    $filas[] = $faltan === array()
        ? af_fila(af_t('a_ext'), af_t('a_ext_ok'), 'ok')
        : af_fila(af_t('a_ext'), af_t('a_ext_faltan', array('faltan' => implode(', ', $faltan))), 'error', af_t('a_ext_ayuda'));

    $prueba = $destino.'/.af-escritura-'.mt_rand(1000, 9999);
    $escribe = @file_put_contents($prueba, 'ok') !== false;
    if ($escribe) {
        @unlink($prueba);
    }
    $filas[] = $escribe
        ? af_fila(af_t('a_escritura'), af_t('a_escritura_ok', array('carpeta' => $destino)), 'ok')
        : af_fila(af_t('a_escritura'), af_t('a_escritura_no', array('carpeta' => $destino)), 'error', af_t('a_escritura_ayuda'));

    // Lo que ya hay en la carpeta. Si AlojaFlow ya se estaba instalando, se puede continuar.
    $hay = af_contenido($destino, basename(AF_AQUI) === 'public' ? array('public') : array());
    $reanuda = is_file(af_temporal().'/manifiesto.json');
    $chocan = $reanuda ? array() : array_values(array_intersect($hay, array('app', 'artisan', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'storage', 'vendor', 'index.php', 'composer.json', '.env')));
    if ($chocan !== array()) {
        $filas[] = af_fila(af_t('a_carpeta'), af_t('a_carpeta_choca', array('archivos' => implode(', ', array_slice($chocan, 0, 6)))), 'error', af_t('a_carpeta_choca_ayuda'));
    } elseif ($hay !== array() && ! $reanuda) {
        $filas[] = af_fila(af_t('a_carpeta'), af_t('a_carpeta_otros', array('archivos' => implode(', ', array_slice($hay, 0, 6)))), 'aviso', af_t('a_carpeta_otros_ayuda'));
    } else {
        $filas[] = af_fila(af_t('a_carpeta'), af_t('a_carpeta_vacia', array('carpeta' => $destino)), 'ok');
    }

    $libre = function_exists('disk_free_space') ? @disk_free_space($destino) : false;
    if ($libre === false) {
        $filas[] = af_fila(af_t('a_disco'), af_t('a_disco_nomide'), 'aviso');
    } else {
        $megas = (int) ($libre / 1048576);
        $filas[] = af_fila(af_t('a_disco'), af_t('a_disco_valor', array('libre' => number_format($megas, 0, '.', ','))), $megas < 150 ? 'error' : ($megas < 500 ? 'aviso' : 'ok'), $megas < 500 ? af_t('a_disco_ayuda') : '');
    }

    $memoria = af_bytes(ini_get('memory_limit'));
    $filas[] = af_fila(af_t('a_memoria'), (string) ini_get('memory_limit'), ($memoria !== -1.0 && $memoria !== -1 && $memoria < 134217728) ? 'error' : (($memoria !== -1.0 && $memoria !== -1 && $memoria < 268435456) ? 'aviso' : 'ok'), ($memoria !== -1.0 && $memoria !== -1 && $memoria < 268435456) ? af_t('a_memoria_ayuda') : '');

    $tiempo = (int) ini_get('max_execution_time');
    $filas[] = $tiempo === 0
        ? af_fila(af_t('a_tiempo'), af_t('a_tiempo_sin'), 'ok')
        : af_fila(af_t('a_tiempo'), af_t('a_tiempo_valor', array('segundos' => $tiempo)), $tiempo < 60 ? 'aviso' : 'ok', $tiempo < 60 ? af_t('a_tiempo_ayuda') : '');

    $subida = min(af_bytes(ini_get('upload_max_filesize')), af_bytes(ini_get('post_max_size')));
    $filas[] = af_fila(af_t('a_subida'), (string) ini_get('upload_max_filesize').' / '.(string) ini_get('post_max_size'), ($subida !== -1.0 && $subida < 33554432) ? 'aviso' : 'ok', ($subida !== -1.0 && $subida < 33554432) ? af_t('a_subida_ayuda') : '');

    $filas[] = af_es_https()
        ? af_fila(af_t('a_https'), af_t('a_https_si'), 'ok')
        : af_fila(af_t('a_https'), af_t('a_https_no'), 'aviso', af_t('a_https_ayuda'));

    $desactivadas = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    $procesos = function_exists('proc_open') && ! in_array('proc_open', $desactivadas, true);
    $filas[] = $procesos
        ? af_fila(af_t('a_procesos'), af_t('a_procesos_si'), 'ok')
        : af_fila(af_t('a_procesos'), af_t('a_procesos_no'), 'aviso', af_t('a_procesos_ayuda'));

    $servidor = isset($_SERVER['SERVER_SOFTWARE']) ? (string) $_SERVER['SERVER_SOFTWARE'] : '';
    $nginx = stripos($servidor, 'nginx') !== false && basename(AF_AQUI) !== 'public';
    $filas[] = $nginx
        ? af_fila(af_t('a_servidor'), af_t('a_servidor_nginx', array('servidor' => $servidor)), 'aviso')
        : af_fila(af_t('a_servidor'), $servidor !== '' ? substr($servidor, 0, 60) : PHP_SAPI, 'ok');

    $puede = true;
    $avisos = false;
    foreach ($filas as $fila) {
        if ($fila['estado'] === 'error') {
            $puede = false;
        } elseif ($fila['estado'] === 'aviso') {
            $avisos = true;
        }
    }

    return array('filas' => $filas, 'puede' => $puede, 'avisos' => $avisos);
}

/* ---------------------------------------------------------------- Base de datos ----------- */

function af_error($mensaje, $campo = null, $codigo = 422)
{
    return array('ok' => false, 'error' => $mensaje, 'campo' => $campo, '_codigo' => $codigo);
}

function af_conectar($datos)
{
    $dsn = 'mysql:host='.$datos['servidor'].';port='.(int) $datos['puerto'].';dbname='.$datos['base'].';charset=utf8mb4';

    return new PDO($dsn, $datos['usuario'], $datos['contrasena'], array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10));
}

function af_datos_base($entrada)
{
    return array(
        'servidor' => isset($entrada['servidor']) && trim($entrada['servidor']) !== '' ? trim($entrada['servidor']) : 'localhost',
        'puerto' => isset($entrada['puerto']) && (int) $entrada['puerto'] > 0 ? (int) $entrada['puerto'] : 3306,
        'base' => isset($entrada['base']) ? trim($entrada['base']) : '',
        'usuario' => isset($entrada['usuario']) ? trim($entrada['usuario']) : '',
        'contrasena' => isset($entrada['contrasena']) ? (string) $entrada['contrasena'] : '',
    );
}

function af_probar_base($entrada)
{
    $datos = af_datos_base($entrada);
    if ($datos['base'] === '') {
        return af_error(af_t('e_datos'), 'base');
    }
    if ($datos['usuario'] === '') {
        return af_error(af_t('e_datos'), 'usuario');
    }

    try {
        $pdo = af_conectar($datos);
        $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        $esMaria = stripos($version, 'mariadb') !== false;
        $numero = preg_replace('/^([0-9.]+).*$/', '$1', $version);
        if (version_compare($numero, $esMaria ? '10.6.0' : '8.0.16', '<')) {
            return af_error(af_t('e_base_version', array('version' => $version)), 'servidor');
        }
        $tablas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        if (count($tablas) > 0) {
            return af_error(af_t('e_base_llena', array('tablas' => count($tablas))), 'base');
        }
        try {
            $pdo->exec('CREATE TABLE af_prueba_instalador (id INT PRIMARY KEY)');
            $pdo->exec('DROP TABLE af_prueba_instalador');
        } catch (Exception $e) {
            return af_error(af_t('e_base_permiso'), 'usuario');
        }

        return array('ok' => true, 'conexion' => $esMaria ? 'mariadb' : 'mysql', 'version' => $version);
    } catch (Exception $e) {
        $codigo = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : (int) $e->getCode();
        if ($codigo === 1045) {
            return af_error(af_t('e_base_acceso'), 'contrasena');
        }
        if ($codigo === 1049 || $codigo === 1044) {
            return af_error(af_t('e_base_nombre', array('base' => $datos['base'])), 'base');
        }
        if ($codigo === 2002 || $codigo === 2003 || $codigo === 2005 || $codigo === 2006) {
            return af_error(af_t('e_base_conexion', array('servidor' => $datos['servidor'])), 'servidor');
        }

        return af_error(af_t('e_base_otro', array('detalle' => $e->getMessage())), 'servidor');
    }
}

/* ---------------------------------------------------------------- Paquete ----------------- */

function af_ruta_paquete()
{
    return af_temporal().'/paquete.zip';
}

/**
 * Deja el zip en la carpeta temporal (sale de este mismo archivo o de uno junto a él) y comprueba
 * su firma antes de copiar nada.
 */
function af_preparar()
{
    $temporal = af_temporal();
    if (! is_dir($temporal) && ! @mkdir($temporal, 0755, true)) {
        return af_error(af_t('e_escribir', array('archivo' => $temporal)));
    }
    $paquete = af_ruta_paquete();

    if (! is_file($paquete)) {
        $origen = @fopen(__FILE__, 'rb');
        $hay = $origen !== false && filesize(__FILE__) - __COMPILER_HALT_OFFSET__ > 1024;
        if ($hay) {
            fseek($origen, __COMPILER_HALT_OFFSET__);
            $salida = @fopen($paquete, 'wb');
            if ($salida === false) {
                return af_error(af_t('e_escribir', array('archivo' => $paquete)));
            }
            stream_copy_to_stream($origen, $salida);
            fclose($salida);
        }
        if ($origen !== false) {
            fclose($origen);
        }
        if (! $hay) {
            $junto = AF_AQUI.'/alojaflow-'.AF_VERSION.'.zip';
            if (! is_file($junto)) {
                return af_error(af_t('e_paquete_falta', array('version' => AF_VERSION)));
            }
            if (! @copy($junto, $paquete)) {
                return af_error(af_t('e_escribir', array('archivo' => $paquete)));
            }
        }
    }

    $zip = new ZipArchive();
    if ($zip->open($paquete) !== true) {
        @unlink($paquete);

        return af_error(af_t('e_paquete_zip'));
    }
    $prefijo = 'alojaflow-'.AF_VERSION.'/';
    $manifiesto = $zip->getFromName($prefijo.'manifiesto.json');
    $firma = $zip->getFromName($prefijo.'manifiesto.firma');
    $total = $zip->numFiles;
    $zip->close();

    $publica = base64_decode(AF_CLAVE_PUBLICA, true);
    $firmaCruda = $firma === false ? false : base64_decode(trim($firma), true);
    if ($manifiesto === false || $firmaCruda === false || strlen($firmaCruda) !== SODIUM_CRYPTO_SIGN_BYTES
        || ! sodium_crypto_sign_verify_detached($firmaCruda, $manifiesto, $publica)) {
        @unlink($paquete);

        return af_error(af_t('e_paquete_firma'));
    }
    if (@file_put_contents($temporal.'/manifiesto.json', $manifiesto) === false) {
        return af_error(af_t('e_escribir', array('archivo' => $temporal.'/manifiesto.json')));
    }

    return array('ok' => true, 'total' => $total);
}

function af_segundos_por_tanda()
{
    $maximo = (int) ini_get('max_execution_time');

    return $maximo <= 0 ? 20.0 : max(3.0, min(20.0, $maximo * 0.4));
}

/**
 * Copia archivos hasta agotar el tiempo de esta petición y dice por dónde va; el navegador pide la
 * siguiente tanda. Así un hosting con 30 segundos de límite no corta la instalación. El .htaccess
 * de la raíz se deja para el final: si el servidor no lo acepta, todavía se puede avisar.
 */
function af_extraer($desde)
{
    @set_time_limit(120);
    $inicio = microtime(true);
    $limite = af_segundos_por_tanda();
    $temporal = af_temporal();
    $destino = af_destino();

    $datos = json_decode((string) @file_get_contents($temporal.'/manifiesto.json'), true);
    if (! is_array($datos) || ! isset($datos['archivos'])) {
        return af_error(af_t('e_paquete_firma'));
    }
    $archivos = $datos['archivos'];
    $prefijo = 'alojaflow-'.AF_VERSION.'/';

    $zip = new ZipArchive();
    if ($zip->open(af_ruta_paquete()) !== true) {
        return af_error(af_t('e_paquete_zip'));
    }
    $total = $zip->numFiles;

    for ($i = $desde; $i < $total; $i++) {
        $nombre = $zip->getNameIndex($i);
        if ($nombre === false || substr($nombre, -1) === '/') {
            continue;
        }
        if (strpos($nombre, $prefijo) !== 0) {
            $zip->close();

            return af_error(af_t('e_paquete_firma'));
        }
        $ruta = substr($nombre, strlen($prefijo));
        $esManifiesto = $ruta === 'manifiesto.json' || $ruta === 'manifiesto.firma';
        if (! $esManifiesto && ! isset($archivos[$ruta])) {
            $zip->close();

            return af_error(af_t('e_paquete_archivo', array('archivo' => $ruta)));
        }
        if ($ruta === '.htaccess') {
            continue;
        }
        if (strpos($ruta, '..') !== false || $ruta[0] === '/') {
            $zip->close();

            return af_error(af_t('e_paquete_archivo', array('archivo' => $ruta)));
        }
        $final = af_escribir_del_zip($zip, $nombre, $destino.'/'.$ruta, $esManifiesto ? null : $archivos[$ruta], $ruta);
        if ($final !== true) {
            $zip->close();

            return $final;
        }
        if (microtime(true) - $inicio > $limite) {
            $zip->close();

            return array('ok' => true, 'siguiente' => $i + 1, 'total' => $total);
        }
    }
    $zip->close();

    return array('ok' => true, 'siguiente' => $total, 'total' => $total, 'terminado' => true);
}

function af_escribir_del_zip($zip, $nombre, $archivo, $hash, $ruta)
{
    $carpeta = dirname($archivo);
    if (! is_dir($carpeta) && ! @mkdir($carpeta, 0755, true) && ! is_dir($carpeta)) {
        return af_error(af_t('e_escribir', array('archivo' => $carpeta)));
    }
    $entrada = $zip->getStream($nombre);
    $salida = @fopen($archivo, 'wb');
    if ($entrada === false || $salida === false) {
        return af_error(af_t('e_escribir', array('archivo' => $archivo)));
    }
    stream_copy_to_stream($entrada, $salida);
    fclose($entrada);
    fclose($salida);
    if ($hash !== null && hash_file('sha256', $archivo) !== $hash) {
        @unlink($archivo);

        return af_error(af_t('e_paquete_archivo', array('archivo' => $ruta)));
    }

    return true;
}

/**
 * Pone el .htaccess de la raíz. Después, el navegador comprueba que los archivos privados ya no se
 * pueden abrir; esa petición sale de la propia computadora de quien instala, así que también
 * funciona en hostings que no dejan al servidor llamarse a sí mismo.
 */
function af_proteger()
{
    $destino = af_destino();
    // Si el dominio apunta a otra carpeta, deja lista una copia del instalador en public/.
    if (AF_AQUI === $destino && is_dir($destino.'/public')) {
        @copy(__FILE__, $destino.'/public/'.basename(__FILE__));
    }
    if (is_file($destino.'/.htaccess') && ! is_file($destino.'/.htaccess.antes-de-alojaflow')) {
        @rename($destino.'/.htaccess', $destino.'/.htaccess.antes-de-alojaflow');
    }
    $zip = new ZipArchive();
    if ($zip->open(af_ruta_paquete()) !== true) {
        return af_error(af_t('e_paquete_zip'));
    }
    $datos = json_decode((string) @file_get_contents(af_temporal().'/manifiesto.json'), true);
    $resultado = af_escribir_del_zip($zip, 'alojaflow-'.AF_VERSION.'/.htaccess', $destino.'/.htaccess', $datos['archivos']['.htaccess'], '.htaccess');
    $zip->close();

    return $resultado === true ? array('ok' => true, 'archivo' => basename(__FILE__)) : $resultado;
}

/* ---------------------------------------------------------------- Configuración y Laravel -- */

function af_valor_env($valor)
{
    $valor = (string) $valor;
    if ($valor !== '' && preg_match('/^[A-Za-z0-9_.\-\/:@]+$/', $valor)) {
        return $valor;
    }

    return '"'.str_replace(array('\\', '"', '$'), array('\\\\', '\\"', '\\$'), $valor).'"';
}

function af_configurar($entrada)
{
    $destino = af_destino();
    $prueba = af_probar_base($entrada);
    if (! $prueba['ok']) {
        return $prueba;
    }
    $datos = af_datos_base($entrada);
    $plantilla = @file_get_contents($destino.'/.env.example');
    if ($plantilla === false) {
        return af_error(af_t('e_paquete_archivo', array('archivo' => '.env.example')));
    }
    $valores = array(
        'APP_ENV' => 'production',
        'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
        'APP_DEBUG' => 'false',
        'APP_URL' => af_url_base(),
        'LOG_LEVEL' => 'error',
        'DB_CONNECTION' => $prueba['conexion'],
        'DB_HOST' => $datos['servidor'],
        'DB_PORT' => (string) $datos['puerto'],
        'DB_DATABASE' => $datos['base'],
        'DB_USERNAME' => $datos['usuario'],
        'DB_PASSWORD' => $datos['contrasena'],
    );
    foreach ($valores as $clave => $valor) {
        $linea = $clave.'='.af_valor_env($valor);
        $cuantas = 0;
        $plantilla = preg_replace_callback('/^'.$clave.'=.*$/m', function () use ($linea) {
            return $linea;
        }, $plantilla, 1, $cuantas);
        if ($cuantas === 0) {
            $plantilla .= "\n".$linea."\n";
        }
    }
    if (@file_put_contents($destino.'/.env', $plantilla) === false) {
        return af_error(af_t('e_escribir', array('archivo' => $destino.'/.env')));
    }
    @chmod($destino.'/.env', 0640);

    return array('ok' => true);
}

function af_instalador_laravel()
{
    @set_time_limit(300);
    $destino = af_destino();
    require_once $destino.'/vendor/autoload.php';
    $app = require $destino.'/bootstrap/app.php';
    $app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap();

    return $app->make('App\\Instalacion\\Instalador');
}

function af_validar_admin($entrada)
{
    $nombre = isset($entrada['nombre']) ? trim($entrada['nombre']) : '';
    $correo = isset($entrada['correo']) ? trim($entrada['correo']) : '';
    $contrasena = isset($entrada['admin_contrasena']) ? (string) $entrada['admin_contrasena'] : '';
    $repite = isset($entrada['admin_repite']) ? (string) $entrada['admin_repite'] : '';
    if ($nombre === '') {
        return af_error(af_t('e_nombre'), 'nombre');
    }
    if (filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
        return af_error(af_t('e_correo'), 'correo');
    }
    if (strlen($contrasena) < 12) {
        return af_error(af_t('e_contrasena'), 'admin_contrasena');
    }
    if ($contrasena !== $repite) {
        return af_error(af_t('e_repite'), 'admin_repite');
    }

    return array('ok' => true);
}

/**
 * Comando de PHP para el cron: el de la consola puede ser otra versión que la del sitio.
 */
function af_php_consola()
{
    $menor = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
    $sinPunto = PHP_MAJOR_VERSION.PHP_MINOR_VERSION;
    $candidatos = array(
        '/usr/local/php'.$sinPunto.'/bin/php',
        '/opt/alt/php'.$sinPunto.'/usr/bin/php',
        '/opt/cpanel/ea-php'.$sinPunto.'/root/usr/bin/php',
        '/opt/plesk/php/'.$menor.'/bin/php',
        '/usr/bin/php'.$menor,
        '/usr/local/bin/php'.$menor,
    );
    if (defined('PHP_BINARY') && PHP_BINARY !== '') {
        $candidatos[] = preg_replace('/-(cgi|fpm)[0-9.]*$|php-?fpm[0-9.]*$|lsphp[0-9.]*$/', 'php', PHP_BINARY);
    }
    foreach ($candidatos as $candidato) {
        if (is_string($candidato) && strpos($candidato, '/') === 0 && @is_executable($candidato)) {
            return $candidato;
        }
    }

    return 'php';
}

/**
 * El último error que anotó la aplicación, para que el mensaje del instalador sirva de algo.
 */
function af_ultimo_error()
{
    $registro = af_destino().'/storage/logs/laravel.log';
    if (! is_file($registro)) {
        return '';
    }
    $tamano = filesize($registro);
    $archivo = @fopen($registro, 'rb');
    if ($archivo === false) {
        return '';
    }
    fseek($archivo, max(0, $tamano - 65536));
    $texto = (string) fread($archivo, 65536);
    fclose($archivo);
    if (preg_match_all('/\.(?:ERROR|CRITICAL|ALERT|EMERGENCY): (.{1,300})/', $texto, $coincidencias) > 0) {
        return (string) end($coincidencias[1]);
    }

    return '';
}

function af_terminar()
{
    $destino = af_destino();
    $instalador = af_instalador_laravel();
    $instalador->terminar();
    af_borrar_carpeta(af_temporal());

    $borrado = @unlink(__FILE__);
    // Si el dominio se apuntó a public/ a mitad de camino, queda otra copia en la carpeta de arriba.
    foreach (array($destino.'/'.basename(__FILE__), $destino.'/public/'.basename(__FILE__)) as $copia) {
        if (is_file($copia)) {
            @unlink($copia);
        }
    }

    return array(
        'ok' => true,
        'borrado' => $borrado,
        'archivo' => basename(__FILE__),
        'url' => af_url_base().'/',
        'cron' => 'cd '.$destino.' && '.af_php_consola().' artisan schedule:run',
    );
}

/* ---------------------------------------------------------------- Peticiones -------------- */

function af_responder($datos)
{
    $codigo = isset($datos['_codigo']) ? (int) $datos['_codigo'] : 200;
    unset($datos['_codigo']);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (function_exists('http_response_code')) {
        http_response_code($codigo);
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($datos);
    exit;
}

function af_atender_accion()
{
    ob_start();
    $entrada = json_decode((string) file_get_contents('php://input'), true);
    if (! is_array($entrada)) {
        $entrada = array();
    }
    $accion = isset($entrada['accion']) ? (string) $entrada['accion'] : '';
    if (! isset($_SERVER['HTTP_X_ALOJAFLOW'])) {
        af_responder(array('ok' => false, 'error' => 'Solicitud no válida.', '_codigo' => 400));
    }
    if (af_instalado() && $accion !== 'terminar') {
        af_responder(array('ok' => false, 'error' => af_t('ya_texto'), '_codigo' => 409));
    }
    if (! af_comprobar_dueno()) {
        af_responder(array('ok' => false, 'error' => af_t('ocupado_texto'), '_codigo' => 403));
    }

    try {
        switch ($accion) {
            case 'analizar':
                af_responder(af_analizar());
            case 'probar_base':
                $admin = af_validar_admin($entrada);
                $base = af_probar_base($entrada);
                if (! $base['ok']) {
                    af_responder($base);
                }
                af_responder($admin);
            case 'preparar':
                af_responder(af_preparar());
            case 'extraer':
                af_responder(af_extraer(isset($entrada['desde']) ? (int) $entrada['desde'] : 0));
            case 'proteger':
                af_responder(af_proteger());
            case 'configurar':
                af_responder(af_configurar($entrada));
            case 'migrar':
                af_instalador_laravel()->migrar();
                af_responder(array('ok' => true));
            case 'administrador':
                $admin = af_validar_admin($entrada);
                if (! $admin['ok']) {
                    af_responder($admin);
                }
                af_instalador_laravel()->crearAdministrador(trim($entrada['nombre']), trim($entrada['correo']), (string) $entrada['admin_contrasena'], af_idioma());
                af_responder(array('ok' => true));
            case 'demo':
                af_instalador_laravel()->crearDemo();
                af_responder(array('ok' => true));
            case 'ultimo_error':
                af_responder(array('ok' => true, 'detalle' => af_ultimo_error()));
            case 'terminar':
                af_responder(af_terminar());
        }
        af_responder(array('ok' => false, 'error' => 'Acción desconocida.', '_codigo' => 400));
    } catch (Exception $e) {
        af_responder(array('ok' => false, 'error' => $e->getMessage(), '_codigo' => 500));
    } catch (Throwable $e) {
        af_responder(array('ok' => false, 'error' => $e->getMessage(), '_codigo' => 500));
    }
}

/* ---------------------------------------------------------------- Página ----------------- */

function af_h($texto)
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

function af_estilos()
{
    return <<<'CSS'
:root{--verde:#0f766e;--verde-oscuro:#115e59;--ambar:#b45309;--rojo:#b91c1c;--tinta:#0b1f2a;--gris:#475569;--borde:#cbd5e1;--fondo:#f1f5f9;--tarjeta:#fff;--campo:#fff}
@media (prefers-color-scheme:dark){:root{--verde:#14b8a6;--verde-oscuro:#0d9488;--ambar:#f59e0b;--rojo:#f87171;--tinta:#e2e8f0;--gris:#94a3b8;--borde:#334155;--fondo:#020617;--tarjeta:#0f172a;--campo:#1e293b}}
*{box-sizing:border-box}
body{margin:0;background:var(--fondo);color:var(--tinta);font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
main{max-width:640px;margin:0 auto;padding:24px 16px 48px}
.cabecera{display:flex;align-items:center;gap:12px;margin-bottom:20px}
.cabecera svg{width:44px;height:44px;flex:none}
h1{font-size:1.4rem;margin:0}
.version{color:var(--gris);font-size:.875rem}
.pasos{display:flex;gap:8px;margin:0 0 16px;padding:0;list-style:none;font-size:.875rem;color:var(--gris)}
.pasos li{flex:1;border-top:3px solid var(--borde);padding-top:6px}
.pasos li.activo{border-color:var(--verde);color:var(--tinta);font-weight:600}
.pasos li.hecho{border-color:var(--verde)}
.tarjeta{background:var(--tarjeta);border:1px solid var(--borde);border-radius:12px;padding:20px;margin-bottom:16px}
h2{font-size:1.05rem;margin:0 0 4px}
.ayuda{color:var(--gris);font-size:.875rem;margin:0 0 12px}
.fila{display:flex;gap:10px;padding:10px 0;border-top:1px solid var(--borde)}
.fila:first-child{border-top:0}
.marca{flex:none;width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;font-weight:700;margin-top:1px}
.ok .marca{background:#15803d}.aviso .marca{background:#b45309}.error .marca{background:#b91c1c}
.fila strong{display:block;font-size:.9375rem}
.fila span.valor{color:var(--gris);font-size:.875rem;word-break:break-word}
.fila p{margin:4px 0 0;font-size:.875rem}
label{display:block;font-size:.875rem;font-weight:600;margin:12px 0 4px}
label small{font-weight:400;color:var(--gris)}
input[type=text],input[type=password],input[type=email],input[type=number]{width:100%;padding:11px 12px;border:1px solid var(--borde);border-radius:8px;font:inherit;background:var(--campo);color:var(--tinta)}
input:focus-visible,button:focus-visible,a:focus-visible{outline:3px solid var(--verde);outline-offset:2px}
input.mal{border-color:var(--rojo)}
.casilla{display:flex;gap:10px;align-items:flex-start;font-weight:400;margin-top:0}
.casilla input{margin-top:4px;width:18px;height:18px}
.doble{display:grid;grid-template-columns:3fr 1fr;gap:12px}
button,.boton{display:inline-block;min-height:44px;padding:10px 18px;border:0;border-radius:8px;background:var(--verde);color:#fff;font:inherit;font-weight:600;cursor:pointer;text-decoration:none}
@media (prefers-color-scheme:dark){button,.boton{color:#042f2e}}
button.secundario{background:transparent;color:var(--tinta);border:1px solid var(--borde)}
button:disabled{opacity:.5;cursor:not-allowed}
.acciones{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
.mensaje{padding:12px;border-radius:8px;margin:12px 0 0;font-size:.9375rem}
.mensaje.error{background:color-mix(in oklab,var(--rojo) 12%,transparent);border:1px solid var(--rojo)}
.mensaje.bien{background:color-mix(in oklab,#15803d 12%,transparent);border:1px solid #15803d}
.progreso{height:10px;background:var(--borde);border-radius:6px;overflow:hidden;margin:12px 0}
.progreso div{height:100%;width:0;background:var(--verde);transition:width .2s}
.tareas{list-style:none;margin:0;padding:0;font-size:.9375rem}
.tareas li{padding:4px 0;color:var(--gris)}
.tareas li.activa{color:var(--tinta);font-weight:600}
.tareas li.hecha{color:var(--tinta)}
.tareas li.hecha::before{content:"✓ ";color:#15803d;font-weight:700}
code.comando{display:block;padding:12px;border-radius:8px;background:var(--fondo);border:1px solid var(--borde);font-size:.8125rem;word-break:break-all;margin:8px 0}
[hidden]{display:none!important}
CSS;
}

function af_logo()
{
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" aria-hidden="true"><rect x="4" y="4" width="56" height="56" rx="14" fill="#0F766E"/><g fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="5"><path d="M17 47 L32 17 L47 47" stroke="#fff"/><path d="M13 39 Q22.5 31 32 39 T51 39" stroke="#F59E0B"/></g></svg>';
}

function af_pagina_simple($titulo, $texto, $codigo = 200)
{
    if (function_exists('http_response_code')) {
        http_response_code($codigo);
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="'.af_idioma().'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>'.af_h($titulo).'</title><style>'.af_estilos().'</style></head><body><main><div class="cabecera">'.af_logo().'<h1>'.af_h($titulo).'</h1></div><div class="tarjeta"><p>'.af_h($texto).'</p></div></main></body></html>';
    exit;
}

function af_pagina()
{
    $textosJs = array();
    foreach (array('analizando', 'analisis_ok', 'analisis_avisos', 'analisis_error', 'probando_base', 'p_preparar', 'p_extraer', 'p_proteger', 'p_configurar', 'p_migrar', 'p_admin', 'p_demo', 'p_comprobar', 'p_terminar', 'error_red', 'reintentar', 'copiar', 'copiado', 'no_borrado', 'e_protegida', 'e_htaccess', 'e_no_abre') as $clave) {
        $textosJs[$clave] = af_t($clave);
    }
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    header('X-Frame-Options: DENY');
    $servidorPorOmision = 'localhost'; ?>
<!doctype html>
<html lang="<?php echo af_idioma(); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title><?php echo af_h(af_t('titulo')); ?></title>
<style><?php echo af_estilos(); ?></style>
</head>
<body>
<main>
    <div class="cabecera">
        <?php echo af_logo(); ?>
        <div><h1><?php echo af_h(af_t('titulo')); ?></h1><div class="version"><?php echo af_h(AF_VERSION); ?></div></div>
    </div>
    <ol class="pasos" id="pasos">
        <li class="activo"><?php echo af_h(af_t('paso1')); ?></li>
        <li><?php echo af_h(af_t('paso2')); ?></li>
        <li><?php echo af_h(af_t('paso3')); ?></li>
    </ol>

    <section class="tarjeta" id="vista-analisis" aria-live="polite">
        <h2><?php echo af_h(af_t('paso1')); ?></h2>
        <p class="ayuda" id="analisis-estado"><?php echo af_h(af_t('analizando')); ?></p>
        <div id="analisis-filas"></div>
        <div class="acciones">
            <button type="button" id="continuar" disabled><?php echo af_h(af_t('continuar')); ?></button>
            <button type="button" class="secundario" id="reanalizar"><?php echo af_h(af_t('reanalizar')); ?></button>
        </div>
    </section>

    <form id="vista-datos" hidden novalidate>
        <section class="tarjeta">
            <h2><?php echo af_h(af_t('base_titulo')); ?></h2>
            <p class="ayuda"><?php echo af_h(af_t('base_ayuda')); ?></p>
            <div class="doble">
                <div>
                    <label for="servidor"><?php echo af_h(af_t('base_servidor')); ?> <small><?php echo af_h(af_t('base_servidor_ayuda')); ?></small></label>
                    <input type="text" id="servidor" name="servidor" value="<?php echo af_h($servidorPorOmision); ?>" autocomplete="off" required>
                </div>
                <div>
                    <label for="puerto"><?php echo af_h(af_t('base_puerto')); ?></label>
                    <input type="number" id="puerto" name="puerto" value="3306" min="1" max="65535">
                </div>
            </div>
            <label for="base"><?php echo af_h(af_t('base_nombre')); ?></label>
            <input type="text" id="base" name="base" autocomplete="off" required>
            <label for="usuario"><?php echo af_h(af_t('base_usuario')); ?></label>
            <input type="text" id="usuario" name="usuario" autocomplete="off" required>
            <label for="contrasena"><?php echo af_h(af_t('base_contrasena')); ?></label>
            <input type="password" id="contrasena" name="contrasena" autocomplete="new-password">
        </section>
        <section class="tarjeta">
            <h2><?php echo af_h(af_t('admin_titulo')); ?></h2>
            <p class="ayuda"><?php echo af_h(af_t('admin_ayuda')); ?></p>
            <label for="nombre"><?php echo af_h(af_t('admin_nombre')); ?></label>
            <input type="text" id="nombre" name="nombre" autocomplete="name" required>
            <label for="correo"><?php echo af_h(af_t('admin_correo')); ?></label>
            <input type="email" id="correo" name="correo" autocomplete="email" required>
            <label for="admin_contrasena"><?php echo af_h(af_t('admin_contrasena')); ?></label>
            <input type="password" id="admin_contrasena" name="admin_contrasena" autocomplete="new-password" minlength="12" required>
            <label for="admin_repite"><?php echo af_h(af_t('admin_repite')); ?></label>
            <input type="password" id="admin_repite" name="admin_repite" autocomplete="new-password" minlength="12" required>
            <label class="casilla" style="margin-top:16px"><input type="checkbox" id="demo" name="demo"> <span><?php echo af_h(af_t('demo')); ?></span></label>
        </section>
        <p class="mensaje error" id="datos-error" role="alert" hidden></p>
        <div class="acciones">
            <button type="submit" id="instalar"><?php echo af_h(af_t('instalar')); ?></button>
            <button type="button" class="secundario" id="atras"><?php echo af_h(af_t('atras')); ?></button>
        </div>
    </form>

    <section class="tarjeta" id="vista-progreso" hidden aria-live="polite">
        <h2><?php echo af_h(af_t('paso3')); ?></h2>
        <div class="progreso"><div id="barra"></div></div>
        <ul class="tareas" id="tareas"></ul>
        <div id="progreso-error" hidden>
            <p class="mensaje error" role="alert" id="progreso-mensaje"></p>
            <div class="acciones"><button type="button" id="reintentar"><?php echo af_h(af_t('reintentar')); ?></button></div>
        </div>
    </section>

    <section id="vista-lista" hidden>
        <div class="tarjeta">
            <h2><?php echo af_h(af_t('listo_titulo')); ?></h2>
            <p><?php echo af_h(af_t('listo_texto')); ?></p>
            <p class="mensaje error" id="no-borrado" hidden></p>
            <div class="acciones"><a class="boton" id="entrar" href="./"><?php echo af_h(af_t('entrar')); ?></a></div>
        </div>
        <div class="tarjeta">
            <h2><?php echo af_h(af_t('cron_titulo')); ?></h2>
            <p class="ayuda"><?php echo af_h(af_t('cron_texto')); ?></p>
            <code class="comando" id="cron"></code>
            <div class="acciones"><button type="button" class="secundario" id="copiar"><?php echo af_h(af_t('copiar')); ?></button></div>
            <p class="ayuda" style="margin-top:12px"><?php echo af_h(af_t('cron_sin')); ?></p>
        </div>
    </section>
</main>
<script>
(function () {
    'use strict';
    var T = <?php echo json_encode($textosJs); ?>;
    var datos = {};
    function $(id) { return document.getElementById(id); }
    function reemplazar(texto, valores) {
        Object.keys(valores || {}).forEach(function (clave) { texto = texto.split(':' + clave).join(valores[clave]); });
        return texto;
    }
    function llamar(accion, extra) {
        var cuerpo = Object.assign({accion: accion}, extra || {});
        return fetch(location.pathname, {method: 'POST', headers: {'Content-Type': 'application/json', 'X-AlojaFlow': '1'}, credentials: 'same-origin', body: JSON.stringify(cuerpo)})
            .then(function (r) {
                return r.text().then(function (t) {
                    try { return JSON.parse(t); }
                    catch (e) { var error = new Error(reemplazar(T.error_red, {detalle: '(' + r.status + ')'})); error.sinRespuesta = true; throw error; }
                });
            });
    }
    function pasoActivo(n) {
        Array.prototype.forEach.call($('pasos').children, function (li, i) {
            li.className = i === n ? 'activo' : (i < n ? 'hecho' : '');
        });
    }
    function mostrar(vista) {
        ['vista-analisis', 'vista-datos', 'vista-progreso', 'vista-lista'].forEach(function (id) { $(id).hidden = id !== vista; });
        window.scrollTo(0, 0);
    }

    function analizar() {
        $('analisis-estado').textContent = T.analizando;
        $('continuar').disabled = true;
        llamar('analizar').then(function (r) {
            var cont = $('analisis-filas');
            cont.textContent = '';
            (r.filas || []).forEach(function (f) {
                var fila = document.createElement('div');
                fila.className = 'fila ' + f.estado;
                var marca = document.createElement('span');
                marca.className = 'marca';
                marca.setAttribute('aria-hidden', 'true');
                marca.textContent = f.estado === 'ok' ? '✓' : (f.estado === 'aviso' ? '!' : '×');
                var texto = document.createElement('div');
                var titulo = document.createElement('strong');
                titulo.textContent = f.titulo;
                var valor = document.createElement('span');
                valor.className = 'valor';
                valor.textContent = f.valor;
                texto.appendChild(titulo);
                texto.appendChild(valor);
                if (f.ayuda) { var ayuda = document.createElement('p'); ayuda.textContent = f.ayuda; texto.appendChild(ayuda); }
                fila.appendChild(marca);
                fila.appendChild(texto);
                cont.appendChild(fila);
            });
            if (r.error) { $('analisis-estado').textContent = r.error; return; }
            $('analisis-estado').textContent = !r.puede ? T.analisis_error : (r.avisos ? T.analisis_avisos : T.analisis_ok);
            $('continuar').disabled = !r.puede;
        }).catch(function (e) { $('analisis-estado').textContent = e.message; });
    }
    $('reanalizar').addEventListener('click', analizar);
    $('continuar').addEventListener('click', function () { pasoActivo(1); mostrar('vista-datos'); });
    $('atras').addEventListener('click', function () { pasoActivo(0); mostrar('vista-analisis'); });

    $('vista-datos').addEventListener('submit', function (ev) {
        ev.preventDefault();
        var form = ev.target;
        Array.prototype.forEach.call(form.querySelectorAll('input'), function (i) { i.classList.remove('mal'); });
        var error = $('datos-error');
        error.hidden = true;
        datos = {};
        Array.prototype.forEach.call(form.querySelectorAll('input'), function (i) { datos[i.name] = i.type === 'checkbox' ? i.checked : i.value; });
        $('instalar').disabled = true;
        llamar('probar_base', datos).then(function (r) {
            $('instalar').disabled = false;
            if (!r.ok) {
                error.textContent = r.error;
                error.hidden = false;
                if (r.campo && form.elements[r.campo]) { form.elements[r.campo].classList.add('mal'); form.elements[r.campo].focus(); }
                return;
            }
            pasoActivo(2);
            mostrar('vista-progreso');
            iniciar();
        }).catch(function (e) {
            $('instalar').disabled = false;
            error.textContent = e.message;
            error.hidden = false;
        });
    });

    var tareas = [];
    function preparaTareas() {
        tareas = [
            ['p_preparar', function () { return exigir(llamar('preparar')).then(function (r) { totalArchivos = r.total; }); }],
            ['p_extraer', function () { return extraer(0); }],
            ['p_proteger', proteger],
            ['p_configurar', function () { return exigir(llamar('configurar', datos)); }],
            ['p_migrar', function () { return exigir(llamar('migrar')); }],
            ['p_admin', function () { return exigir(llamar('administrador', datos)); }]
        ];
        if (datos.demo) { tareas.push(['p_demo', function () { return exigir(llamar('demo')); }]); }
        tareas.push(['p_comprobar', comprobar]);
        tareas.push(['p_terminar', terminar]);
        var lista = $('tareas');
        lista.textContent = '';
        tareas.forEach(function (t, i) { var li = document.createElement('li'); li.id = 'tarea-' + i; li.textContent = T[t[0]]; lista.appendChild(li); });
    }
    var totalArchivos = 1;
    var indice = 0;
    function exigir(promesa) {
        return promesa.then(function (r) { if (!r.ok) { throw new Error(r.error); } return r; });
    }
    function extraer(desde) {
        return exigir(llamar('extraer', {desde: desde})).then(function (r) {
            if (r.terminado) { desdeArchivo = 0; return; }
            desdeArchivo = r.siguiente;
            $('tarea-1').textContent = T.p_extraer + ' (' + Math.round(100 * r.siguiente / r.total) + ' %)';
            return extraer(r.siguiente);
        });
    }
    var desdeArchivo = 0;
    function protegidos() {
        var archivos = ['composer.json', '.env.example', 'artisan'];
        return Promise.all(archivos.map(function (a) {
            return fetch(a + '?comprobar=' + Date.now(), {cache: 'no-store', credentials: 'omit', redirect: 'manual'}).then(function (r) {
                if (r.status !== 200) { return true; }
                return r.text().then(function (t) { return t.indexOf('juanelodi/alojaflow') === -1 && t.indexOf('APP_NAME=') === -1 && t.indexOf('LARAVEL_START') === -1; });
            }).catch(function () { return true; });
        })).then(function (r) { return r.every(Boolean); });
    }
    function proteger() {
        return exigir(llamar('proteger')).then(function (r) {
            // Si el .htaccess rompe el sitio, el propio instalador deja de responder.
            return llamar('analizar').catch(function () { var e = new Error(reemplazar(T.e_htaccess, {archivo: r.archivo})); e.final = true; throw e; }).then(function () {
                return protegidos().then(function (bien) {
                    if (!bien) { var e = new Error(reemplazar(T.e_protegida, {archivo: r.archivo})); e.final = true; throw e; }
                });
            });
        });
    }
    function comprobar() {
        return fetch('login?comprobar=' + Date.now(), {cache: 'no-store', credentials: 'omit', redirect: 'manual'}).then(function (r) {
            // Con redirect manual, una redirección (por ejemplo, a https) llega con estado 0.
            if (r.status === 200 || r.status === 0) { return; }
            return llamar('ultimo_error').catch(function () { return {}; }).then(function (e) {
                throw new Error(reemplazar(T.e_no_abre, {codigo: String(r.status), detalle: e.detalle || ''}));
            });
        });
    }
    function terminar() {
        return exigir(llamar('terminar')).then(function (r) {
            $('cron').textContent = r.cron;
            $('entrar').href = r.url;
            if (!r.borrado) { $('no-borrado').textContent = reemplazar(T.no_borrado, {archivo: r.archivo}); $('no-borrado').hidden = false; }
        });
    }
    function correr() {
        if (indice >= tareas.length) {
            $('barra').style.width = '100%';
            mostrar('vista-lista');
            return;
        }
        var li = $('tarea-' + indice);
        li.className = 'activa';
        $('barra').style.width = Math.round(100 * indice / tareas.length) + '%';
        tareas[indice][1]().then(function () {
            li.className = 'hecha';
            indice++;
            correr();
        }).catch(function (e) {
            li.className = 'activa';
            $('progreso-mensaje').textContent = e.message;
            $('progreso-error').hidden = false;
            $('reintentar').hidden = !!e.final;
        });
    }
    function iniciar() {
        preparaTareas();
        indice = 0;
        correr();
    }
    $('reintentar').addEventListener('click', function () {
        $('progreso-error').hidden = true;
        if (indice === 1 && desdeArchivo > 0) {
            var li = $('tarea-1');
            extraer(desdeArchivo).then(function () { li.className = 'hecha'; indice++; correr(); }).catch(function (e) { $('progreso-mensaje').textContent = e.message; $('progreso-error').hidden = false; });
            return;
        }
        correr();
    });
    $('copiar').addEventListener('click', function () {
        if (navigator.clipboard) { navigator.clipboard.writeText($('cron').textContent).then(function () { $('copiar').textContent = T.copiado; }); }
    });
    analizar();
})();
</script>
</body>
</html>
<?php
}

/* ---------------------------------------------------------------- Inicio ----------------- */

if (strpos(AF_VERSION, '@@') === 0) {
    af_pagina_simple(af_t('titulo'), af_t('sin_construir'), 500);
}

if (version_compare(PHP_VERSION, '5.6.0', '<') || ! function_exists('json_encode')) {
    af_pagina_simple(af_t('titulo'), af_t('a_php').': '.PHP_VERSION.'. '.af_t('php_viejo_extra', array('minimo' => AF_PHP_MINIMO)), 500);
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    af_atender_accion();
}

if (af_instalado()) {
    @unlink(__FILE__);
    af_pagina_simple(af_t('ya_titulo'), af_t('ya_texto'));
}

if (! af_comprobar_dueno()) {
    af_pagina_simple(af_t('ocupado_titulo'), af_t('ocupado_texto'), 403);
}

af_pagina();

__halt_compiler();
