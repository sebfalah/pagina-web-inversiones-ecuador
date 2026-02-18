<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Metodo no permitido']);
    exit;
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$dbName = getenv('DB_NAME') ?: 'nexo_capital';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

$nombre = trim((string)($_POST['nombre'] ?? ''));
$correo = trim((string)($_POST['correo'] ?? ''));
$edad = (int)($_POST['edad'] ?? 0);
$riesgo = trim((string)($_POST['riesgo'] ?? ''));
$objetivos = $_POST['objetivos'] ?? [];
$capital = (float)($_POST['capital'] ?? 0);
$cuentaUsaRaw = trim((string)($_POST['cuentaUsa'] ?? ''));
$mensaje = trim((string)($_POST['mensaje'] ?? ''));

if ($nombre === '' || mb_strlen($nombre) > 150) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Nombre invalido']);
    exit;
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 190) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Correo invalido']);
    exit;
}

if ($edad < 18 || $edad > 100) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Edad fuera de rango']);
    exit;
}

$riesgosPermitidos = ['conservador', 'moderado', 'agresivo'];
if (!in_array($riesgo, $riesgosPermitidos, true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Riesgo invalido']);
    exit;
}

if (!is_array($objetivos) || count($objetivos) === 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Selecciona al menos un objetivo']);
    exit;
}

$objetivosPermitidos = ['crecer-capital', 'preservar-capital', 'flujo-caja', 'diversificacion'];
$objetivosFiltrados = [];
foreach ($objetivos as $objetivo) {
    $objetivoLimpio = trim((string)$objetivo);
    if (in_array($objetivoLimpio, $objetivosPermitidos, true)) {
        $objetivosFiltrados[] = $objetivoLimpio;
    }
}
$objetivosFiltrados = array_values(array_unique($objetivosFiltrados));

if (count($objetivosFiltrados) === 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Objetivos invalidos']);
    exit;
}

if ($capital < 5000) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Capital minimo: 5000']);
    exit;
}

if ($cuentaUsaRaw !== 'si' && $cuentaUsaRaw !== 'no') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Valor de cuenta en EE.UU. invalido']);
    exit;
}
$cuentaUsa = $cuentaUsaRaw === 'si' ? 1 : 0;

if ($mensaje !== '' && mb_strlen($mensaje) > 5000) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Mensaje demasiado largo']);
    exit;
}

$ipAddress = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
$userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $stmt = $pdo->prepare(
        'INSERT INTO prospectos (nombre, correo, edad, riesgo, objetivos, capital, cuenta_usa, mensaje, ip_address, user_agent)
         VALUES (:nombre, :correo, :edad, :riesgo, :objetivos, :capital, :cuenta_usa, :mensaje, :ip_address, :user_agent)'
    );

    $stmt->execute([
        ':nombre' => $nombre,
        ':correo' => $correo,
        ':edad' => $edad,
        ':riesgo' => $riesgo,
        ':objetivos' => json_encode($objetivosFiltrados, JSON_UNESCAPED_UNICODE),
        ':capital' => $capital,
        ':cuenta_usa' => $cuentaUsa,
        ':mensaje' => $mensaje === '' ? null : $mensaje,
        ':ip_address' => $ipAddress !== '' ? $ipAddress : null,
        ':user_agent' => $userAgent !== '' ? $userAgent : null,
    ]);

    echo json_encode([
        'ok' => true,
        'message' => 'Perfil guardado correctamente',
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'No se pudo guardar el perfil',
    ]);
}
