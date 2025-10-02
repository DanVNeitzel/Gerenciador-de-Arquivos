<?php
/**
 * Sistema de Compartilhamento de Arquivos - Página de Acesso
 * Este arquivo processa os links de compartilhamento gerados pelo sistema
 */

// Configurações de segurança
ini_set('display_errors', 0);
error_reporting(0);

// Função para retornar erro em JSON
function shareError($code, $message) {
    http_response_code($code);
    if (isset($_GET['json'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => ['code' => $code, 'msg' => $message]]);
    } else {
        // Página HTML de erro
        echo renderErrorPage($code, $message);
    }
    exit;
}

// Função para renderizar página de erro
function renderErrorPage($code, $message) {
    return '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro - Compartilhamento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        body { background: linear-gradient(120deg, #1f8657 0%, #191f24 100%); color: white; }
        .error-container { min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .error-card { background: rgba(0,0,0,0.8); border-radius: 15px; padding: 2rem; max-width: 500px; text-align: center; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-card">
            <i class="fa fa-exclamation-triangle fa-3x mb-3 text-warning"></i>
            <h2>Erro ' . $code . '</h2>
            <p class="lead">' . htmlspecialchars($message) . '</p>
            <a href="#" onclick="history.back()" class="btn btn-outline-light">
                <i class="fa fa-arrow-left"></i> Voltar
            </a>
        </div>
    </div>
</body>
</html>';
}

// Função para renderizar página de login
function renderLoginPage($share_id, $hash, $error = null) {
    $errorHtml = $error ? '<div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> ' . htmlspecialchars($error) . '</div>' : '';
    
    return '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso ao Arquivo Compartilhado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        body { 
            background: linear-gradient(120deg, #1f8657 0%, #191f24 100%); 
            color: white; 
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .share-container { 
            background: rgba(0,0,0,0.8); 
            border-radius: 15px; 
            padding: 2rem; 
            max-width: 400px; 
            width: 100%;
        }
        .form-control {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
        }
        .form-control:focus {
            background: rgba(255,255,255,0.2);
            border-color: #1f8657;
            color: white;
            box-shadow: 0 0 0 0.2rem rgba(31, 134, 87, 0.25);
        }
        .form-control::placeholder {
            color: rgba(255,255,255,0.6);
        }
    </style>
</head>
<body>
    <div class="share-container">
        <div class="text-center mb-4">
            <i class="fa fa-lock fa-3x mb-3 text-warning"></i>
            <h3>Arquivo Protegido</h3>
            <p class="text-muted">Este arquivo está protegido por senha</p>
        </div>
        
        ' . $errorHtml . '
        
        <form method="post">
            <input type="hidden" name="share_id" value="' . htmlspecialchars($share_id) . '">
            <input type="hidden" name="hash" value="' . htmlspecialchars($hash) . '">
            
            <div class="mb-3">
                <label for="password" class="form-label">
                    <i class="fa fa-key"></i> Senha de Acesso
                </label>
                <input type="password" class="form-control" id="password" name="password" 
                       placeholder="Digite a senha" required autofocus>
            </div>
            
            <div class="d-grid">
                <button type="submit" class="btn btn-success">
                    <i class="fa fa-unlock"></i> Acessar Arquivo
                </button>
            </div>
        </form>
        
        <div class="text-center mt-3">
            <small class="text-muted">
                <i class="fa fa-shield"></i> Conexão segura e criptografada
            </small>
        </div>
    </div>
</body>
</html>';
}

// Obter parâmetros da URL
$share_id = isset($_GET['id']) ? trim($_GET['id']) : '';
$hash = isset($_GET['hash']) ? trim($_GET['hash']) : '';

// Validar parâmetros
if (!$share_id || !preg_match('/^[a-f0-9]{32}$/', $share_id)) {
    shareError(400, 'Link de compartilhamento inválido');
}

if (!$hash || !preg_match('/^[a-f0-9]{64}$/', $hash)) {
    shareError(400, 'Hash de segurança inválido');
}

// Localizar arquivo de compartilhamento
$share_dir = __DIR__ . '/.shares';
$share_file = $share_dir . '/' . $share_id . '.json';

if (!file_exists($share_file)) {
    shareError(404, 'Link de compartilhamento não encontrado ou expirado');
}

// Carregar dados do compartilhamento
$share_data = @json_decode(@file_get_contents($share_file), true);
if (!$share_data) {
    shareError(500, 'Erro ao carregar dados do compartilhamento');
}

// Verificar hash de segurança
if ($share_data['security_hash'] !== $hash) {
    shareError(403, 'Hash de segurança inválido');
}

// Verificar expiração
if (time() > $share_data['expires_at']) {
    // Limpar arquivo expirado
    @unlink($share_file);
    shareError(410, 'Este link de compartilhamento expirou');
}

// Verificar limite de downloads
if ($share_data['max_downloads'] > 0 && $share_data['downloads'] >= $share_data['max_downloads']) {
    shareError(429, 'Limite de downloads atingido');
}

// Verificar se arquivo ainda existe
$file_path = __DIR__ . '/' . $share_data['file_path'];
if (!file_exists($file_path) || !is_file($file_path)) {
    shareError(404, 'Arquivo não encontrado no servidor');
}

// Verificar se é POST (tentativa de acesso com senha)
if ($_POST) {
    $provided_password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Se arquivo tem senha, verificar
    if (!empty($share_data['password'])) {
        if (!$provided_password || !password_verify($provided_password, $share_data['password'])) {
            echo renderLoginPage($share_id, $hash, 'Senha incorreta');
            exit;
        }
    }
    
    // Senha correta ou arquivo sem senha - prosseguir com download
} else {
    // Se arquivo tem senha e não é POST, mostrar formulário de login
    if (!empty($share_data['password'])) {
        echo renderLoginPage($share_id, $hash);
        exit;
    }
}

// Incrementar contador de downloads
$share_data['downloads']++;
$share_data['last_access'] = time();

// Salvar dados atualizados
@file_put_contents($share_file, json_encode($share_data, JSON_PRETTY_PRINT));

// Se chegou até aqui, liberar o download
$filename = basename($share_data['file_path']);
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file_path);

// Headers para download
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime_type);
header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file_path));

// Limpar buffer de saída
ob_clean();
flush();

// Enviar arquivo
$fp = fopen($file_path, 'rb');
if ($fp) {
    while (!feof($fp)) {
        echo fread($fp, 8192);
        flush();
    }
    fclose($fp);
} else {
    shareError(500, 'Erro ao ler o arquivo');
}

// Se atingiu limite de downloads, remover o compartilhamento
if ($share_data['max_downloads'] > 0 && $share_data['downloads'] >= $share_data['max_downloads']) {
    @unlink($share_file);
}

exit;
?>