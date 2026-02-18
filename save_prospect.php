<?php
header('Content-Type: application/json; charset=utf-8');

$debug = true;

function str_len_safe($value)
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value);
    }
    return strlen($value);
}

function post_value($key, $default = '')
{
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

try {
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(array('ok' => false, 'message' => 'Metodo no permitido'));
        exit;
    }

    // Ajusta estos 4 valores con los de tu hosting.
    $host = 'localhost';
    $dbName = 'u174757005_nexo';
    $dbUser = 'u174757005_sfalah_nexo';
    $dbPass = 'Yorke1986!';

    $nombre = trim((string)post_value('nombre', ''));
    $correo = trim((string)post_value('correo', ''));
    $edad = (int)post_value('edad', 0);
    $riesgo = trim((string)post_value('riesgo', ''));
    $objetivos = post_value('objetivos', array());
    $capital = (float)post_value('capital', 0);
    $cuentaUsaRaw = trim((string)post_value('cuentaUsa', ''));
    $mensaje = trim((string)post_value('mensaje', ''));

    if ($nombre === '' || str_len_safe($nombre) > 150) {
        http_response_code(422);
        echo json_encode(array('ok' => false, 'message' => 'Nombre invalido'));
        exit;
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || str_len_safe($correo) > 190) {
        http_response_code(422);
        echo json_encode(array('ok' => false, 'message' => 'Correo invalido'));
        exit;
    }

    if ($edad < 18 || $edad > 100) {
        http_response_code(422);
        echo json_encode(array('ok' => false, 'message' => 'Edad fuera de rango'));
        exit;
    }

    $riesgosPermitidos = array('conservador', 'moderado', 'agresivo');
    if (!in_array($riesgo, $riesgosPermitidos, true)) {
        http_response_code(422);
        echo json_encode(array('ok' => false, 'message' => 'Riesgo invalido'));
        exit;
    }

    if (!is_array($objetivos) || count($objetivos) === 0) {
        http_response_code(422);
        echo json_encode(array('ok' => false, 'message' => 'Selecciona al menos un objetivo'));
        exit;
    }

    $objetivosPermitidos = array('crecer-capital', 'preservar-capital', 'flujo-caja', 'diversificacion');
    $objetivosFiltrados = array();
    foreach ($objetivos as $objetivo) {
        $objetivoLimpio = trim((string)$objetivo);
        if (in_array($objetivoLimpio, $objetivosPermitidos, true)) {
            $objetivosFiltrados[] = $objetivoLimpio;
        }
    }
    $objetivosFiltrados = array_values(array_unique($objetivosFiltrados));

    if (count($objetivosFiltrados) === 0) {
        http_response_code(422);
        echo json_encode(array('ok' => false, 'message' => 'Objetivos invalidos'));
        exit;
    }

    if ($capital < 5000) {
        http_response_code(422);
        echo json_encode(array('ok' => false, 'message' => 'Capital minimo: 5000'));
        exit;
    }

    if ($cuentaUsaRaw !== 'si' && $cuentaUsaRaw !== 'no') {
        http_response_code(422);
        echo json_encode(array('ok' => false, 'message' => 'Valor de cuenta en EE.UU. invalido'));
        exit;
    }
    $cuentaUsa = $cuentaUsaRaw === 'si' ? 1 : 0;

    if ($mensaje !== '' && str_len_safe($mensaje) > 5000) {
        http_response_code(422);
        echo json_encode(array('ok' => false, 'message' => 'Mensaje demasiado largo'));
        exit;
    }

    $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? substr((string)$_SERVER['REMOTE_ADDR'], 0, 45) : null;
    $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 255) : null;

    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        )
    );

    $stmt = $pdo->prepare(
        'INSERT INTO prospectos (nombre, correo, edad, riesgo, objetivos, capital, cuenta_usa, mensaje, ip_address, user_agent)
         VALUES (:nombre, :correo, :edad, :riesgo, :objetivos, :capital, :cuenta_usa, :mensaje, :ip_address, :user_agent)'
    );

    $stmt->execute(array(
        ':nombre' => $nombre,
        ':correo' => $correo,
        ':edad' => $edad,
        ':riesgo' => $riesgo,
        ':objetivos' => json_encode($objetivosFiltrados),
        ':capital' => $capital,
        ':cuenta_usa' => $cuentaUsa,
        ':mensaje' => $mensaje === '' ? null : $mensaje,
        ':ip_address' => $ipAddress,
        ':user_agent' => $userAgent,
    ));

    echo json_encode(array(
        'ok' => true,
        'message' => 'Perfil guardado correctamente',
    ));
} catch (Exception $e) {
    error_log('save_prospect.php error: ' . $e->getMessage());
    http_response_code(500);

    if ($debug) {
        echo json_encode(array(
            'ok' => false,
            'message' => 'Error interno',
            'debug' => $e->getMessage(),
            'code' => $e->getCode(),
        ));
    } else {
        echo json_encode(array(
            'ok' => false,
            'message' => 'No se pudo guardar el perfil',
        ));
    }
}
