<?php
header('Content-Type: application/json; charset=utf-8');

$debug = false;

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

function verify_turnstile_token($secretKey, $token, $remoteIp)
{
    if ($secretKey === '' || $token === '') {
        return false;
    }

    $payload = http_build_query(array(
        'secret' => $secretKey,
        'response' => $token,
        'remoteip' => $remoteIp,
    ));

    $rawResponse = false;
    if (function_exists('curl_init')) {
        $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $rawResponse = curl_exec($ch);
        curl_close($ch);
    } else {
        $context = stream_context_create(array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 10,
            ),
        ));
        $rawResponse = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
    }

    if ($rawResponse === false) {
        return false;
    }

    $decoded = json_decode($rawResponse, true);
    return is_array($decoded) && !empty($decoded['success']);
}

try {
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(array('ok' => false, 'message' => 'Metodo no permitido'));
        exit;
    }

    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/\\') : '';
    $domainRoot = $documentRoot !== '' ? dirname($documentRoot) : '';
    $configPath = $domainRoot !== '' ? $domainRoot . '/Env/secureConfig.php' : '';

    $secureConfig = array();
    if ($configPath !== '' && is_readable($configPath)) {
        $loaded = require $configPath;
        if (is_array($loaded)) {
            $secureConfig = $loaded;
        }
    }

    $host = (is_array($secureConfig) && isset($secureConfig['DB_HOST']) && is_string($secureConfig['DB_HOST']))
        ? trim($secureConfig['DB_HOST'])
        : '';
    $dbName = (is_array($secureConfig) && isset($secureConfig['DB_NAME']) && is_string($secureConfig['DB_NAME']))
        ? trim($secureConfig['DB_NAME'])
        : '';
    $dbUser = (is_array($secureConfig) && isset($secureConfig['DB_USER']) && is_string($secureConfig['DB_USER']))
        ? trim($secureConfig['DB_USER'])
        : '';
    $dbPass = (is_array($secureConfig) && isset($secureConfig['DB_PASS']) && is_string($secureConfig['DB_PASS']))
        ? $secureConfig['DB_PASS']
        : '';

    $missingDbKeys = array();
    if ($host === '') {
        $missingDbKeys[] = 'DB_HOST';
    }
    if ($dbName === '') {
        $missingDbKeys[] = 'DB_NAME';
    }
    if ($dbUser === '') {
        $missingDbKeys[] = 'DB_USER';
    }
    if ($dbPass === '') {
        $missingDbKeys[] = 'DB_PASS';
    }

    if (count($missingDbKeys) > 0) {
        http_response_code(500);
        echo json_encode(array(
            'ok' => false,
            'message' => 'Configuracion de base de datos incompleta',
        ));
        exit;
    }

    $turnstileSecretKey = '';
    if (is_array($secureConfig) && isset($secureConfig['TURNSTILE_SECRET_KEY']) && is_string($secureConfig['TURNSTILE_SECRET_KEY'])) {
        $turnstileSecretKey = trim($secureConfig['TURNSTILE_SECRET_KEY']);
    }
    $turnstileEnabled = true;
    if (is_array($secureConfig) && isset($secureConfig['TURNSTILE_ENABLED'])) {
        $parsedToggle = filter_var($secureConfig['TURNSTILE_ENABLED'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($parsedToggle !== null) {
            $turnstileEnabled = $parsedToggle;
        }
    }

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
    $honeypot = trim((string)post_value('website', ''));
    $turnstileToken = trim((string)post_value('cf-turnstile-response', ''));

    if ($honeypot !== '') {
        http_response_code(422);
        echo json_encode(array('ok' => false, 'message' => 'Solicitud invalida'));
        exit;
    }

    if ($turnstileEnabled) {
        if ($turnstileSecretKey === '') {
            http_response_code(500);
            echo json_encode(array('ok' => false, 'message' => 'Configuracion anti-bot incompleta'));
            exit;
        }

        if ($turnstileToken === '') {
            http_response_code(422);
            echo json_encode(array('ok' => false, 'message' => 'Completa la verificacion anti-bot'));
            exit;
        }

        if (!verify_turnstile_token($turnstileSecretKey, $turnstileToken, $ipAddress)) {
            http_response_code(422);
            echo json_encode(array('ok' => false, 'message' => 'Verificacion anti-bot fallida'));
            exit;
        }
    }

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
