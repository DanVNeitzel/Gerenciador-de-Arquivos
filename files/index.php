<?php
// Endpoint para informações da pasta
if (isset($_GET['do']) && $_GET['do'] === 'folderinfo') {
  $folder = $_GET['folder'] ?? '';
  if ($folder === '' || !is_dir($folder)) {
    echo json_encode(['success' => false]);
    exit;
  }
  $name = basename($folder);
  $size = 0;
  $files = 0;
  date_default_timezone_set('America/Sao_Paulo');
  $modified = date('d/m/Y H:i:s', filemtime($folder));
  $dirs = 0;
  $rii = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
  );
  foreach (scandir($folder) as $entry) {
    if ($entry === '.' || $entry === '..')
      continue;
    $path = $folder . DIRECTORY_SEPARATOR . $entry;
    if (is_dir($path))
      $dirs++;
    if (is_file($path))
      $files++;
    $size += filesize($path); // <-- soma o tamanho dos arquivos
  }
  // Formata tamanho
  function formatBytes($bytes, $precision = 2)
  {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
  }
  echo json_encode([
    'success' => true,
    'name' => $name,
    'size' => formatBytes($size),
    'modified' => $modified,
    'files' => $files,
    'dirs' => $dirs
  ]);
  exit;
}

// --- LIXEIRA: ENDPOINTS ---
// Caminho absoluto da lixeira
$TRASH_DIR = __DIR__ . '/.trash';
$TRASH_META = $TRASH_DIR . '/meta.json';

// Garante que a lixeira e o meta existem
if (!file_exists($TRASH_DIR))
  @mkdir($TRASH_DIR, 0777, true);
if (!file_exists($TRASH_META))
  @file_put_contents($TRASH_META, '{}');

// Listar arquivos da lixeira
if (isset($_GET['do']) && $_GET['do'] === 'listtrash') {
  $meta = @json_decode(@file_get_contents($TRASH_META), true) ?: [];
  $results = [];
  foreach ($meta as $trash_name => $info) {
    $trash_path = $TRASH_DIR . '/' . $trash_name;
    if (file_exists($trash_path)) {
      $results[] = [
        'trash_name' => $trash_name,
        'name' => $info['name'],
        'original' => $info['original'],
        'deleted_at' => $info['deleted_at'],
        'is_dir' => isset($info['is_dir']) ? $info['is_dir'] : is_dir($trash_path),
      ];
    }
  }
  echo json_encode(['success' => true, 'results' => $results]);
  exit;
}

// Restaurar arquivo/pasta da lixeira
if (isset($_POST['do']) && $_POST['do'] === 'restoretrash') {
  $meta = @json_decode(@file_get_contents($TRASH_META), true) ?: [];
  $trash = isset($_POST['trash']) ? $_POST['trash'] : '';
  if (!isset($meta[$trash]))
    err(404, 'Item não encontrado na lixeira.');
  $trash_path = $TRASH_DIR . '/' . $trash;
  $original = $meta[$trash]['original'];
  $target = __DIR__ . '/' . $original;
  // Garante que o diretório de destino existe
  $parent = dirname($target);
  if (!is_dir($parent))
    @mkdir($parent, 0777, true);
  // Não sobrescreve se já existir
  if (file_exists($target))
    err(409, 'Já existe um arquivo/pasta no destino original.');
  $ok = @rename($trash_path, $target);
  if ($ok) {
    unset($meta[$trash]);
    @file_put_contents($TRASH_META, json_encode($meta, JSON_PRETTY_PRINT));
    echo json_encode(['success' => true]);
  } else {
    err(500, 'Falha ao restaurar.');
  }
  exit;
}

// Excluir permanentemente arquivo/pasta da lixeira
if (isset($_POST['do']) && $_POST['do'] === 'deletetrash') {
  $meta = @json_decode(@file_get_contents($TRASH_META), true) ?: [];
  $trash = isset($_POST['trash']) ? $_POST['trash'] : '';
  if (!isset($meta[$trash]))
    err(404, 'Item não encontrado na lixeira.');
  $trash_path = $TRASH_DIR . '/' . $trash;
  if (is_dir($trash_path)) {
    rmrf($trash_path);
  } else {
    @unlink($trash_path);
  }
  unset($meta[$trash]);
  @file_put_contents($TRASH_META, json_encode($meta, JSON_PRETTY_PRINT));
  echo json_encode(['success' => true]);
  exit;
}

// Restaurar todos os itens da lixeira
if (isset($_POST['do']) && $_POST['do'] === 'restorealltrash') {
  $meta = @json_decode(@file_get_contents($TRASH_META), true) ?: [];
  $restored = 0;
  foreach ($meta as $trash => $info) {
    $trash_path = $TRASH_DIR . '/' . $trash;
    $original = $info['original'];
    $target = __DIR__ . '/' . $original;
    $parent = dirname($target);
    if (!is_dir($parent))
      @mkdir($parent, 0777, true);
    if (!file_exists($target) && file_exists($trash_path)) {
      if (@rename($trash_path, $target)) {
        unset($meta[$trash]);
        $restored++;
      }
    }
  }
  @file_put_contents($TRASH_META, json_encode($meta, JSON_PRETTY_PRINT));
  echo json_encode(['success' => true, 'restored' => $restored]);
  exit;
}

// Esvaziar a lixeira (excluir todos permanentemente)
if (isset($_POST['do']) && $_POST['do'] === 'emptytrash') {
  $meta = @json_decode(@file_get_contents($TRASH_META), true) ?: [];
  $deleted = 0;
  foreach ($meta as $trash => $info) {
    $trash_path = $TRASH_DIR . '/' . $trash;
    if (file_exists($trash_path)) {
      if (is_dir($trash_path)) {
        rmrf($trash_path);
      } else {
        @unlink($trash_path);
      }
      $deleted++;
    }
    unset($meta[$trash]);
  }
  @file_put_contents($TRASH_META, json_encode($meta, JSON_PRETTY_PRINT));
  echo json_encode(['success' => true, 'deleted' => $deleted]);
  exit;
}

// Ao excluir, mover para lixeira (ajuste no endpoint de delete)

//Desativar relatório de erro
error_reporting(error_reporting() & ~E_NOTICE);

//Opções de Segurança
$allow_delete = true; // Defina como false para desativar o botão de exclusão e excluir a solicitação POST.
$allow_upload = true; // Defina como true para permitir o upload de arquivos
$allow_create_folder = true; // Defina como false para desativar a criação de pasta
$allow_create_file = true; // ADDED: permitir criação de novos arquivos de texto via UI
$allow_direct_link = true; // Defina como false para permitir apenas downloads e não link direto
$allow_show_folders = true; // Defina como false para ocultar todos os subdiretórios
$configTime = 5; // Defina o tempo de expiração da sessão em minutos (padrão: 5 minutos)
$max_upload_size_mb = 200; // Limite máximo de tamanho para upload em MB (padrão: 50MB)

$disallowed_patterns = ['*.php'];  // Padrões de arquivos não permitidos para upload, download, criação e zip (usado fnmatch)
$hidden_patterns = ['*.php', '*.css', '*.js', '.*']; // Extensões ocultas no índice do diretório

$SENHA = 'abc123';  // Defina a senha, para acessar o gerenciador de arquivos ... (opcional)

// ADDED: controle de acesso ao modal de configurações (editar este valor no arquivo para ativar)
$permissionAdmin = true; // se true -> mostra o ícone de configuração e habilita salvar (altere manualmente neste arquivo)

// ADDED: carregar configurações salvas em JSON (se existir)
$config_file = __DIR__ . '/files_config.json';
if (file_exists($config_file)) {
  $cfg = @json_decode(@file_get_contents($config_file), true);
  if (is_array($cfg)) {
    if (isset($cfg['allow_delete']))
      $allow_delete = (bool) $cfg['allow_delete'];
    if (isset($cfg['allow_upload']))
      $allow_upload = (bool) $cfg['allow_upload'];
    if (isset($cfg['allow_create_folder']))
      $allow_create_folder = (bool) $cfg['allow_create_folder'];
    if (isset($cfg['allow_create_file']))
      $allow_create_file = (bool) $cfg['allow_create_file'];
    if (isset($cfg['allow_direct_link']))
      $allow_direct_link = (bool) $cfg['allow_direct_link'];
    if (isset($cfg['allow_show_folders']))
      $allow_show_folders = (bool) $cfg['allow_show_folders'];
    if (isset($cfg['configTime']))
      $configTime = intval($cfg['configTime']);
    if (isset($cfg['max_upload_size_mb']))
      $max_upload_size_mb = intval($cfg['max_upload_size_mb']);
    if (isset($cfg['disallowed_patterns']) && is_array($cfg['disallowed_patterns']))
      $disallowed_patterns = $cfg['disallowed_patterns'];
    if (isset($cfg['hidden_patterns']) && is_array($cfg['hidden_patterns']))
      $hidden_patterns = $cfg['hidden_patterns'];
    if (isset($cfg['SENHA']))
      $SENHA = $cfg['SENHA'];
    // ADDED: carregar permissionAdmin (controle de acesso ao modal de configurações)
    if (isset($cfg['permissionAdmin']))
      $permissionAdmin = (bool) $cfg['permissionAdmin'];
  }
}

if ($SENHA) {

  session_start();
  // Expiração automática da sessão baseada em inatividade (valor em milissegundos)
  $timeout = $configTime * 60 * 1000;
  if (isset($_SESSION['LAST_ACTIVITY']) && ((time() * 1000) - $_SESSION['LAST_ACTIVITY'] > $timeout)) {
    session_unset();
    session_destroy();
    header('Location: ?');
    exit;
  }
  $_SESSION['LAST_ACTIVITY'] = time() * 1000;
  if (!$_SESSION['_sfm_allowed']) {
    // sha1, and random bytes to thwart timing attacks.  Not meant as secure hashing.
    $t = bin2hex(openssl_random_pseudo_bytes(10));
    if ($_POST['p'] && sha1($t . $_POST['p']) === sha1($t . $SENHA)) {
      $_SESSION['_sfm_allowed'] = true;
      header('Location: ?');
    }
    echo '
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
      <meta charset="UTF-8">
      <title>[Login] - Gerenciador de Arquivos</title>
      <link data-rh="true" rel="icon" href="../files_assets/icons/files-logo.svg">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"/>
      <style>
        /* Destaque visual para links de pastas e arquivos */
        .name-dir {
          color: #ffc107 !important; /* amarelo pasta */
          font-weight: 600;
        }
        .name-arc {
          color: #bfc9d1 !important; /* cinza claro arquivo */
          font-weight: 500;
        }
        html, body {
          height: 100%;
          margin: 0;
          padding: 0;
          background: linear-gradient(120deg, #1f8657 0%, #191f24 100%);
          font-family: "Segoe UI", Arial, sans-serif;
          width: 100vw;
          height: 100vh;
          transition: background-image 3s ease;
        }
        body {
          display: flex;
          align-items: center;
          justify-content: center;
          min-height: 100vh;
          position: relative;
          z-index: 1;
        }
        .bg-slideshow, .bg-slideshow-next {
          position: fixed;
          top: 0; left: 0;
          width: 100vw; height: 100vh;
          z-index: 0;
          background-size: cover;
          background-position: center;
          background-repeat: no-repeat;
          transition: opacity 1s ease;
          pointer-events: none;
          opacity: 1;
        }
        .bg-slideshow-next {
          opacity: 0;
        }
        .login-container {
          background: rgb(0 0 0 / 25%);
          border-radius: 18px;
          box-shadow: 0 8px 32px 0 rgba(31, 135, 85, 0.37);
          backdrop-filter: blur(25px);
          -webkit-backdrop-filter: blur(25px);
          border: 1px solid rgba(255, 255, 255, 0.18);
          padding: 36px 36px 36px 36px;
          display: flex;
          flex-direction: column;
          align-items: center;
          min-width: 340px;
          z-index: 2;
          position: relative;
        }
        .login-avatar {
          width: 96px;
          height: 96px;
          border-radius: 50%;
          background: #e0f2e9;
          margin-bottom: 18px;
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 56px;
          color: #1f8657;
          box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .login-title {
          font-size: 1.5rem;
          color: #ffffff;
          margin-bottom: 8px;
          font-weight: 500;
          letter-spacing: 0.5px;
        }
        .login-subtitle {
          color: #ffffff;
          font-size: 1rem;
          margin-bottom: 24px;
        }
        .login-form {
          width: 100%;
          display: flex;
          flex-direction: column;
          align-items: stretch;
        }
        .login-input {
          padding: 12px 16px;
          border-radius: 8px;
          border: 1.5px solid #8fd6b4;
          background: rgba(255,255,255,0.15);
          color: #fff;
          font-size: 1.1rem;
          margin-bottom: 18px;
          outline: none;
          transition: border 0.2s;
        }
        .login-input:focus {
          border: 1.5px solid #1f8657;
          background: rgba(255,255,255,0.22);
        }
        .login-btn {
          background: #1f8657;
          color: #fff;
          border: none;
          border-radius: 8px;
          padding: 12px 0;
          font-size: 1.1rem;
          font-weight: 500;
          cursor: pointer;
          transition: background 0.2s;
          margin-bottom: 8px;
        }
        .login-btn:hover {
          background: #176b44;
        }
        .login-footer {
          margin-top: 18px;
          color: #1f8657;
          font-size: 0.95rem;
          text-align: center;
        }
        @media (max-width: 480px) {
          .login-container {
            min-width: 90vw;
            padding: 32px 8vw 24px 8vw;
          }
        }
        ::-webkit-input-placeholder {
          color: #ffffff;
        }

        :-moz-placeholder { /* Firefox 18- */
          color: #ffffff;
        }

        ::-moz-placeholder {  /* Firefox 19+ */
          color: #ffffff;
        }

        :-ms-input-placeholder {  
          color: #ffffff;
        }
        .hide {
          display: none;
        }
        /* Garante que o estilo de pastas funcione igual nos dois modos */
        .is_dir .name,
        .is-dir .name {
          color: #ffc107 !important;
          font-weight: 600;
        }
        .empty {
          color: #777;
          font-style: italic;
          text-align: center;
          padding: 3em 0;
        }
        /* Esconder a barra de rolagem customizada do sidebar */
        .sidebar::-webkit-scrollbar {
          width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
          background: rgba(255, 255, 255, 0.1);
        }

        .sidebar::-webkit-scrollbar-thumb {
          background: rgba(78, 205, 196, 0.5);
          border-radius: 3px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
          background: rgba(78, 205, 196, 0.8);
        }
      </style>
    </head>
    <body>
      <div class="bg-slideshow"></div>
      <div class="bg-slideshow-next"></div>
      <div class="login-container">
        <div class="login-avatar hide">
          <i class="fa fa-user"></i>
        </div>
        <div class="login-title">Bem-vindo</div>
        <div class="login-subtitle">Digite a senha para acessar</div>
        <form class="login-form" action="?" method="post" autocomplete="off">
          <input class="login-input" type="password" name="p" placeholder="Senha" autofocus required>
          <button class="login-btn" type="submit">Entrar</button>
        </form>
        <div class="login-footer">
          <span><i class="fa fa-folder"></i> Gerenciador de Arquivos</span>
        </div>
      </div>
      <script>
      // Lista de imagens de fundo (adicione URLs absolutas ou relativas conforme necessário)
      var bgImages = [
        "https://images.pexels.com/photos/417074/pexels-photo-417074.jpeg",
        "https://images.pexels.com/photos/15905161/pexels-photo-15905161.jpeg",
        "https://images.pexels.com/photos/5272416/pexels-photo-5272416.jpeg",
        "https://images.pexels.com/photos/10059884/pexels-photo-10059884.jpeg",
        "https://images.pexels.com/photos/2049422/pexels-photo-2049422.jpeg",
      ];
      function shuffle(arr) {
        for (let i = arr.length - 1; i > 0; i--) {
          const j = Math.floor(Math.random() * (i + 1));
          [arr[i], arr[j]] = [arr[j], arr[i]];
        }
        return arr;
      }
      bgImages = shuffle(bgImages);
      var idx = 0;
      var fadeDuration = 3000; // ms

      var bg1 = document.querySelector(".bg-slideshow");
      var bg2 = document.querySelector(".bg-slideshow-next");
      var showingFirst = true;

      function setBg(first) {
        var el = first ? bg1 : bg2;
        el.style.backgroundImage = "url(\"" + bgImages[idx] + "\")";
      }

      // Inicializa
      setBg(true);
      bg1.style.opacity = 1;
      bg2.style.opacity = 0;

      function fadeTransition() {
        var nextIdx = (idx + 1) % bgImages.length;
        var showEl = showingFirst ? bg2 : bg1;
        var hideEl = showingFirst ? bg1 : bg2;

        // Prepara próxima imagem
        showEl.style.backgroundImage = "url(\"" + bgImages[nextIdx] + "\")";
        showEl.style.transition = "opacity " + (fadeDuration/1000) + "s";
        hideEl.style.transition = "opacity " + (fadeDuration/1000) + "s";

        // Garante que showEl está por cima
        showEl.style.zIndex = 1;
        hideEl.style.zIndex = 0;

        // Inicia fade
        showEl.style.opacity = 0;
        // Força reflow para garantir transição
        void showEl.offsetWidth;
        showEl.style.opacity = 1;
        hideEl.style.opacity = 0;

        // Após fade, troca referência
        setTimeout(function() {
          idx = nextIdx;
          showingFirst = !showingFirst;
        }, fadeDuration);
      }

      setInterval(fadeTransition, 6000);

      </script>
    </body>
    </html>
    ';
    exit;
  }
}

// Deve estar em UTF-8 ou `basename` não funcionará
setlocale(LC_ALL, 'pt_BR.UTF-8');

$tmp_dir = dirname($_SERVER['SCRIPT_FILENAME']);
if (DIRECTORY_SEPARATOR === '\\')
  $tmp_dir = str_replace('/', DIRECTORY_SEPARATOR, $tmp_dir);
$tmp = get_absolute_path($tmp_dir . '/' . $_REQUEST['file']);

if ($tmp === false)
  err(404, 'File or Directory Not Found');
if (substr($tmp, 0, strlen($tmp_dir)) !== $tmp_dir)
  err(403, "Forbidden");
if (strpos($_REQUEST['file'], DIRECTORY_SEPARATOR) === 0)
  err(403, "Forbidden");
if (preg_match('@^.+://@', $_REQUEST['file'])) {
  err(403, "Forbidden");
}


if (!$_COOKIE['_sfm_xsrf'])
  setcookie('_sfm_xsrf', bin2hex(openssl_random_pseudo_bytes(16)));
if ($_POST) {
  if ($_COOKIE['_sfm_xsrf'] !== $_POST['xsrf'] || !$_POST['xsrf'])
    err(403, "XSRF Failure");
}

$file = $_REQUEST['file'] ?: '.';

// ADDED: endpoint de logout — encerra sessão e redireciona para tela de senha
if (isset($_GET['do']) && $_GET['do'] === 'logout') {
  // Always start session before manipulating it
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
  }
  session_unset();
  session_destroy();
  // Expire session cookie
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
      session_name(),
      '',
      time() - 42000,
      $params["path"],
      $params["domain"],
      $params["secure"],
      $params["httponly"]
    );
  }
  header('Location: ?');
  exit;
}

if ($_GET['do'] == 'list') {
  if (is_dir($file)) {
    $directory = $file;
    $result = [];
    $files = array_diff(scandir($directory), ['.', '..']);
    foreach ($files as $entry) {
      if (!is_entry_ignored($entry, $allow_show_folders, $hidden_patterns)) {
        $i = $directory . '/' . $entry;
        $stat = stat($i);
        $result[] = [
          'mtime' => $stat['mtime'],
          'size' => $stat['size'],
          'name' => basename($i),
          'path' => preg_replace('@^\./@', '', $i),
          'is_dir' => is_dir($i),
          'is_deleteable' => $allow_delete && ((!is_dir($i) && is_writable($directory)) ||
            (is_dir($i) && is_writable($directory) && is_recursively_deleteable($i))),
          'is_readable' => is_readable($i),
          'is_writable' => is_writable($i),
          'is_executable' => is_executable($i),
        ];
      }
    }
    usort($result, function ($f1, $f2) {
      $f1_key = ($f1['is_dir'] ?: 2) . $f1['name'];
      $f2_key = ($f2['is_dir'] ?: 2) . $f2['name'];
      return $f1_key > $f2_key;
    });
  } else {
    err(412, "Não é um diretório");
  }
  echo json_encode(['success' => true, 'is_writable' => is_writable($file), 'results' => $result]);
  exit;
} elseif ($_GET['do'] == 'listfolders') {
  // Endpoint para listar todas as pastas recursivamente
  if (is_dir($file)) {
    $result = [];

    function scanFoldersRecursively($dir, &$result, $allow_show_folders, $hidden_patterns)
    {
      if (!is_readable($dir))
        return;

      $files = @scandir($dir);
      if ($files === false)
        return;

      $files = array_diff($files, ['.', '..']);
      foreach ($files as $entry) {
        if (!is_entry_ignored($entry, $allow_show_folders, $hidden_patterns)) {
          $fullPath = $dir . '/' . $entry;
          if (is_dir($fullPath)) {
            $relativePath = preg_replace('@^\./@', '', $fullPath);
            $result[] = [
              'name' => basename($fullPath),
              'path' => $relativePath,
              'is_dir' => true,
              'is_writable' => is_writable($fullPath)
            ];
            // Recursivamente escanear subpastas
            scanFoldersRecursively($fullPath, $result, $allow_show_folders, $hidden_patterns);
          }
        }
      }
    }

    scanFoldersRecursively($file, $result, $allow_show_folders, $hidden_patterns);

    // Ordenar por caminho
    usort($result, function ($a, $b) {
      return strcmp($a['path'], $b['path']);
    });

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'folders' => $result, 'count' => count($result), 'base_path' => $file]);
  } else {
    err(412, "Não é um diretório");
  }
  exit;
} elseif ($_POST['do'] == 'delete') {
  if ($allow_delete) {
    // Mover para lixeira
    $trash_id = uniqid('trash_', true);
    $basename = basename($file);
    // Função para normalizar nome (remover acentos e caracteres especiais)
    function normalize_filename($str)
    {
      // Remove acentos
      $str = preg_replace('/[áàãâä]/ui', 'a', $str);
      $str = preg_replace('/[éèêë]/ui', 'e', $str);
      $str = preg_replace('/[íìîï]/ui', 'i', $str);
      $str = preg_replace('/[óòõôö]/ui', 'o', $str);
      $str = preg_replace('/[úùûü]/ui', 'u', $str);
      $str = preg_replace('/[ç]/ui', 'c', $str);
      // Remove outros caracteres especiais
      $str = preg_replace('/[^a-z0-9_\.-]/i', '_', $str);
      return $str;
    }
    $normalized = normalize_filename($basename);
    $trash_name = $trash_id . '_' . $normalized;
    $meta = @json_decode(@file_get_contents($TRASH_META), true) ?: [];
    $trash_path = $TRASH_DIR . '/' . $trash_name;
    $ok = @rename($file, $trash_path);
    if ($ok) {
      // Horário UTC-3 (Brasília)
      $dt = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
      $meta[$trash_name] = [
        'name' => $basename,
        'original' => $file,
        'deleted_at' => $dt->format('Y-m-d H:i:s'),
      ];
      @file_put_contents($TRASH_META, json_encode($meta, JSON_PRETTY_PRINT));
    } else {
      // Se não conseguiu mover, tenta excluir normalmente
      rmrf($file);
    }
  }
  exit;
} elseif ($_POST['do'] == 'mkdir' && $allow_create_folder) {
  // don't allow actions outside root. we also filter out slashes to catch args like './../outside'
  $dir = $_POST['name'];
  $dir = str_replace('/', '', $dir);
  if (substr($dir, 0, 2) === '..')
    exit;
  chdir($file);
  @mkdir($_POST['name']);
  exit;
}

// ADDED: endpoint para criar novo arquivo de texto
elseif ($_POST['do'] == 'createfile' && $allow_create_file) {
  $folder = isset($_POST['file']) && $_POST['file'] !== '' ? $_POST['file'] : '.';
  if (strpos($folder, '..') !== false)
    err(403, "Pasta proibida.");

  $name = isset($_POST['name']) ? trim($_POST['name']) : '';
  if ($name === '')
    err(400, "Nome do arquivo ausente.");

  // proibir barras, backslashes e parent traversal
  if (strpos($name, '/') !== false || strpos($name, '\\') !== false || strpos($name, '..') !== false)
    err(400, "Nome de arquivo inválido.");

  // checar padrões proibidos
  foreach ($disallowed_patterns as $pattern)
    if (fnmatch($pattern, $name))
      err(403, "Arquivos deste tipo não são permitidos.");

  $tmp_dir = dirname($_SERVER['SCRIPT_FILENAME']);
  if (DIRECTORY_SEPARATOR === '\\')
    $tmp_dir = str_replace('/', DIRECTORY_SEPARATOR, $tmp_dir);

  $abs = get_absolute_path($tmp_dir . '/' . $folder . '/' . $name);
  if ($abs === false)
    err(500, "Falha ao construir o caminho.");

  if (substr($abs, 0, strlen($tmp_dir)) !== $tmp_dir)
    err(403, "Proibido");

  if (file_exists($abs))
    err(409, "Arquivo já existe.");

  $parent_dir = dirname($abs);
  if (!is_writable($parent_dir))
    err(403, "Diretório pai não é gravável.");

  $content = isset($_POST['content']) ? $_POST['content'] : '';
  $res = @file_put_contents($abs, $content, LOCK_EX);
  if ($res === false)
    err(500, "Falha ao criar o arquivo.");

  echo json_encode(['success' => true, 'file' => ($folder === '.' ? $name : ($folder . '/' . $name))]);
  exit;
} elseif ($_POST['do'] == 'upload' && $allow_upload) {
  foreach ($disallowed_patterns as $pattern)
    if (fnmatch($pattern, $_FILES['file_data']['name']))
      err(403, "Arquivos deste tipo não são permitidos.");

  // Validar tamanho do arquivo
  $file_size = $_FILES['file_data']['size'];
  $max_size_bytes = $max_upload_size_mb * 1024 * 1024;
  if ($file_size > $max_size_bytes) {
    err(413, "Arquivo excede o tamanho máximo permitido de " . $max_upload_size_mb . "MB. Tamanho atual: " . round($file_size / 1024 / 1024, 2) . "MB");
  }

  $target = $file . '/' . $_FILES['file_data']['name'];
  $overwrite = isset($_POST['overwrite']) && $_POST['overwrite'] == '1';
  if (file_exists($target) && !$overwrite) {
    err(409, "Arquivo já existe.");
  }
  $res = move_uploaded_file($_FILES['file_data']['tmp_name'], $target);
  if ($res === false) {
    err(500, "Falha ao mover o arquivo.");
  }
  echo json_encode(['success' => true]);
  exit;
} elseif ($_GET['do'] == 'download') {
  // Corrigir: garantir que $file está definido e seguro
  $download_file = isset($_GET['file']) ? $_GET['file'] : '';
  $download_file = urldecode($download_file);
  if ($download_file === '' || strpos($download_file, '..') !== false || preg_match('@^.+://@', $download_file)) {
    err(400, 'Arquivo inválido para download.');
  }
  $tmp_dir = dirname($_SERVER['SCRIPT_FILENAME']);
  if (DIRECTORY_SEPARATOR === '\\')
    $tmp_dir = str_replace('/', DIRECTORY_SEPARATOR, $tmp_dir);
  $abs = get_absolute_path($tmp_dir . '/' . $download_file);
  if ($abs === false || !file_exists($abs) || !is_file($abs)) {
    err(404, 'Arquivo não encontrado para download.');
  }
  foreach ($disallowed_patterns as $pattern)
    if (fnmatch($pattern, basename($abs)))
      err(403, "Arquivos deste tipo não são permitidos.");

  $filename = basename($abs);
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  header('Content-Description: File Transfer');
  header('Content-Type: ' . finfo_file($finfo, $abs));
  $disp = 'attachment; filename="' . str_replace('"', '', $filename) . '"';
  if (preg_match('/[^\x20-\x7e]/', $filename)) {
    $disp .= "; filename*=UTF-8''" . rawurlencode($filename);
  }
  header('Content-Disposition: ' . $disp);
  header('Content-Transfer-Encoding: binary');
  header('Expires: 0');
  header('Cache-Control: must-revalidate');
  header('Pragma: public');
  header('Content-Length: ' . filesize($abs));
  flush();
  $fp = fopen($abs, 'rb');
  if ($fp) {
    while (!feof($fp)) {
      print fread($fp, 8192);
      flush();
    }
    fclose($fp);
  } else {
    err(500, 'Falha ao abrir arquivo para download.');
  }
  exit;
}
// ADDED: salvar configurações (somente se permissionAdmin == true)
// receberá campos via POST e gravará files_config.json
elseif ($_POST['do'] == 'saveconfig') {
  if (!$permissionAdmin)
    err(403, "Proibido");
  // construir payload seguro
  $new = [];
  $new['allow_delete'] = !empty($_POST['allow_delete']) ? true : false;
  $new['allow_upload'] = !empty($_POST['allow_upload']) ? true : false;
  $new['allow_create_folder'] = !empty($_POST['allow_create_folder']) ? true : false;
  $new['allow_create_file'] = !empty($_POST['allow_create_file']) ? true : false;
  $new['allow_direct_link'] = !empty($_POST['allow_direct_link']) ? true : false;
  $new['allow_show_folders'] = !empty($_POST['allow_show_folders']) ? true : false;
  $new['configTime'] = isset($_POST['configTime']) ? intval($_POST['configTime']) : $configTime;
  $new['max_upload_size_mb'] = isset($_POST['max_upload_size_mb']) ? max(1, intval($_POST['max_upload_size_mb'])) : $max_upload_size_mb;
  // padrões como CSV -> array
  $dp = isset($_POST['disallowed_patterns']) ? trim($_POST['disallowed_patterns']) : '';
  $hp = isset($_POST['hidden_patterns']) ? trim($_POST['hidden_patterns']) : '';
  $new['disallowed_patterns'] = $dp === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $dp))));
  $new['hidden_patterns'] = $hp === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $hp))));
  // senha (opcional) - grava exatamente o valor recebido
  $new['SENHA'] = isset($_POST['SENHA']) ? $_POST['SENHA'] : $SENHA;
  // preservar ou atualizar permissionAdmin (se fornecido via POST, atualiza; caso contrário preserva o atual)
  if (isset($_POST['permissionAdmin'])) {
    $new['permissionAdmin'] = !empty($_POST['permissionAdmin']) ? true : false;
  } else {
    $new['permissionAdmin'] = $permissionAdmin;
  }

  $config_file = __DIR__ . '/files_config.json';
  $ok = @file_put_contents($config_file, json_encode($new, JSON_PRETTY_PRINT));
  if ($ok === false) {
    err(500, "Falha ao gravar o arquivo de configuração.");
  }
  echo json_encode(['success' => true]);
}

// ADDED: endpoint para gerar link de compartilhamento externo
elseif ($_POST['do'] == 'generatesharelink') {
  $file = isset($_POST['file']) ? $_POST['file'] : '';
  $password = isset($_POST['password']) ? trim($_POST['password']) : '';
  $expires_hours = isset($_POST['expires_hours']) ? intval($_POST['expires_hours']) : 24;

  if (!$file) {
    err(400, "Arquivo não especificado");
  }

  // Verificar se arquivo existe e é acessível
  $tmp_dir = dirname($_SERVER['SCRIPT_FILENAME']);
  if (DIRECTORY_SEPARATOR === '\\')
    $tmp_dir = str_replace('/', DIRECTORY_SEPARATOR, $tmp_dir);

  $abs_path = get_absolute_path($tmp_dir . '/' . $file);
  if ($abs_path === false || !file_exists($abs_path)) {
    err(404, "Arquivo não encontrado");
  }

  if (substr($abs_path, 0, strlen($tmp_dir)) !== $tmp_dir) {
    err(403, "Acesso negado");
  }

  // Criar diretório para links compartilhados se não existir
  $share_dir = __DIR__ . '/.shares';
  if (!file_exists($share_dir)) {
    @mkdir($share_dir, 0777, true);
  }

  // Gerar hash único para o link
  $share_id = bin2hex(openssl_random_pseudo_bytes(16));
  $security_hash = hash('sha256', $share_id . $file . time());

  // Calcular timestamp de expiração
  $expires_at = time() + ($expires_hours * 3600);

  // Dados do compartilhamento
  $share_data = [
    'file_path' => $file,
    'created_at' => time(),
    'expires_at' => $expires_at,
    'password' => $password ? password_hash($password, PASSWORD_DEFAULT) : null,
    'security_hash' => $security_hash,
    'downloads' => 0,
    'max_downloads' => isset($_POST['max_downloads']) ? intval($_POST['max_downloads']) : 0 // 0 = ilimitado
  ];

  // Salvar dados do compartilhamento
  $share_file = $share_dir . '/' . $share_id . '.json';
  $ok = @file_put_contents($share_file, json_encode($share_data, JSON_PRETTY_PRINT));

  if (!$ok) {
    err(500, "Falha ao criar link de compartilhamento");
  }

  // Gerar URL completa
  $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'];
  $script_name = $_SERVER['SCRIPT_NAME'];
  $base_url = $protocol . '://' . $host . dirname($script_name);

  $share_url = $base_url . '/share.php?id=' . $share_id . '&hash=' . $security_hash;

  echo json_encode([
    'success' => true,
    'share_id' => $share_id,
    'share_url' => $share_url,
    'security_hash' => $security_hash,
    'expires_at' => (new DateTime('@' . $expires_at))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y H:i:s'),
    'has_password' => !empty($password)
  ]);
  exit;
}

// ADDED: endpoint para listar links de compartilhamento
elseif ($_GET['do'] == 'listsharelinks') {
  $share_dir = __DIR__ . '/.shares';
  $shares = [];

  if (file_exists($share_dir)) {
    $files = glob($share_dir . '/*.json');
    foreach ($files as $share_file) {
      $data = @json_decode(@file_get_contents($share_file), true);
      if ($data) {
        $share_id = basename($share_file, '.json');
        $shares[] = [
          'id' => $share_id,
          'file_path' => $data['file_path'],
          'created_at' => (new DateTime('@' . $data['created_at']))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y H:i:s'),
          'expires_at' => (new DateTime('@' . $data['expires_at']))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y H:i:s'),
          'expired' => time() > $data['expires_at'],
          'has_password' => !empty($data['password']),
          'downloads' => $data['downloads'] ?? 0,
          'max_downloads' => $data['max_downloads'] ?? 0,
          'security_hash' => $data['security_hash'] ?? ''
        ];
      }
    }
  }

  echo json_encode(['success' => true, 'shares' => $shares]);
  exit;
}

// ADDED: endpoint para deletar link de compartilhamento
elseif ($_POST['do'] == 'deletesharelink') {
  $share_id = isset($_POST['share_id']) ? $_POST['share_id'] : '';

  if (!$share_id || !preg_match('/^[a-f0-9]{32}$/', $share_id)) {
    err(400, "ID de compartilhamento inválido");
  }

  $share_dir = __DIR__ . '/.shares';
  $share_file = $share_dir . '/' . $share_id . '.json';

  if (file_exists($share_file)) {
    @unlink($share_file);
  }

  echo json_encode(['success' => true]);
  exit;
}

// --- ADDED: endpoint para listar conteúdo de arquivo ZIP ---
elseif ($_GET['do'] == 'listzip') {
  $zip_file = isset($_GET['file']) ? $_GET['file'] : '';
  if ($zip_file === '' || strpos($zip_file, '..') !== false) {
    err(400, 'Arquivo ZIP inválido.');
  }

  $tmp_dir = dirname($_SERVER['SCRIPT_FILENAME']);
  if (DIRECTORY_SEPARATOR === '\\')
    $tmp_dir = str_replace('/', DIRECTORY_SEPARATOR, $tmp_dir);

  $zip_abs = get_absolute_path($tmp_dir . '/' . $zip_file);
  if ($zip_abs === false || !file_exists($zip_abs) || !is_file($zip_abs)) {
    err(404, 'Arquivo ZIP não encontrado. Caminho: ' . $zip_abs . ', Existe: ' . (file_exists($zip_abs) ? 'sim' : 'não') . ', É arquivo: ' . (is_file($zip_abs) ? 'sim' : 'não'));
  }

  if (substr($zip_abs, 0, strlen($tmp_dir)) !== $tmp_dir) {
    err(403, "Proibido");
  }

  if (!class_exists('ZipArchive')) {
    err(500, "ZipArchive não está disponível no servidor.");
  }

  $zip = new ZipArchive();
  $result = $zip->open($zip_abs);
  if ($result !== true) {
    $errorMessages = [
      ZipArchive::ER_OK => 'Sem erro',
      ZipArchive::ER_MULTIDISK => 'Arquivo multi-disco não suportado',
      ZipArchive::ER_RENAME => 'Erro de renomeação',
      ZipArchive::ER_CLOSE => 'Erro ao fechar arquivo',
      ZipArchive::ER_SEEK => 'Erro de busca',
      ZipArchive::ER_READ => 'Erro de leitura',
      ZipArchive::ER_WRITE => 'Erro de escrita',
      ZipArchive::ER_CRC => 'Erro de CRC',
      ZipArchive::ER_ZIPCLOSED => 'ZIP fechado',
      ZipArchive::ER_NOENT => 'Arquivo não existe',
      ZipArchive::ER_EXISTS => 'Arquivo já existe',
      ZipArchive::ER_OPEN => 'Não é possível abrir arquivo',
      ZipArchive::ER_TMPOPEN => 'Falha ao criar arquivo temporário',
      ZipArchive::ER_ZLIB => 'Erro Zlib',
      ZipArchive::ER_MEMORY => 'Erro de memória',
      ZipArchive::ER_CHANGED => 'Entrada alterada',
      ZipArchive::ER_COMPNOTSUPP => 'Método de compressão não suportado',
      ZipArchive::ER_EOF => 'Final do arquivo prematuro',
      ZipArchive::ER_INVAL => 'Argumento inválido',
      ZipArchive::ER_NOZIP => 'Não é um arquivo ZIP',
      ZipArchive::ER_INTERNAL => 'Erro interno',
      ZipArchive::ER_INCONS => 'ZIP inconsistente',
      ZipArchive::ER_REMOVE => 'Não é possível remover arquivo',
      ZipArchive::ER_DELETED => 'Entrada deletada'
    ];
    $errorMsg = isset($errorMessages[$result]) ? $errorMessages[$result] : 'Erro desconhecido código: ' . $result;
    err(500, "Falha ao abrir arquivo ZIP: " . $errorMsg);
  }

  $files = [];
  for ($i = 0; $i < $zip->numFiles; $i++) {
    $entry = $zip->getNameIndex($i);
    $stat = $zip->statIndex($i);

    $files[] = [
      'name' => $entry,
      'size' => $stat['size'],
      'compressed_size' => $stat['comp_size'],
      'modified' => (new DateTime('@' . $stat['mtime']))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y h:i A'),
      'is_dir' => substr($entry, -1) === '/',
      'method' => $stat['comp_method']
    ];
  }

  $zip->close();

  header('Content-Type: application/json');
  echo json_encode([
    'success' => true,
    'zip_file' => $zip_file,
    'files' => $files,
    'total_files' => count($files)
  ]);
  exit;
}

// --- ADDED: endpoint para extrair arquivo ZIP ---
elseif ($_POST['do'] == 'extractzip') {
  $zip_file = isset($_POST['file']) ? $_POST['file'] : '';
  $extract_to = isset($_POST['extract_to']) ? trim($_POST['extract_to']) : '';

  if ($zip_file === '' || strpos($zip_file, '..') !== false) {
    err(400, 'Arquivo ZIP inválido.');
  }

  if ($extract_to === '') {
    err(400, 'Nome da pasta de destino não especificado.');
  }

  // Sanitizar nome da pasta
  $extract_to = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $extract_to);
  if (strpos($extract_to, '/') !== false || strpos($extract_to, '\\') !== false || strpos($extract_to, '..') !== false) {
    err(400, 'Nome de pasta inválido.');
  }

  $tmp_dir = dirname($_SERVER['SCRIPT_FILENAME']);
  if (DIRECTORY_SEPARATOR === '\\')
    $tmp_dir = str_replace('/', DIRECTORY_SEPARATOR, $tmp_dir);

  $zip_abs = get_absolute_path($tmp_dir . '/' . $zip_file);
  if ($zip_abs === false || !file_exists($zip_abs) || !is_file($zip_abs)) {
    err(404, 'Arquivo ZIP não encontrado.');
  }

  if (substr($zip_abs, 0, strlen($tmp_dir)) !== $tmp_dir) {
    err(403, "Proibido");
  }

  // Diretório onde o ZIP está localizado
  $zip_dir = dirname($zip_abs);
  $extract_path = $zip_dir . DIRECTORY_SEPARATOR . $extract_to;

  if (file_exists($extract_path)) {
    err(409, 'Pasta de destino já existe.');
  }

  if (!is_writable($zip_dir)) {
    err(403, 'Diretório não é gravável.');
  }

  if (!class_exists('ZipArchive')) {
    err(500, "ZipArchive não está disponível no servidor.");
  }

  $zip = new ZipArchive();
  $result = $zip->open($zip_abs);
  if ($result !== true) {
    $errorMessages = [
      ZipArchive::ER_OK => 'Sem erro',
      ZipArchive::ER_MULTIDISK => 'Arquivo multi-disco não suportado',
      ZipArchive::ER_RENAME => 'Erro de renomeação',
      ZipArchive::ER_CLOSE => 'Erro ao fechar arquivo',
      ZipArchive::ER_SEEK => 'Erro de busca',
      ZipArchive::ER_READ => 'Erro de leitura',
      ZipArchive::ER_WRITE => 'Erro de escrita',
      ZipArchive::ER_CRC => 'Erro de CRC',
      ZipArchive::ER_ZIPCLOSED => 'ZIP fechado',
      ZipArchive::ER_NOENT => 'Arquivo não existe',
      ZipArchive::ER_EXISTS => 'Arquivo já existe',
      ZipArchive::ER_OPEN => 'Não é possível abrir arquivo',
      ZipArchive::ER_TMPOPEN => 'Falha ao criar arquivo temporário',
      ZipArchive::ER_ZLIB => 'Erro Zlib',
      ZipArchive::ER_MEMORY => 'Erro de memória',
      ZipArchive::ER_CHANGED => 'Entrada alterada',
      ZipArchive::ER_COMPNOTSUPP => 'Método de compressão não suportado',
      ZipArchive::ER_EOF => 'Final do arquivo prematuro',
      ZipArchive::ER_INVAL => 'Argumento inválido',
      ZipArchive::ER_NOZIP => 'Não é um arquivo ZIP',
      ZipArchive::ER_INTERNAL => 'Erro interno',
      ZipArchive::ER_INCONS => 'ZIP inconsistente',
      ZipArchive::ER_REMOVE => 'Não é possível remover arquivo',
      ZipArchive::ER_DELETED => 'Entrada deletada'
    ];
    $errorMsg = isset($errorMessages[$result]) ? $errorMessages[$result] : 'Erro desconhecido código: ' . $result;
    err(500, "Falha ao abrir arquivo ZIP para extração: " . $errorMsg);
  }

  // Criar diretório de destino
  if (!@mkdir($extract_path, 0777, true)) {
    $zip->close();
    err(500, 'Falha ao criar diretório de destino.');
  }

  // Extrair arquivos
  $extracted = 0;
  for ($i = 0; $i < $zip->numFiles; $i++) {
    $entry_name = $zip->getNameIndex($i);

    // Validar se o caminho é seguro
    if (strpos($entry_name, '..') !== false) {
      continue; // Pular entradas inseguras
    }

    $target_path = $extract_path . DIRECTORY_SEPARATOR . $entry_name;

    // Garantir que o diretório pai existe
    $parent_dir = dirname($target_path);
    if (!is_dir($parent_dir)) {
      @mkdir($parent_dir, 0777, true);
    }

    // Extrair arquivo individual
    if ($zip->extractTo($extract_path, $entry_name)) {
      $extracted++;
    }
  }

  $zip->close();

  header('Content-Type: application/json');
  echo json_encode([
    'success' => true,
    'extracted' => $extracted,
    'extract_path' => dirname($zip_file) . '/' . $extract_to
  ]);
  exit;
}

// --- ADDED: endpoint para ler arquivo (para edição) ---
elseif ($_GET['do'] == 'getfile') {
  foreach ($disallowed_patterns as $pattern)
    if (fnmatch($pattern, $file))
      err(403, "Arquivos deste tipo não são permitidos.");

  if (!is_file($file) || !is_readable($file))
    err(404, "Arquivo não encontrado ou não pode ser lido");

  $max_read = 5 * 1024 * 1024; // 5MB limite de leitura via editor
  if (filesize($file) > $max_read)
    err(413, "Arquivo muito grande para editar pelo editor web.");

  $content = file_get_contents($file);
  header("Content-Type: application/json; charset=utf-8");
  echo json_encode(['success' => true, 'content' => $content, 'path' => $file]);
  exit;
}

// --- ADDED: endpoint para salvar conteúdo editado ---
elseif ($_POST['do'] == 'savefile') {
  foreach ($disallowed_patterns as $pattern)
    if (fnmatch($pattern, $file = $_POST['file']))
      err(403, "Arquivos deste tipo não são permitidos.");

  if (!is_file($file))
    err(404, "Arquivo não encontrado.");

  if (!is_writable($file))
    err(403, "Arquivo não é gravável.");

  $content = isset($_POST['content']) ? $_POST['content'] : '';
  $res = @file_put_contents($file, $content, LOCK_EX);
  if ($res === false)
    err(500, "Falha ao salvar o arquivo.");
  echo json_encode(['success' => true]);
  exit;
}
// --- ADDED: endpoint para criar um ZIP com arquivos selecionados (AGORA suporta diretórios recursivamente) ---
elseif ($_POST['do'] == 'zip') {
  if (!isset($_POST['files']) || !is_array($_POST['files']))
    err(400, "Nenhum arquivo especificado.");

  // Nome do zip opcional
  $zip_name = isset($_POST['name']) ? trim($_POST['name']) : '';
  // pasta atual (hash)
  $folder = isset($_POST['folder']) && $_POST['folder'] !== '' ? $_POST['folder'] : '.';
  if (strpos($folder, '..') !== false)
    err(403, "Pasta proibida.");

  // sanitize requested zip name
  if ($zip_name === '') {
    $zip_name = 'selected_' . time() . '.zip';
  } else {
    // garantir extensão .zip e caracter seguro (permitir espaços e acentos)
    $zip_name = basename(preg_replace('/[<>:"|?*]/', '_', $zip_name));
    if (stripos($zip_name, '.zip') === false)
      $zip_name .= '.zip';
  }

  if (!class_exists('ZipArchive'))
    err(500, "ZipArchive não está disponível no servidor.");

  // garantir que a pasta destino exista
  $dest_rel = ($folder === '.' || $folder === '') ? $zip_name : ($folder . '/' . $zip_name);
  $dest_abs = $tmp_dir . '/' . $dest_rel;
  $dest_dir = dirname($dest_abs);
  if (!is_dir($dest_dir)) {
    @mkdir($dest_dir, 0777, true);
  }

  // Progresso: arquivo temporário
  $progress_file = $tmp_dir . '/zip_progress.json';
  @file_put_contents($progress_file, json_encode([
    'status' => 'iniciando',
    'total' => count($_POST['files']),
    'current' => 0,
    'added' => 0,
    'zip' => $dest_rel
  ]));

  $zip = new ZipArchive();
  if ($zip->open($dest_abs, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true)
    err(500, "Falha ao criar o arquivo zip.");

  // ADDED: Configurar nível de compressão
  $compression_level = isset($_POST['compression_level']) ? intval($_POST['compression_level']) : 6;
  $preserve_paths = isset($_POST['preserve_paths']) && $_POST['preserve_paths'] === '1';

  // Validar nível de compressão (0-9)
  if ($compression_level < 0 || $compression_level > 9) {
    $compression_level = 6; // Padrão
  }

  $added = 0;
  $current = 0;
  foreach ($_POST['files'] as $f) {
    $current++;
    // basic checks
    if (!$f)
      continue;
    if (strpos($f, '..') !== false)
      continue;
    if (preg_match('@^.+://@', $f))
      continue;

    // proibir padrões indesejados (por base filename)
    foreach ($disallowed_patterns as $pattern) {
      if (fnmatch($pattern, basename($f))) {
        // não adiciona arquivos proibidos
        continue 2;
      }
    }

    $abs = get_absolute_path($tmp_dir . '/' . $f);
    if ($abs === false)
      continue;
    if (substr($abs, 0, strlen($tmp_dir)) !== $tmp_dir)
      continue;

    // se for diretório, adicionar recursivamente mantendo estrutura (usa basename($f) como raiz no ZIP)
    if (is_dir($abs)) {
      addPathToZip($zip, $abs, basename($f), $disallowed_patterns, $added, $compression_level, $preserve_paths);
    } elseif (is_file($abs) && is_readable($abs)) {
      // arquivo simples
      $zip->addFile($abs, basename($abs));
      // Definir nível de compressão para o arquivo adicionado
      $zip->setCompressionName(basename($abs), ZipArchive::CM_DEFLATE, $compression_level);
      $added++;
    }
    // Atualiza progresso
    @file_put_contents($progress_file, json_encode([
      'status' => 'processando',
      'total' => count($_POST['files']),
      'current' => $current,
      'added' => $added,
      'zip' => $dest_rel
    ]));
  }

  $zip->close();

  if ($added === 0) {
    // remover zip vazio
    @unlink($dest_abs);
    @file_put_contents($progress_file, json_encode([
      'status' => 'erro',
      'msg' => 'No valid files to add to zip.'
    ]));
    err(400, "No valid files to add to zip.");
  }

  @file_put_contents($progress_file, json_encode([
    'status' => 'finalizado',
    'total' => count($_POST['files']),
    'current' => $current,
    'added' => $added,
    'zip' => $dest_rel
  ]));

  echo json_encode(['success' => true, 'zip' => $dest_rel, 'added' => $added]);
  exit;
}

// Endpoint para consultar progresso do ZIP
elseif (isset($_GET['do']) && $_GET['do'] === 'zipprogress') {
  $progress_file = $tmp_dir . '/zip_progress.json';
  if (file_exists($progress_file)) {
    $progress = @json_decode(@file_get_contents($progress_file), true);
    echo json_encode(['success' => true, 'progress' => $progress]);
  } else {
    echo json_encode(['success' => false, 'progress' => null]);
  }
  exit;
} elseif ($_POST['do'] == 'rename') {
  $old = isset($_POST['file']) ? trim($_POST['file']) : '';
  $newname = isset($_POST['newname']) ? trim($_POST['newname']) : '';
  if ($old === '' || $newname === '')
    err(400, "Parâmetros ausentes.");

  // proibir caminhos relativos na nova string e barras
  if (strpos($newname, '/') !== false || strpos($newname, '\\') !== false)
    err(400, "Nome novo inválido.");

  if (strpos($old, '..') !== false || preg_match('@^.+://@', $old))
    err(403, "Proibido");

  // basic disallowed extensions check for target name
  foreach ($disallowed_patterns as $pattern)
    if (fnmatch($pattern, $newname))
      err(403, "Nome de destino proibido pelo padrão.");

  $tmp_dir = dirname($_SERVER['SCRIPT_FILENAME']);
  if (DIRECTORY_SEPARATOR === '\\')
    $tmp_dir = str_replace('/', DIRECTORY_SEPARATOR, $tmp_dir);

  $old_abs = get_absolute_path($tmp_dir . '/' . $old);
  if ($old_abs === false)
    err(404, 'Arquivo ou diretório de origem não encontrado.');

  if (substr($old_abs, 0, strlen($tmp_dir)) !== $tmp_dir)
    err(403, "Proibido");

  // Ensure source exists
  if (!file_exists($old_abs))
    err(404, "Arquivo ou diretório de origem não encontrado.");

  // Ensure parent dir writable
  $parent_dir = dirname($old_abs);
  if (!is_writable($parent_dir))
    err(403, "Diretório pai não é gravável.");

  // Build new absolute path (keep same parent)
  $new_rel = ($old === '.' ? $newname : (rtrim(dirname($old), '/\\') . '/' . $newname));
  $new_abs = get_absolute_path($tmp_dir . '/' . $new_rel);
  if ($new_abs === false)
    err(500, "Falha ao construir o novo caminho.");

  if (substr($new_abs, 0, strlen($tmp_dir)) !== $tmp_dir)
    err(403, "Proibido");

  // Prevent overwrite
  if (file_exists($new_abs))
    err(409, "Arquivo ou diretório de destino já existe.");

  // perform rename
  if (!@rename($old_abs, $new_abs))
    err(500, "Falha ao renomear.");

  echo json_encode(['success' => true, 'old' => $old, 'new' => $new_rel]);
  exit;
}
// --- ADDED: handler for move/copy bulk operation ---
elseif ($_POST['do'] == 'movecopy') {
  if (!isset($_POST['files']) || !is_array($_POST['files']))
    err(400, "Nenhum arquivo especificado.");
  $files = $_POST['files'];
  $dest = isset($_POST['dest']) ? trim($_POST['dest']) : '';
  $action = isset($_POST['action']) ? trim($_POST['action']) : 'move';
  if ($dest === '' || strpos($dest, '..') !== false)
    err(400, "Destino inválido.");
  $tmp_dir = dirname($_SERVER['SCRIPT_FILENAME']);
  if (DIRECTORY_SEPARATOR === '\\')
    $tmp_dir = str_replace('/', DIRECTORY_SEPARATOR, $tmp_dir);
  $dest_abs = get_absolute_path($tmp_dir . '/' . $dest);
  if ($dest_abs === false || !is_dir($dest_abs) || !is_writable($dest_abs))
    err(403, "Destino não é gravável ou não é um diretório.");
  $results = [];
  foreach ($files as $f) {
    $res = ['success' => false];
    if (!$f || strpos($f, '..') !== false || preg_match('@^.+://@', $f)) {
      $res['msg'] = 'Arquivo inválido';
      $results[$f] = $res;
      continue;
    }
    $src_abs = get_absolute_path($tmp_dir . '/' . $f);
    if ($src_abs === false || !file_exists($src_abs)) {
      $res['msg'] = 'Origem não encontrada';
      $results[$f] = $res;
      continue;
    }
    $basename = basename($src_abs);
    $target_abs = $dest_abs . DIRECTORY_SEPARATOR . $basename;
    if (file_exists($target_abs)) {
      $res['msg'] = 'Destino já existe';
      $results[$f] = $res;
      continue;
    }
    if ($action === 'move') {
      if (@rename($src_abs, $target_abs)) {
        $res['success'] = true;
      } else {
        $res['msg'] = 'Falha ao mover';
      }
    } elseif ($action === 'copy') {
      if (is_dir($src_abs)) {
        if (copy_dir_recursive($src_abs, $target_abs)) {
          $res['success'] = true;
        } else {
          $res['msg'] = 'Falha ao copiar';
        }
      } else {
        if (@copy($src_abs, $target_abs)) {
          $res['success'] = true;
        } else {
          $res['msg'] = 'Falha ao copiar';
        }
      }
    } else {
      $res['msg'] = 'Ação desconhecida';
    }
    $results[$f] = $res;
  }
  echo json_encode(['success' => true, 'results' => $results]);
  exit;
}

// ADDED: endpoint para conversão e compressão de imagens
elseif ($_POST['do'] == 'convertimages') {
  if (!isset($_POST['files']) || !is_array($_POST['files']))
    err(400, "Nenhuma imagem especificada.");

  $files = $_POST['files'];
  $format = isset($_POST['format']) ? $_POST['format'] : 'webp';
  $quality = isset($_POST['quality']) ? intval($_POST['quality']) : 85;
  $maxWidth = isset($_POST['max_width']) ? intval($_POST['max_width']) : 1920;
  $maxHeight = isset($_POST['max_height']) ? intval($_POST['max_height']) : 1080;
  $preserveOriginal = isset($_POST['preserve_original']) && $_POST['preserve_original'] === '1';
  $addSuffix = isset($_POST['add_suffix']) && $_POST['add_suffix'] === '1';

  // Validar parâmetros
  if (!in_array($format, ['webp', 'jpeg', 'png', 'avif'])) {
    err(400, "Formato de saída inválido.");
  }
  if ($quality < 10 || $quality > 100) {
    err(400, "Qualidade deve estar entre 10 e 100.");
  }

  // Verificar se o GD está disponível
  if (!extension_loaded('gd')) {
    err(500, "Extensão GD não está disponível no servidor.");
  }

  $tmp_dir = dirname($_SERVER['SCRIPT_FILENAME']);
  if (DIRECTORY_SEPARATOR === '\\')
    $tmp_dir = str_replace('/', DIRECTORY_SEPARATOR, $tmp_dir);

  $results = [];
  $converted = 0;

  foreach ($files as $file) {
    $result = ['success' => false, 'file' => $file];

    // Validar arquivo
    if (!$file || strpos($file, '..') !== false || preg_match('@^.+://@', $file)) {
      $result['error'] = 'Arquivo inválido';
      $results[] = $result;
      continue;
    }

    $src_abs = get_absolute_path($tmp_dir . '/' . $file);
    if ($src_abs === false || !file_exists($src_abs) || !is_file($src_abs)) {
      $result['error'] = 'Arquivo não encontrado';
      $results[] = $result;
      continue;
    }

    // Verificar se é uma imagem
    $imageInfo = @getimagesize($src_abs);
    if ($imageInfo === false) {
      $result['error'] = 'Não é uma imagem válida';
      $results[] = $result;
      continue;
    }

    // Carregar imagem
    $sourceImage = null;
    switch ($imageInfo[2]) {
      case IMAGETYPE_JPEG:
        $sourceImage = @imagecreatefromjpeg($src_abs);
        break;
      case IMAGETYPE_PNG:
        $sourceImage = @imagecreatefrompng($src_abs);
        break;
      case IMAGETYPE_GIF:
        $sourceImage = @imagecreatefromgif($src_abs);
        break;
      case IMAGETYPE_WEBP:
        if (function_exists('imagecreatefromwebp')) {
          $sourceImage = @imagecreatefromwebp($src_abs);
        }
        break;
    }

    if (!$sourceImage) {
      $result['error'] = 'Falha ao carregar imagem';
      $results[] = $result;
      continue;
    }

    $originalWidth = imagesx($sourceImage);
    $originalHeight = imagesy($sourceImage);

    // Calcular novas dimensões mantendo proporção
    $newWidth = $originalWidth;
    $newHeight = $originalHeight;

    if ($originalWidth > $maxWidth || $originalHeight > $maxHeight) {
      $ratioWidth = $maxWidth / $originalWidth;
      $ratioHeight = $maxHeight / $originalHeight;
      $ratio = min($ratioWidth, $ratioHeight);

      $newWidth = intval($originalWidth * $ratio);
      $newHeight = intval($originalHeight * $ratio);
    }

    // Criar nova imagem
    $newImage = imagecreatetruecolor($newWidth, $newHeight);

    // Preservar transparência para PNG e WebP
    if ($format === 'png' || $format === 'webp') {
      imagealphablending($newImage, false);
      imagesavealpha($newImage, true);
    }

    // Redimensionar
    imagecopyresampled(
      $newImage,
      $sourceImage,
      0,
      0,
      0,
      0,
      $newWidth,
      $newHeight,
      $originalWidth,
      $originalHeight
    );

    // Determinar nome do arquivo de saída
    $pathInfo = pathinfo($src_abs);
    $baseName = $pathInfo['filename'];
    $outputDir = $pathInfo['dirname'];

    if ($addSuffix) {
      $baseName .= '_converted';
    }

    $outputFile = $outputDir . DIRECTORY_SEPARATOR . $baseName . '.' . $format;

    // Se não preservar original e não adicionar sufixo, backup temporário
    $backupFile = null;
    if (!$preserveOriginal && !$addSuffix) {
      $backupFile = $src_abs . '.backup_' . time();
      @rename($src_abs, $backupFile);
    }

    // Salvar imagem convertida
    $success = false;
    switch ($format) {
      case 'jpeg':
        $success = imagejpeg($newImage, $outputFile, $quality);
        break;
      case 'png':
        $pngQuality = intval((100 - $quality) / 10); // PNG usa 0-9, inversamente
        $success = imagepng($newImage, $outputFile, $pngQuality);
        break;
      case 'webp':
        if (function_exists('imagewebp')) {
          $success = imagewebp($newImage, $outputFile, $quality);
        }
        break;
      case 'avif':
        if (function_exists('imageavif')) {
          $success = imageavif($newImage, $outputFile, $quality);
        }
        break;
    }

    // Limpeza
    imagedestroy($sourceImage);
    imagedestroy($newImage);

    if ($success) {
      $result['success'] = true;
      $result['original_size'] = filesize($backupFile ?: $src_abs);
      $result['new_size'] = filesize($outputFile);
      $result['output_file'] = str_replace($tmp_dir . DIRECTORY_SEPARATOR, '', $outputFile);
      $result['compression_ratio'] = round((1 - $result['new_size'] / $result['original_size']) * 100, 1);
      $converted++;

      // Remover backup se conversion foi bem-sucedida e não preservar original
      if ($backupFile && !$preserveOriginal) {
        @unlink($backupFile);
      } elseif ($backupFile) {
        @rename($backupFile, $src_abs); // Restaurar original
      }
    } else {
      $result['error'] = 'Falha ao salvar imagem convertida';
      // Restaurar backup se houver
      if ($backupFile) {
        @rename($backupFile, $src_abs);
      }
    }

    $results[] = $result;
  }

  echo json_encode([
    'success' => true,
    'results' => $results,
    'converted' => $converted,
    'total' => count($files)
  ]);
  exit;
}
// --- helper for recursive copy ---
function copy_dir_recursive($src, $dst)
{
  $dir = opendir($src);
  @mkdir($dst);
  while (false !== ($file = readdir($dir))) {
    if (($file != '.') && ($file != '..')) {
      if (is_dir($src . DIRECTORY_SEPARATOR . $file)) {
        copy_dir_recursive($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file);
      } else {
        copy($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file);
      }
    }
  }
  closedir($dir);
  return true;
}

// ADDED: função auxiliar recursiva para adicionar diretórios/arquivos ao zip
function addPathToZip($zip, $absPath, $localPath, $disallowed_patterns, &$added, $compression_level = 6, $preserve_paths = true)
{
  // $absPath = caminho absoluto no servidor
  // $localPath = caminho relativo a usar dentro do zip (string)
  if (!is_readable($absPath))
    return;

  if (is_dir($absPath)) {
    // adiciona diretório vazio no zip (se necessário)
    if ($preserve_paths) {
      $zip->addEmptyDir($localPath);
    }

    $files = array_diff(scandir($absPath), ['.', '..']);
    foreach ($files as $entry) {
      // filtrar padrões proibidos pelo nome base
      foreach ($disallowed_patterns as $pattern) {
        if (fnmatch($pattern, $entry)) {
          continue 2;
        }
      }

      $childAbs = $absPath . '/' . $entry;
      $childLocal = $preserve_paths ? ($localPath === '' ? $entry : ($localPath . '/' . $entry)) : $entry;

      if (is_dir($childAbs)) {
        addPathToZip($zip, $childAbs, $childLocal, $disallowed_patterns, $added, $compression_level, $preserve_paths);
      } elseif (is_file($childAbs) && is_readable($childAbs)) {
        // adiciona arquivo preservando ou não o caminho relativo dentro do zip
        $zip->addFile($childAbs, $childLocal);
        // Definir nível de compressão para o arquivo adicionado
        $zip->setCompressionName($childLocal, ZipArchive::CM_DEFLATE, $compression_level);
        $added++;
      }
    }
  } elseif (is_file($absPath) && is_readable($absPath)) {
    $zip->addFile($absPath, $localPath);
    // Definir nível de compressão para o arquivo adicionado
    $zip->setCompressionName($localPath, ZipArchive::CM_DEFLATE, $compression_level);
    $added++;
  }
}

function is_entry_ignored($entry, $allow_show_folders, $hidden_patterns)
{
  if ($entry === basename(__FILE__)) {
    return true;
  }

  // Sempre ocultar arquivos de configuração
  if ($entry === 'files_config.json' || $entry === 'zip_progress.json' || $entry === 'share.php') {
    return true;
  }

  if (is_dir($entry) && !$allow_show_folders) {
    return true;
  }
  foreach ($hidden_patterns as $pattern) {
    if (fnmatch($pattern, $entry)) {
      return true;
    }
  }
  return false;
}

function rmrf($dir)
{
  if (is_dir($dir)) {
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file)
      rmrf("$dir/$file");
    rmdir($dir);
  } else {
    unlink($dir);
  }
}
function is_recursively_deleteable($d)
{
  $stack = [$d];
  while ($dir = array_pop($stack)) {
    if (!is_readable($dir) || !is_writable($dir))
      return false;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file)
      if (is_dir($file)) {
        $stack[] = "$dir/$file";
      }
  }
  return true;
}

function get_absolute_path($path)
{
  $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
  $parts = explode(DIRECTORY_SEPARATOR, $path);
  $absolutes = [];
  foreach ($parts as $part) {
    if ('.' == $part)
      continue;
    if ('..' == $part) {
      array_pop($absolutes);
    } else {
      $absolutes[] = $part;
    }
  }
  return implode(DIRECTORY_SEPARATOR, $absolutes);
}

function err($code, $msg)
{
  http_response_code($code);
  header("Content-Type: application/json");
  echo json_encode(['error' => ['code' => intval($code), 'msg' => $msg]]);
  exit;
}

function asBytes($ini_v)
{
  $ini_v = trim($ini_v);
  $s = ['g' => 1 << 30, 'm' => 1 << 20, 'k' => 1 << 10];
  return intval($ini_v) * ($s[strtolower(substr($ini_v, -1))] ?: 1);
}

// Usar o maior valor entre configuração PHP e nossa configuração personalizada
$php_post_max = asBytes(ini_get('post_max_size'));
$php_upload_max = asBytes(ini_get('upload_max_filesize'));
$config_max = $max_upload_size_mb * 1024 * 1024;

// Pegar o maior entre PHP e nossa configuração, mas respeitando o menor limite do PHP entre post_max_size e upload_max_filesize
$php_limit = min($php_post_max, $php_upload_max);
$MAX_UPLOAD_SIZE = max($php_limit, $config_max);

?>
<!DOCTYPE html>
<html>

<head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link data-rh="true" rel="icon" href="../files_assets/icons/files-logo.svg">
  <!-- DEFINA O TITULO DO SITE -->
  <title>[Logado] Gerenciador de Arquivos</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <link href="../files_assets/css/style.css" rel="stylesheet">
  <link href="../files_assets/css/config.css" rel="stylesheet">
  <script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js"></script>
  <script src='../files_assets/js/script.js' crossorigin='anonymous'></script>

  <!-- CodeMirror CSS e JS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/monokai.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/dracula.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/material.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/solarized.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.css">
  <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/matchesonscrollbar.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/show-hint.min.css">

  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>

  <!-- Modos de linguagem do CodeMirror -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/php/php.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/python/python.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/sql/sql.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/markdown/markdown.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/yaml/yaml.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/shell/shell.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/dockerfile/dockerfile.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/clike/clike.min.js"></script>

  <!-- Addons do CodeMirror -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/closebrackets.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/matchbrackets.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/selection/active-line.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/search.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/searchcursor.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/jump-to-line.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/foldcode.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/foldgutter.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/brace-fold.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/xml-fold.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/comment-fold.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/closetag.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/matchtags.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/show-hint.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/xml-hint.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/html-hint.min.js"></script>
  <style>
    /* Estilo para grid de cards de arquivos/pastas */
    .file-grid-view {
      display: flex;
      flex-direction: row;
      flex-wrap: wrap;
      justify-content: flex-start;
      align-items: stretch;
      gap: 1.5rem;
      margin-top: 1rem;
      padding: 20px;
      background: #1a1a1a;
      border-radius: 8px;
      min-height: 400px;
    }

    .file-card {
      background: linear-gradient(135deg, #2d2d2d 0%, #3a3a3a 100%);
      border: 2px solid #1f8657;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
      padding: 1.2rem;
      width: 280px;
      height: 230px;
      min-height: auto;
      flex: 0 0 auto;
      display: flex;
      flex-direction: column;
      word-break: break-word;
      align-items: flex-start;
      transition: all 0.3s ease;
      color: #ffffff;
      position: relative;
      overflow: hidden;
    }

    .file-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 3px;
      background: linear-gradient(90deg, #1f8657, #4CAF50);
    }

    .file-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(31, 134, 87, 0.4);
      border-color: #4CAF50;
    }

    .file-card .file-card-title {
      font-weight: 600;
      margin-bottom: 1rem;
      font-size: 1.1em;
      display: flex;
      align-items: center;
      gap: 0.8em;
      color: #21cb7f;
      width: 100%;
      min-height: 2.5em;
    }

    .file-card .file-card-title a {
      color: #21cb7f;
      text-decoration: none;
      font-weight: 600;
      transition: color 0.3s ease;
      display: block;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      max-width: 200px;
    }

    .file-card .file-card-title a:hover {
      color: #4CAF50;
      white-space: normal;
    }

    .file-card .file-card-title .form-check {
      margin-right: 10px;
      flex-shrink: 0;
    }

    .file-card .file-card-actions {
      margin-top: auto;
      display: flex;
      gap: 0.4em;
      flex-wrap: nowrap;
      justify-content: center;
      width: 100%;
      padding-top: 1rem;
      border-top: 1px solid #404040;
      flex-direction: row;
    }

    .file-card .file-card-actions .btn {
      font-size: 0.75em;
      padding: 4px 8px;
      border-radius: 4px;
      min-width: 32px;
      height: 28px;
    }

    .file-card .file-card-meta {
      font-size: 0.85em;
      color: #b0b0b0;
      margin-bottom: 1rem;
      flex-grow: 1;
      width: 100%;
    }

    .file-card .file-card-meta .badge {
      margin: 2px;
      font-size: 0.7em;
      padding: 4px 8px;
      border-radius: 6px;
    }

    .file-card .file-card-footer {
      margin-top: auto;
      width: 100%;
    }

    .file-card.is-dir {
      border-color: #ffc107;
      background: linear-gradient(135deg, #2d2d2d 0%, #3a3a2d 100%);
    }

    .file-card.is-dir::before {
      background: linear-gradient(90deg, #ffc107, #ffdb4d);
    }

    .file-card.is-dir .file-card-title {
      color: #ffc107;
    }

    .file-card.is-dir .file-card-title a {
      color: #ffc107;
    }

    .file-card.is-dir:hover {
      border-color: #ffdb4d;
      box-shadow: 0 8px 25px rgba(255, 193, 7, 0.3);
    }

    @media (max-width: 600px) {
      .file-card {
        width: 100%;
        max-width: 100%;
        padding: 1rem;
      }

      .file-grid-view {
        gap: 1rem;
        padding: 15px;
      }
    }

    /* Estilos para o modal da lixeira */
    .modal-xlg {
      max-width: 90%;
    }

    .table-responsive {
      max-height: 60vh;
      overflow-y: auto;
    }

    .indicator {
      font-size: 0.8em;
      margin-left: 5px;
    }

    .fontSmall {
      font-size: 1em;
    }

    .btn-purple {
      background-color: #6f42c1 !important;
      color: white !important;
      border: transparent !important;
    }

    /* Melhorias de responsividade para tabelas */
    @media (max-width: 768px) {

      table,
      thead,
      tbody,
      th,
      td,
      tr {
        font-size: 0.95em;
      }

      .table th,
      .table td {
        padding: 0.4rem 0.3rem;
      }

      .table th,
      .table td {
        white-space: pre-line;
        word-break: break-word;
      }

      .table th:not(:first-child),
      .table td:not(:first-child) {
        min-width: 80px;
      }
    }

    @media (max-width: 480px) {

      table,
      thead,
      tbody,
      th,
      td,
      tr {
        font-size: 0.88em;
      }

      .table th,
      .table td {
        padding: 0.3rem 0.2rem;
      }

      .table th,
      .table td {
        white-space: pre-line;
        word-break: break-word;
      }

      .table th:not(:first-child),
      .table td:not(:first-child) {
        min-width: 60px;
      }

      .empty {
        color: #777;
        font-style: italic;
        text-align: center;
        padding: 3em 0;
      }
    }

    /* Estilos para o modal de ZIP */
    .modal-xl {
      max-width: 95%;
    }

    #zipFilesList tr:hover {
      background-color: rgba(0, 123, 255, 0.1);
    }

    .sticky-top {
      position: sticky;
      top: 0;
      z-index: 10;
    }

    .table-responsive {
      border: none;
      border-radius: 0;
    }

    /* Melhorias para inputs de extração */
    #extractFolderName {
      border: 2px solid #28a745;
    }

    #extractZipBtn {
      min-width: 140px;
    }

    /* Responsividade para modal ZIP */
    @media (max-width: 768px) {
      .modal-xl {
        max-width: 98%;
      }

      #zipFilesList th:nth-child(3),
      #zipFilesList th:nth-child(4),
      #zipFilesList td:nth-child(3),
      #zipFilesList td:nth-child(4) {
        display: none;
      }
    }

    /* Estilos para o CodeMirror estilo VS Code */
    .CodeMirror {
      height: 100% !important;
      font-family: 'Consolas', 'Monaco', 'Courier New', 'Fira Code', monospace !important;
      border: none !important;
      background: #1e1e1e;
      color: #d4d4d4;
    }

    .CodeMirror-scroll {
      min-height: calc(100vh - 200px);
    }

    .CodeMirror.cm-s-default {
      background: #1e1e1e;
      color: #d4d4d4;
    }

    .CodeMirror.cm-s-monokai {
      background: #1e1e1e !important;
      color: #d4d4d4 !important;
    }

    .CodeMirror-gutters {
      background: #252526 !important;
      border-right: 1px solid #3e3e42 !important;
    }

    .CodeMirror-linenumber {
      color: #858585 !important;
      padding: 0 8px !important;
      font-size: 12px !important;
    }

    .CodeMirror-cursor {
      border-left: 2px solid #aeafad !important;
    }

    .CodeMirror-selected {
      background: #264f78 !important;
    }

    .CodeMirror-activeline-background {
      background: rgba(255, 255, 255, 0.04) !important;
    }

    .CodeMirror-matchingbracket {
      background: rgba(255, 255, 255, 0.15) !important;
      color: inherit !important;
    }

    /* Estilos para matching tags HTML */
    .CodeMirror-matchingtag {
      background: rgba(255, 255, 0, 0.3) !important;
    }

    /* Melhor suporte para syntax highlighting HTML */
    .cm-s-monokai .cm-tag {
      color: #f92672 !important;
    }

    .cm-s-monokai .cm-attribute {
      color: #a6e22e !important;
    }

    .cm-s-monokai .cm-string {
      color: #e6db74 !important;
    }

    .cm-s-monokai .cm-bracket {
      color: #f8f8f2 !important;
    }

    /* Estilos para o folding (dobramento de código) */
    .CodeMirror-foldgutter {
      width: 12px;
    }

    .CodeMirror-foldgutter-open,
    .CodeMirror-foldgutter-folded {
      cursor: pointer;
    }

    .CodeMirror-foldgutter-open:after {
      content: "▼";
      color: #999;
      font-size: 10px;
    }

    .CodeMirror-foldgutter-folded:after {
      content: "▶";
      color: #999;
      font-size: 10px;
    }

    .CodeMirror-foldmarker {
      background: #007acc;
      color: white;
      margin: 0 3px;
      padding: 0 3px;
      border-radius: 2px;
      cursor: pointer;
      font-size: 11px;
    }

    .CodeMirror-foldmarker:hover {
      background: #005a9e;
    }

    #codeMirrorContainer {
      resize: vertical;
      overflow: hidden;
      background: #1e1e1e;
      border: none;
    }

    /* Estilos VS Code */
    .vscode-btn {
      transition: background-color 0.2s ease;
    }

    .vscode-btn:hover {
      background-color: #464647 !important;
    }

    .vscode-close:hover {
      background-color: #e81123 !important;
    }

    .vscode-toolbar-btn {
      background: none;
      border: none;
      color: #cccccc;
      padding: 4px 8px;
      border-radius: 3px;
      font-size: 12px;
      cursor: pointer;
      transition: background-color 0.2s ease;
    }

    .vscode-toolbar-btn:hover {
      background-color: #464647;
    }

    .vscode-toolbar-btn.active {
      background-color: #007acc;
      color: white;
    }

    .vscode-select {
      background: #3c3c3c !important;
      color: #cccccc !important;
      border: 1px solid #464647 !important;
      border-radius: 3px;
      padding: 4px 8px;
      font-size: 11px;
    }

    .vscode-select:focus {
      outline: 1px solid #007acc;
      border-color: #007acc !important;
    }

    .vscode-checkbox {
      color: #cccccc;
      font-size: 11px;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .vscode-checkbox input[type="checkbox"] {
      accent-color: #007acc;
      margin: 0;
    }

    .gap-5px {
      gap: 5px !important;
    }

    .gap-10px {
      gap: 10px !important;
    }

    .gap-15px {
      gap: 15px !important;
    }

    /* Estado maximizado */
    #textEditModal.maximized {
      padding: 0 !important;
    }

    #textEditModal.maximized>div {
      max-width: 100vw !important;
      max-height: 100vh !important;
      border-radius: 0 !important;
    }

    /* Barra de status VS Code */
    .vscode-status-bar {
      background: #007acc;
      color: white;
      height: 30px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 15px;
      font-size: 11px;
    }

    .vscode-status-bar .text-success {
      color: #00ff3cff !important;
      font-weight: bold;
      animation: pulse 0.5s ease-in-out;
    }

    @keyframes pulse {
      0% {
        opacity: 0.5;
      }

      50% {
        opacity: 1;
      }

      100% {
        opacity: 0.5;
      }
    }

    /* Notificação de sucesso */
    .success-notification {
      position: fixed;
      top: 20px;
      right: 20px;
      background: #28a745;
      color: white;
      padding: 15px 20px;
      border-radius: 5px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
      z-index: 9999;
      font-size: 14px;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      display: flex;
      align-items: center;
      gap: 10px;
      animation: slideInRight 0.3s ease-out, fadeOut 0.3s ease-out 2.7s;
      animation-fill-mode: both;
    }

    @keyframes slideInRight {
      from {
        transform: translateX(100%);
        opacity: 0;
      }

      to {
        transform: translateX(0);
        opacity: 1;
      }
    }

    @keyframes fadeOut {
      from {
        opacity: 1;
      }

      to {
        opacity: 0;
      }
    }

    /* Estilos para o Preview */
    #previewContainer {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    #previewContent {
      line-height: 1.6;
    }

    #previewContent h1,
    #previewContent h2,
    #previewContent h3,
    #previewContent h4,
    #previewContent h5,
    #previewContent h6 {
      margin-top: 1em;
      margin-bottom: 0.5em;
      font-weight: 600;
    }

    #previewContent h1 {
      font-size: 2em;
    }

    #previewContent h2 {
      font-size: 1.5em;
    }

    #previewContent h3 {
      font-size: 1.25em;
    }

    #previewContent p {
      margin-bottom: 1em;
    }

    #previewContent pre {
      background: #f8f9fa;
      padding: 12px;
      border-radius: 4px;
      border: 1px solid #e9ecef;
      overflow-x: auto;
    }

    #previewContent code {
      background: #f8f9fa;
      padding: 2px 4px;
      border-radius: 3px;
      font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    }

    #previewContent blockquote {
      border-left: 4px solid #007acc;
      margin: 1em 0;
      padding-left: 1em;
      color: #666;
    }

    #previewContent table {
      width: 100%;
      border-collapse: collapse;
      margin: 1em 0;
    }

    #previewContent table th,
    #previewContent table td {
      border: 1px solid #ddd;
      padding: 8px;
      text-align: left;
    }

    #previewContent table th {
      background: #f8f9fa;
      font-weight: 600;
    }

    #codeMirrorToolbar .form-select,
    #codeMirrorToolbar .btn {
      font-size: 0.875rem;
    }

    /* Melhoria na aparência do modal do editor */
    #textEditModal .modal-content {
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }

    /* Responsividade para o editor */
    @media (max-width: 768px) {
      #textEditModal>div {
        max-width: 98vw !important;
        max-height: 98vh !important;
        padding: 8px !important;
      }

      #codeMirrorToolbar {
        flex-direction: column;
        gap: 8px !important;
      }

      #codeMirrorToolbar>div {
        width: 100%;
      }

      #codeMirrorToolbar .form-select {
        width: 100% !important;
      }

      .CodeMirror-scroll {
        min-height: 60vh;
      }

      /* Preview responsivo */
      #editorContainer {
        flex-direction: column !important;
        height: auto !important;
      }

      #codeMirrorContainer,
      #previewContainer {
        flex: none !important;
        height: 50vh;
      }

      #previewContainer {
        margin-top: 10px;
      }
    }

    /* Estilos para o player de áudio flutuante melhorado */
    #floatingAudioPlayer {
      position: fixed;
      bottom: 22px;
      left: 20px;
      width: 375px;
      background: linear-gradient(135deg, #1f8657 0%, #21cb7f 100%);
      border-radius: 12px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
      backdrop-filter: blur(10px);
      z-index: 9999;
      color: white;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      transition: all 0.3s ease;
      border: 1px solid rgba(255, 255, 255, 0.1);
    }

    /* Estilos para a galeria de imagens */
    #imagePreviewModal {
      user-select: none;
    }

    #imagePreviewModal img {
      transition: transform 0.3s ease;
    }

    #imagePreviewModal img.zoomed {
      transform: scale(1.5);
      cursor: grab;
    }

    #imagePreviewModal img.zoomed:active {
      cursor: grabbing;
    }

    #imageThumbnailGallery {
      scrollbar-width: thin;
      scrollbar-color: rgba(255, 255, 255, 0.3) transparent;
    }

    #imageThumbnailGallery::-webkit-scrollbar {
      height: 6px;
    }

    #imageThumbnailGallery::-webkit-scrollbar-track {
      background: rgba(255, 255, 255, 0.1);
      border-radius: 3px;
    }

    #imageThumbnailGallery::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.3);
      border-radius: 3px;
    }

    #imageThumbnailGallery::-webkit-scrollbar-thumb:hover {
      background: rgba(255, 255, 255, 0.5);
    }

    .thumbnail-item {
      width: 60px;
      height: 60px;
      border-radius: 4px;
      cursor: pointer;
      border: 2px solid transparent;
      transition: all 0.2s ease;
      object-fit: cover;
      flex-shrink: 0;
    }

    .thumbnail-item:hover {
      border-color: rgba(255, 255, 255, 0.5);
      transform: scale(1.05);
    }

    .thumbnail-item.active {
      border-color: #007acc;
      box-shadow: 0 0 8px rgba(0, 122, 204, 0.6);
    }

    /* Navegação com teclado */
    #imagePreviewModal .btn {
      transition: all 0.2s ease;
    }

    #imagePreviewModal .btn:hover {
      transform: scale(1.05);
    }

    /* Responsividade da galeria */
    @media (max-width: 768px) {

      #prevImageBtn,
      #nextImageBtn {
        width: 40px;
        height: 40px;
        padding: 0;
        font-size: 16px;
      }

      #prevImageBtn {
        left: 10px;
      }

      #nextImageBtn {
        right: 10px;
      }

      #imageInfo {
        top: -50px;
      }

      #imageThumbnailGallery {
        bottom: 10px;
        max-width: 95vw;
        padding: 8px;
      }

      .thumbnail-item {
        width: 50px;
        height: 50px;
      }
    }

    #floatingAudioPlayer.minimized {
      height: 60px;
      overflow: hidden;
    }

    #floatingAudioPlayer.minimized .floating-player-body {
      display: none;
    }

    .floating-player-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 16px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 12px 12px 0 0;
      cursor: pointer;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .player-title {
      display: flex;
      align-items: center;
      gap: 8px;
      font-weight: 600;
      font-size: 14px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      flex: 1;
    }

    .player-controls-header {
      display: flex;
      gap: 4px;
    }

    .floating-player-body {
      padding: 16px;
    }

    .player-info {
      margin-bottom: 16px;
    }

    .track-info {
      text-align: center;
    }

    .track-name {
      font-size: 16px;
      font-weight: 600;
      margin-bottom: 4px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .track-time {
      font-size: 12px;
      opacity: 0.8;
    }

    .player-progress {
      margin-bottom: 16px;
    }

    .progress-bar-container {
      width: 100%;
      height: 6px;
      background: rgba(255, 255, 255, 0.2);
      border-radius: 3px;
      position: relative;
      cursor: pointer;
    }

    .progress-bar {
      width: 100%;
      height: 100%;
      position: relative;
    }

    .progress-fill {
      height: 100%;
      background: linear-gradient(90deg, #198754, #1f8657);
      border-radius: 3px;
      width: 0%;
      transition: width 0.1s ease;
    }

    .progress-handle {
      position: absolute;
      top: 50%;
      right: -6px;
      width: 12px;
      height: 12px;
      background: white;
      border-radius: 50%;
      transform: translateY(-50%);
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
      cursor: pointer;
      opacity: 0;
      transition: opacity 0.2s ease;
    }

    .progress-bar-container:hover .progress-handle {
      opacity: 1;
    }

    .player-controls {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 12px;
      margin-bottom: 16px;
    }

    .btn-player-control {
      background: rgba(255, 255, 255, 0.1);
      border: 1px solid rgba(255, 255, 255, 0.2);
      color: white;
      padding: 8px 12px;
      border-radius: 20px;
      cursor: pointer;
      transition: all 0.2s ease;
      font-size: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .btn-player-control:hover {
      background: rgba(255, 255, 255, 0.2);
      transform: translateY(-1px);
    }

    .btn-player-control:active {
      transform: translateY(0);
    }

    .play-pause-btn {
      width: 35px;
      height: 35px;
      font-size: 15;
      background: rgba(255, 255, 255, 0.2);
    }

    .play-pause-btn:hover {
      background: rgba(255, 255, 255, 0.3);
    }

    .volume-control {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .volume-slider {
      width: 60px;
    }

    .volume-slider input[type="range"] {
      width: 100%;
      height: 4px;
      background: rgba(255, 255, 255, 0.2);
      border-radius: 2px;
      outline: none;
      -webkit-appearance: none;
    }

    .volume-slider input[type="range"]::-webkit-slider-thumb {
      -webkit-appearance: none;
      width: 12px;
      height: 12px;
      background: white;
      border-radius: 50%;
      cursor: pointer;
    }

    .volume-slider input[type="range"]::-moz-range-thumb {
      width: 12px;
      height: 12px;
      background: white;
      border-radius: 50%;
      cursor: pointer;
      border: none;
    }

    .playlist-container {
      border-top: 1px solid rgba(255, 255, 255, 0.1);
      padding-top: 16px;
      max-height: 200px;
      overflow-y: auto;
    }

    .playlist-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
    }

    .playlist-header h6 {
      margin: 0;
      font-size: 14px;
      font-weight: 600;
    }

    .playlist {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .playlist-item {
      padding: 8px 12px;
      background: rgba(255, 255, 255, 0.05);
      border-radius: 6px;
      cursor: pointer;
      transition: all 0.2s ease;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .playlist-item:hover {
      background: rgba(255, 255, 255, 0.1);
    }

    .playlist-item.active {
      background: rgba(255, 255, 255, 0.2);
      border-left: 3px solid #4ecdc4;
    }

    .playlist-item-name {
      font-size: 13px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      flex: 1;
    }

    .playlist-item-remove {
      color: #ff6b6b;
      cursor: pointer;
      padding: 2px 6px;
      border-radius: 3px;
      font-size: 12px;
    }

    .playlist-item-remove:hover {
      background: rgba(255, 107, 107, 0.2);
    }

    .btn-player-control.active {
      background: rgba(255, 255, 255, 0.3);
      color: #4ecdc4;
    }

    @media (max-width: 480px) {
      #floatingAudioPlayer {
        width: calc(100vw - 20px);
        left: 10px;
        right: 10px;
        bottom: 80px;
        /* Espaço para não sobrepor com botões da interface */
      }

      .floating-player-body {
        padding: 12px;
      }

      .player-controls {
        flex-wrap: wrap;
        gap: 6px;
      }

      .volume-control {
        order: 1;
        width: 100%;
        justify-content: center;
        margin-top: 8px;
      }
    }

    /* Animações para o player */
    @keyframes slideInUp {
      from {
        transform: translateY(100px);
        opacity: 0;
      }

      to {
        transform: translateY(0);
        opacity: 1;
      }
    }

    #floatingAudioPlayer.show {
      animation: slideInUp 0.3s ease;
    }

    /* Customização da scrollbar da playlist */
    .playlist-container::-webkit-scrollbar {
      width: 4px;
    }

    .playlist-container::-webkit-scrollbar-track {
      background: rgba(255, 255, 255, 0.1);
      border-radius: 2px;
    }

    .playlist-container::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.3);
      border-radius: 2px;
    }

    .playlist-container::-webkit-scrollbar-thumb:hover {
      background: rgba(255, 255, 255, 0.5);
    }

    /* Indicador de música tocando na navbar */
    .audio-playing-indicator {
      display: flex;
      justify-content: center;
      align-items: end;
      height: 12px;
      gap: 2px;
      margin-top: 2px;
    }

    .eq-bar {
      width: 3px;
      background: #4ecdc4;
      border-radius: 1px;
      animation: eq-animation 1.2s infinite ease-in-out;
    }

    .eq-bar:nth-child(1) {
      animation-delay: 0s;
    }

    .eq-bar:nth-child(2) {
      animation-delay: 0.1s;
    }

    .eq-bar:nth-child(3) {
      animation-delay: 0.2s;
    }

    @keyframes eq-animation {

      0%,
      100% {
        height: 4px;
      }

      50% {
        height: 12px;
      }
    }

    /* Destacar botão do player quando ativo */
    #toggleAudioPlayerBtn.playing {
      background: linear-gradient(135deg, #1f8657 0%, #21cb7f 100%) !important;
      border-color: #21cb7f !important;
      color: white !important;
    }

    .btn-outline-player {
      border: 2px solid #198754 !important;
      color: #198754 !important;
      transition: all 0.2s ease;
    }

    /* Estilos para Drag and Drop */
    .draggable-item {
      cursor: move;
      transition: all 0.2s ease;
      position: relative;
    }

    .draggable-item:hover {
      background-color: rgba(25, 135, 84, 0.1);
    }

    .draggable-item.dragging {
      opacity: 0.6;
      transform: scale(0.98);
      background-color: rgba(25, 135, 84, 0.2);
      border: 2px dashed #198754;
      z-index: 1000;
    }

    .draggable-item.dragging:after {
      content: attr(data-drag-count) " item(s) - Movendo...";
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: rgba(25, 135, 84, 0.9);
      color: white;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 12px;
      font-weight: bold;
      white-space: nowrap;
      pointer-events: none;
      z-index: 1001;
    }

    .drop-zone {
      transition: all 0.2s ease;
      position: relative;
    }

    .drop-zone.drag-over {
      background-color: rgba(25, 135, 84, 0.3) !important;
      outline: 3px solid #198754 !important;
      outline-offset: -2px;
      transform: scale(1.02);
      z-index: 999;
    }

    .drop-zone.drag-over:before {
      content: "📁 Soltar aqui";
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: rgba(25, 135, 84, 0.95);
      color: white;
      padding: 6px 12px;
      border-radius: 6px;
      font-size: 13px;
      font-weight: bold;
      white-space: nowrap;
      pointer-events: none;
      z-index: 1000;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    }

    /* Estilos específicos para pastas quando são alvos de drop */
    tr.is_dir.drop-zone.drag-over {
      background: linear-gradient(135deg, rgba(25, 135, 84, 0.4), rgba(25, 135, 84, 0.2)) !important;
      box-shadow: 0 4px 12px rgba(25, 135, 84, 0.4);
    }

    .file-card.is-dir.drop-zone.drag-over {
      background: linear-gradient(135deg, rgba(25, 135, 84, 0.4), rgba(25, 135, 84, 0.2)) !important;
      box-shadow: 0 8px 20px rgba(25, 135, 84, 0.4);
    }

    /* Estilo para breadcrumb quando é alvo de drop */
    #breadcrumb a.drop-zone.drag-over {
      background-color: rgba(25, 135, 84, 0.8) !important;
      color: white !important;
      padding: 6px 12px;
      border-radius: 6px;
      font-weight: bold;
    }

    #breadcrumb a.drop-zone.drag-over:before {
      content: "🏠 ";
    }

    /* Indicador de carregamento durante drag */
    .drag-loading {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: rgba(0, 0, 0, 0.9);
      color: white;
      padding: 20px 30px;
      border-radius: 12px;
      z-index: 10000;
      font-size: 16px;
      font-weight: 500;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .drag-loading:before {
      content: "🔄";
      font-size: 20px;
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      from {
        transform: rotate(0deg);
      }

      to {
        transform: rotate(360deg);
      }
    }

    /* Tornar cards de arquivo também arrastáveis visualmente */
    .file-card.draggable-item {
      cursor: grab;
    }

    .file-card.draggable-item:active {
      cursor: grabbing;
    }

    /* Melhorar aparência visual durante drag para cards */
    .file-card.draggable-item.dragging {
      box-shadow: 0 12px 32px rgba(0, 0, 0, 0.3);
      border-color: #198754;
    }

    /* Melhorar cursor quando está arrastando sobre área não válida */
    .draggable-item.dragging~* {
      cursor: not-allowed;
    }

    /* Responsividade para dispositivos menores */
    @media (max-width: 768px) {
      .draggable-item.dragging:after {
        font-size: 10px;
        padding: 2px 6px;
      }

      .drop-zone.drag-over:before {
        font-size: 11px;
        padding: 4px 8px;
      }
    }

    /* ========== ESTILOS DO MENU LATERAL ========== */

    /* Overlay para mobile */
    .sidebar-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      z-index: 1999;
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s ease;
    }

    .sidebar-overlay.active {
      opacity: 1;
      visibility: visible;
    }

    /* Sidebar principal */
    .sidebar {
      position: fixed;
      top: 0;
      left: -320px;
      width: 320px;
      height: auto;
      background: linear-gradient(145deg, #1a1a1a 0%, #2d2d2d 100%);
      border-right: 3px solid #1f8657;
      z-index: 2000;
      transition: left 0.3s ease;
      overflow-y: hidden;
      overflow-x: hidden;
      box-shadow: 2px 0 15px rgba(0, 0, 0, 0.3);
    }

    .sidebar.active {
      left: 0;
    }

    /* Header do sidebar */
    .sidebar-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 20px;
      background: linear-gradient(145deg, #1f8657 0%, #1a6b46 100%);
      border-bottom: 2px solid #145f3a;
      position: relative;
    }

    .sidebar-brand {
      display: flex;
      align-items: center;
      color: #ffffff;
      font-size: 18px;
      font-weight: 600;
    }

    .sidebar-brand i {
      font-size: 24px;
      margin-right: 12px;
      color: #4ecdc4;
    }

    .sidebar-brand-text {
      font-family: 'Segoe UI', Arial, sans-serif;
      letter-spacing: 0.5px;
    }

    .sidebar-close-btn {
      background: rgba(255, 255, 255, 0.2);
      border: none;
      color: #ffffff;
      width: 32px;
      height: 32px;
      border-radius: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .sidebar-close-btn:hover {
      background: rgba(255, 255, 255, 0.3);
      transform: scale(1.05);
    }

    /* Timer de sessão no sidebar */
    .sidebar-session-timer {
      padding: 15px 20px;
      background: rgba(31, 134, 87, 0.2);
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      color: #4ecdc4;
      font-size: 13px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .sidebar-session-timer i {
      font-size: 14px;
    }

    .session-text {
      font-weight: 500;
    }

    /* Menu do sidebar */
    .sidebar-menu {
      padding: 10px 0;
      overflow-y: scroll;
      height: calc(100vh - 112px);
    }

    /* Seções do menu */
    .menu-section {
      margin-bottom: 25px;
    }

    .menu-section-title {
      padding: 12px 20px 8px 20px;
      color: #4ecdc4;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 1px;
      display: flex;
      align-items: center;
      gap: 8px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
      margin-bottom: 5px;
    }

    .menu-section-title i {
      font-size: 14px;
    }

    /* Items do menu */
    .menu-items {
      padding: 0 10px;
    }

    .menu-item {
      width: 100%;
      background: none;
      border: none;
      color: #ffffff;
      padding: 12px 15px;
      margin-bottom: 3px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      gap: 12px;
      cursor: pointer;
      transition: all 0.2s ease;
      text-align: left;
      position: relative;
      font-size: 14px;
    }

    .menu-item:hover {
      background: rgba(255, 255, 255, 0.1);
      transform: translateX(5px);
      color: #ffffff;
    }

    .menu-item:disabled {
      opacity: 0.5;
      cursor: not-allowed;
      transform: none;
    }

    .menu-item:disabled:hover {
      background: none;
      transform: none;
    }

    .menu-item i {
      width: 20px;
      font-size: 16px;
      text-align: center;
      color: #4ecdc4;
      transition: all 0.2s ease;
    }

    .menu-item:hover i {
      color: #ffffff;
      transform: scale(1.1);
    }

    .menu-text {
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .menu-label {
      font-weight: 500;
      font-size: 14px;
      line-height: 1.2;
    }

    .menu-desc {
      font-size: 11px;
      color: #999999;
      font-weight: 400;
    }

    .menu-value {
      font-size: 12px;
      color: #4ecdc4;
      font-weight: 500;
    }

    /* Badge para contador */
    .menu-badge {
      background: #ff4757;
      color: #ffffff;
      font-size: 10px;
      font-weight: 600;
      padding: 2px 6px;
      border-radius: 10px;
      min-width: 18px;
      text-align: center;
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
    }

    /* Variações de cores para items especiais */
    .menu-item-primary:hover {
      background: rgba(78, 205, 196, 0.2);
    }

    .menu-item-primary:hover i {
      color: #4ecdc4;
    }

    .menu-item-info:hover {
      background: rgba(78, 205, 196, 0.2);
    }

    .menu-item-info:hover i {
      color: #4ecdc4;
    }

    .menu-item-danger:hover {
      background: rgba(255, 71, 87, 0.2);
    }

    .menu-item-danger:hover i {
      color: #ff4757;
    }

    .menu-item-warning:hover {
      background: rgba(255, 159, 67, 0.2);
    }

    .menu-item-warning:hover i {
      color: #ff9f43;
    }

    .menu-item-success:hover {
      background: rgba(31, 134, 87, 0.2);
    }

    .menu-item-success:hover i {
      color: #1f8657;
    }

    /* Estilo especial para o item do player quando estiver tocando */

    .menu-item-player.playing {
      background: rgba(31, 134, 87, 0.3);
      border-left: 3px solid #1f8657;
    }

    .menu-item-player.playing i {
      color: #1f8657;
      animation: pulse 1.5s infinite;
    }

    @keyframes pulse {

      0%,
      100% {
        opacity: 1;
      }

      50% {
        opacity: 0.7;
      }
    }

    /* Conteúdo principal */
    .main-content {
      transition: margin-left 0.3s ease;
      min-height: 100vh;
      background: #1a1a1a;
      /* Dark background */
      color: #e0e0e0;
      /* Light text */
    }

    /* Header superior */
    .top-header {
      background: #2d2d2d;
      /* Dark header */
      border-bottom: 2px solid #404040;
      padding: 15px 20px;
      display: flex;
      align-items: center;
      gap: 20px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
      position: sticky;
      top: 0;
      z-index: 1000;
      color: #e0e0e0;
      /* Light text */
    }

    /* Botão toggle do sidebar */
    .sidebar-toggle-btn {
      background: #1f8657;
      border: none;
      color: #ffffff;
      width: 40px;
      height: 40px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.2s ease;
      font-size: 16px;
    }

    .sidebar-toggle-btn:hover {
      background: #1a6b46;
      transform: scale(1.05);
      box-shadow: 0 2px 8px rgba(31, 134, 87, 0.3);
    }

    /* Breadcrumb container */
    .breadcrumb-container {
      flex: 1;
      font-size: 14px;
      color: #e0e0e0;
    }

    /* Ajustar cores dos links no breadcrumb para dark mode */
    .breadcrumb-container a {
      color: #4ecdc4 !important;
      text-decoration: none;
    }

    .breadcrumb-container a:hover {
      color: #ffffff !important;
    }

    /* Responsividade */
    @media (min-width: 1200px) {
      .sidebar {
        left: 0;
      }

      .main-content {
        margin-left: 320px;
      }

      .sidebar-toggle-btn {
        display: none;
      }

      .sidebar-close-btn {
        display: none;
      }
    }

    @media (max-width: 1199px) {
      .sidebar {
        left: -320px;
      }

      .main-content {
        margin-left: 0;
      }
    }

    @media (max-width: 768px) {
      .sidebar {
        width: 280px;
        left: -280px;
      }

      .top-header {
        padding: 10px 15px;
      }

      .sidebar-header {
        padding: 15px;
      }

      .sidebar-brand {
        font-size: 16px;
      }

      .menu-item {
        padding: 10px 12px;
      }

      .menu-text {
        gap: 1px;
      }

      .menu-label {
        font-size: 13px;
      }

      .menu-desc {
        font-size: 10px;
      }
    }

    @media (max-width: 480px) {
      .sidebar {
        width: 260px;
        left: -260px;
      }

      .top-header {
        padding: 8px 12px;
      }

      .sidebar-toggle-btn {
        width: 36px;
        height: 36px;
        font-size: 14px;
      }
    }

    /* Ajuste da tabela para o novo layout */
    #table {
      background: #2d2d2d;
      /* Dark table background */
      overflow: hidden;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
      color: #e0e0e0;
      /* Light text */
    }

    /* Estilizar cabeçalho da tabela */
    #table thead th {
      background: #1f8657;
      color: #ffffff;
      border: none;
      padding: 12px;
      font-weight: 600;
    }

    /* Estilizar linhas da tabela */
    #table tbody tr {
      background: #2d2d2d;
      border-bottom: 1px solid #404040;
    }

    #table tbody tr:hover {
      background: #404040;
    }

    #table tbody td {
      color: #e0e0e0;
      border: none;
      padding: 12px;
    }

    /* Estilizar links de arquivos e pastas */
    #table tbody td a {
      color: #4ecdc4 !important;
      text-decoration: none;
    }

    #table tbody td a:hover {
      color: #ffffff !important;
      text-decoration: underline;
    }

    /* Destaque para pastas */
    .name-dir,
    .is_dir .name {
      color: #ffc107 !important;
      font-weight: 600;
    }

    /* Destaque para arquivos */
    .name-arc {
      color: #4ecdc4 !important;
      font-weight: 500;
    }

    /* Ajustar botões para dark mode */
    .btn {
      border: 1px solid transparent;
    }

    .btn-primary {
      background-color: #1f8657;
      border-color: #1f8657;
    }

    .btn-primary:hover {
      background-color: #145f3a;
      border-color: #145f3a;
    }

    .btn-secondary {
      background-color: #404040;
      border-color: #404040;
      color: #e0e0e0;
    }

    .btn-secondary:hover {
      background-color: #505050;
      border-color: #505050;
      color: #ffffff;
    }

    .btn-success {
      background-color: #1f8657;
      border-color: #1f8657;
    }

    .btn-success:hover {
      background-color: #145f3a;
      border-color: #145f3a;
    }

    .btn-danger {
      background-color: #dc3545;
      border-color: #dc3545;
    }

    .btn-danger:hover {
      background-color: #c82333;
      border-color: #bd2130;
    }

    /* Ajuste do grid de arquivos */
    .file-grid-view {
      padding: 10px;
      margin: 10px;
      background: #1a1a1a;
      border-radius: 8px;
      min-height: 400px;
      /* Garantir altura mínima */
    }

    /* Ajuste da área de seleção */
    .d-flex.align-items-center.p-2 {
      background: #1f8657 !important;
      /* Manter o verde, mas ajustar para dark */
      border-bottom: 2px solid #145f3a;
    }

    /* Melhorar aparência dos botões desabilitados no sidebar */
    .menu-item-disabled {
      opacity: 0.4;
      cursor: not-allowed;
    }

    .menu-item-disabled:hover {
      background: none !important;
      transform: none !important;
    }

    .menu-item-disabled i {
      color: #666666 !important;
    }

    /* Esconder a barra de rolagem customizada do sidebar */
    .sidebar::-webkit-scrollbar {
      width: 6px;
    }

    .sidebar::-webkit-scrollbar-track {
      background: rgba(255, 255, 255, 0.1);
    }

    .sidebar::-webkit-scrollbar-thumb {
      background: rgba(78, 205, 196, 0.5);
      border-radius: 3px;
    }

    .sidebar::-webkit-scrollbar-thumb:hover {
      background: rgba(78, 205, 196, 0.8);
    }

    /* Dark theme global improvements */
    body {
      background: #1a1a1a;
      color: #ffffff;
    }

    /* Main content area */
    .main-content {
      background: #1a1a1a;
      color: #ffffff;
      padding: 0;
      min-height: 100vh;
    }

    /* Top header styling */
    .top-header {
      background: #2d2d2d;
      border-bottom: 2px solid #1f8657;
      color: #ffffff;
      padding: 15px 20px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    /* Breadcrumb improvements */
    #breadcrumb {
      background: #2d2d2d;
      color: #ffffff;
      padding: 15px 20px;
      border-radius: 0;
      margin: 0;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    #breadcrumb a {
      color: #21cb7f;
      text-decoration: none;
      transition: color 0.3s ease;
    }

    #breadcrumb a:hover {
      color: #4CAF50;
    }

    /* Table improvements */
    table {
      background: #2d2d2d;
      border-radius: 0;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
      margin: 15px 0 0 0;
    }

    th {
      background: #1f8657;
      color: #ffffff;
      padding: 15px;
      border-bottom: 2px solid #145f3a;
      font-weight: 600;
    }

    td {
      background: #2d2d2d;
      color: #ffffff;
      padding: 12px 15px;
      border-bottom: 1px solid #404040;
    }

    tr:hover td {
      background: #155135;
      color: #ffffff!important;
    }

    /* Form controls */
    .form-control {
      background: #2d2d2d;
      border: 1px solid #404040;
      color: #ffffff;
      transition: all 0.3s ease;
    }

    .form-control:focus {
      background: #2d2d2d;
      border-color: #21cb7f;
      color: #ffffff;
      box-shadow: 0 0 0 0.2rem rgba(33, 203, 127, 0.25);
    }

    .form-control::placeholder {
      color: #999999;
    }

    /* Button improvements */
    .btn {
      border-radius: 6px;
      padding: 8px 16px;
      font-weight: 500;
      transition: all 0.3s ease;
    }

    .btn:hover {
      transform: translateY(-1px);
    }

    .btn-outline-success {
      border-color: #21cb7f;
      color: #21cb7f;
    }

    .btn-outline-success:hover {
      background: #21cb7f;
      color: #ffffff;
    }

    .btn-outline-primary {
      border-color: #0d6efd;
      color: #0d6efd;
    }

    .btn-outline-primary:hover {
      background: #0d6efd;
      color: #ffffff;
    }

    .btn-outline-danger {
      border-color: #dc3545;
      color: #dc3545;
    }

    .btn-outline-danger:hover {
      background: #dc3545;
      color: #ffffff;
    }

    .btn-outline-warning {
      border-color: #ffc107;
      color: #ffc107;
    }

    .btn-outline-warning:hover {
      background: #ffc107;
      color: #000000;
    }

    .btn-outline-info {
      border-color: #0dcaf0;
      color: #0dcaf0;
    }

    .btn-outline-info:hover {
      background: #0dcaf0;
      color: #000000;
    }

    /* Modal improvements */
    .modal-content {
      background: #2d2d2d;
      border: 1px solid #404040;
      color: #ffffff;
    }

    .modal-header {
      border-bottom: 1px solid #404040;
      background: #242424;
    }

    .modal-footer {
      border-top: 1px solid #404040;
      background: #242424;
    }

    .modal-title {
      color: #21cb7f;
    }

    /* Badge improvements */
    .badge {
      font-size: 0.75em;
      font-weight: 500;
    }

    .badge.bg-success {
      background-color: #21cb7f !important;
    }

    /* Upload area */
    #file_drop_target {
      background: #2a2a2a;
      border: 2px dashed #21cb7f;
      color: #ffffff;
      border-radius: 12px;
      transition: all 0.3s ease;
    }

    #file_drop_target.drag_over {
      border-color: #4CAF50;
      background: rgba(76, 175, 80, 0.1);
    }

    /* Progress bars */
    .progress {
      background: #404040;
    }

    .progress-bar {
      background: #21cb7f;
    }

    /* File name styling */
    .name {
      color: #21cb7f;
      font-weight: 500;
    }

    .name-dir {
      color: #4CAF50;
      font-weight: 600;
    }

    /* Icons and visual elements */
    .fa-folder {
      color: #ffc107 !important;
    }

    .fa-file-o {
      color: #6c757d !important;
    }

    /* Table sorting indicators */
    .indicator {
      color: #21cb7f;
    }

    /* Responsive improvements */
    @media (max-width: 768px) {
      .main-content {
        padding: 10px;
      }

      #breadcrumb {
        padding: 10px 15px;
        font-size: 13px;
      }

      table th,
      table td {
        padding: 8px 10px;
        font-size: 0.9rem;
      }
    }
  </style>
  <!-- ADDED: Modal para visualização de conteúdo ZIP -->
  <div class="modal fade" id="zipViewModal" tabindex="-1" aria-labelledby="zipViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="zipViewModalLabel">
            <i class="fa fa-file-archive-o"></i> Conteúdo do Arquivo ZIP
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row mb-3">
            <div class="col-md-8 text-dark">
              <strong>Arquivo:</strong> <span class="text-dark" id="zipFileName"></span>
            </div>
            <div class="col-md-4 text-end text-dark">
              <strong>Total de arquivos:</strong> <span class="text-dark" id="zipFileCount"></span>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label for="extractFolderName" class="form-label">Nome da pasta para extração:</label>
              <input type="text" class="form-control" id="extractFolderName" placeholder="Digite o nome da pasta">
            </div>
            <div class="col-md-6 d-flex align-items-end">
              <button type="button" class="btn btn-success" id="extractZipBtn">
                <i class="fa fa-download"></i> Extrair ZIP
              </button>
            </div>
          </div>

          <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
            <table class="table table-striped table-sm">
              <thead class="table-dark sticky-top">
                <tr>
                  <th><i class="fa fa-file-o"></i> Nome</th>
                  <th><i class="fa fa-calendar"></i> Modificado</th>
                  <th><i class="fa fa-hdd-o"></i> Tamanho</th>
                  <th><i class="fa fa-compress"></i> Comprimido</th>
                </tr>
              </thead>
              <tbody id="zipFilesList">
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fa fa-times"></i> Fechar
          </button>
        </div>
      </div>
    </div>
  </div>
  <!-- Modal da Lixeira -->
  <div class="modal fade" id="trashModal" tabindex="-1" aria-labelledby="trashModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="trashModalLabel">Lixeira</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div id="trashAlert" style="display:none;"></div>
          <div class="mb-2 d-flex gap-2">
            <button id="restoreAllTrashBtn" class="btn btn-success btn-sm"><i class="fa fa-undo"></i>
              Restaurar
              Todos</button>
            <button id="emptyTrashBtn" class="btn btn-danger btn-sm"><i class="fa fa-trash"></i> Esvaziar
              Lixeira</button>
          </div>
          <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
              <thead class="table-dark">
                <tr>
                  <th>Nome</th>
                  <th>Tipo</th>
                  <th>Data Exclusão</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody id="trashList">
                <tr>
                  <td colspan="5" class="text-center">Carregando...</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Handlers para restaurar todos e esvaziar lixeira
    $(function () {
      $('#restoreAllTrashBtn').on('click', function () {
        if (!confirm('Restaurar TODOS os itens da lixeira?')) return;
        $.post('?', { do: 'restorealltrash', xsrf: (document.cookie.match('(^|; )_sfm_xsrf=([^;]*)') || 0)[2] }, function (res) {
          if (res && res.success) {
            showTrashAlert('Todos os itens foram restaurados.', 'success');
            loadTrashList();
          } else {
            showTrashAlert('Erro ao restaurar todos: ' + (res && res.error ? res.error.msg : 'unknown'), 'danger');
          }
        }, 'json').fail(function () {
          showTrashAlert('Falha na requisição.', 'danger');
        });
      });
      $('#emptyTrashBtn').on('click', function () {
        if (!confirm('Excluir PERMANENTEMENTE todos os itens da lixeira? Esta ação não pode ser desfeita.')) return;
        $.post('?', { do: 'emptytrash', xsrf: (document.cookie.match('(^|; )_sfm_xsrf=([^;]*)') || 0)[2] }, function (res) {
          if (res && res.success) {
            showTrashAlert('Lixeira esvaziada.', 'success');
            loadTrashList();
          } else {
            showTrashAlert('Erro ao limpar lixeira: ' + (res && res.error ? res.error.msg : 'unknown'), 'danger');
          }
        }, 'json').fail(function () {
          showTrashAlert('Falha na requisição.', 'danger');
        });
      });
    });
    (function ($) {
      $.fn.tablesorter = function () {
        var $table = this;
        this.find('th').click(function () {
          var idx = $(this).index();
          var direction = $(this).hasClass('sort_asc');
          // apenas dispara a ordenação da tabela aqui — o timer de sessão foi unificado
          $table.tablesortby(idx, direction);
        });
        return this;
      };
      $.fn.tablesortby = function (idx, direction) {
        var $rows = this.find('tbody tr');
        function elementToVal(a) {
          var $a_elem = $(a).find('td:nth-child(' + (idx + 1) + ')');
          var a_val = $a_elem.attr('data-sort') || $a_elem.text();
          return (a_val == parseInt(a_val) ? parseInt(a_val) : a_val);
        }
        $rows.sort(function (a, b) {
          var a_val = elementToVal(a), b_val = elementToVal(b);
          return (a_val > b_val ? 1 : (a_val == b_val ? 0 : -1)) * (direction ? 1 : -1);
        })
        this.find('th').removeClass('sort_asc sort_desc');
        $(this).find('thead th:nth-child(' + (idx + 1) + ')').addClass(direction ? 'sort_desc' : 'sort_asc');
        for (var i = 0; i < $rows.length; i++)
          this.append($rows[i]);
        this.settablesortmarkers();
        return this;
      }
      $.fn.retablesort = function () {
        var $e = this.find('thead th.sort_asc, thead th.sort_desc');
        if ($e.length)
          this.tablesortby($e.index(), $e.hasClass('sort_desc'));

        return this;
      }
      $.fn.settablesortmarkers = function () {
        this.find('thead th span.indicator').remove();
        this.find('thead th.sort_asc').append('<span class="indicator">&darr;<span>');
        this.find('thead th.sort_desc').append('<span class="indicator">&uarr;<span>');
        return this;
      }
    })(jQuery);

    // ADDED: variáveis globais para XSRF token
    var XSRF = (document.cookie.match('(^|; )_sfm_xsrf=([^;]*)') || 0)[2];
    var token = XSRF; // Para compatibilidade

    $(function () {
      var MAX_UPLOAD_SIZE = <?php echo $MAX_UPLOAD_SIZE ?>;

      // Debug: Mostrar informações de limite de upload
      console.log('Limites de Upload:');
      console.log('Configurado: <?php echo $max_upload_size_mb ?>MB');
      console.log('PHP post_max_size: <?php echo round($php_post_max / 1024 / 1024, 2) ?>MB');
      console.log('PHP upload_max_filesize: <?php echo round($php_upload_max / 1024 / 1024, 2) ?>MB');
      console.log('Efetivo: <?php echo round($MAX_UPLOAD_SIZE / 1024 / 1024, 2) ?>MB');

      // ADDED: Sistema de Drag and Drop para mover arquivos
      var dragAndDropMover = {
        draggedFiles: [],
        isDragging: false,
        dragStartTime: 0,

        init: function () {
          this.bindEvents();
          this.addDropZones();
        },

        bindEvents: function () {
          var self = this;

          // Eventos de drag para itens de arquivo/pasta
          $(document).on('dragstart', '.draggable-item', function (e) {
            e.originalEvent.dataTransfer.effectAllowed = 'move';

            var filePath = $(this).find('.select-item').attr('data-file') || $(this).attr('data-file');
            if (!filePath) return false;

            // Se o item sendo arrastado não está selecionado, limpar seleção e selecionar apenas ele
            if (!$(this).find('.select-item').prop('checked')) {
              $('.select-item').prop('checked', false);
              $(this).find('.select-item').prop('checked', true);
              if (typeof updateDeleteSelectedBtn === 'function') {
                updateDeleteSelectedBtn();
              }
            }

            // Coletar todos os arquivos selecionados
            self.draggedFiles = $('.select-item:checked').map(function () {
              return $(this).attr('data-file');
            }).get();

            self.isDragging = true;
            self.dragStartTime = Date.now();

            // Definir dados de transferência
            e.originalEvent.dataTransfer.setData('text/plain', JSON.stringify(self.draggedFiles));

            // Adicionar classe visual
            $(this).addClass('dragging').attr('data-drag-count', self.draggedFiles.length);
            $('.is_dir, .file-card.is-dir').addClass('drop-zone');

            //console.log('Iniciando drag com arquivos:', self.draggedFiles);
          });

          $(document).on('dragend', '.draggable-item', function (e) {
            $(this).removeClass('dragging');
            $('.drop-zone').removeClass('drop-zone drag-over');

            setTimeout(function () {
              self.isDragging = false;
              self.draggedFiles = [];
            }, 100);
          });
        },

        addDropZones: function () {
          var self = this;

          // Adicionar zona de drop para pastas na tabela
          $(document).on('dragover', 'tr.is_dir', function (e) {
            if (!self.isDragging) return;
            e.preventDefault();
            e.originalEvent.dataTransfer.dropEffect = 'move';

            var targetPath = $(this).find('.select-item').attr('data-file');
            if (targetPath && self.draggedFiles.indexOf(targetPath) === -1) {
              // Verificar se é uma movimentação válida (não mover pasta para dentro dela mesma)
              var isValidDrop = !self.draggedFiles.some(function (file) {
                return targetPath.startsWith(file + '/') || targetPath === file;
              });

              if (isValidDrop) {
                $(this).addClass('drop-zone drag-over');
                e.originalEvent.dataTransfer.dropEffect = 'move';
              } else {
                e.originalEvent.dataTransfer.dropEffect = 'none';
              }
            } else {
              e.originalEvent.dataTransfer.dropEffect = 'none';
            }
          });

          $(document).on('dragleave', 'tr.is_dir', function (e) {
            $(this).removeClass('drag-over');
          });

          $(document).on('drop', 'tr.is_dir', function (e) {
            if (!self.isDragging) return;
            e.preventDefault();

            var targetPath = $(this).find('.select-item').attr('data-file');
            if (targetPath && self.draggedFiles.indexOf(targetPath) === -1) {
              // Verificar se é uma movimentação válida
              var isValidDrop = !self.draggedFiles.some(function (file) {
                return targetPath.startsWith(file + '/') || targetPath === file;
              });

              if (isValidDrop) {
                self.moveFiles(self.draggedFiles, targetPath);
              }
            }

            $(this).removeClass('drop-zone drag-over');
          });

          // Adicionar zona de drop para cards de pasta no grid
          $(document).on('dragover', '.file-card.is-dir', function (e) {
            if (!self.isDragging) return;
            e.preventDefault();
            e.originalEvent.dataTransfer.dropEffect = 'move';

            var targetPath = $(this).find('.select-item').attr('data-file');
            if (targetPath && self.draggedFiles.indexOf(targetPath) === -1) {
              // Verificar se é uma movimentação válida
              var isValidDrop = !self.draggedFiles.some(function (file) {
                return targetPath.startsWith(file + '/') || targetPath === file;
              });

              if (isValidDrop) {
                $(this).addClass('drop-zone drag-over');
                e.originalEvent.dataTransfer.dropEffect = 'move';
              } else {
                e.originalEvent.dataTransfer.dropEffect = 'none';
              }
            } else {
              e.originalEvent.dataTransfer.dropEffect = 'none';
            }
          });

          $(document).on('dragleave', '.file-card.is-dir', function (e) {
            $(this).removeClass('drag-over');
          });

          $(document).on('drop', '.file-card.is-dir', function (e) {
            if (!self.isDragging) return;
            e.preventDefault();

            var targetPath = $(this).find('.select-item').attr('data-file');
            if (targetPath && self.draggedFiles.indexOf(targetPath) === -1) {
              // Verificar se é uma movimentação válida
              var isValidDrop = !self.draggedFiles.some(function (file) {
                return targetPath.startsWith(file + '/') || targetPath === file;
              });

              if (isValidDrop) {
                self.moveFiles(self.draggedFiles, targetPath);
              }
            }

            $(this).removeClass('drop-zone drag-over');
          });

          // Zona de drop para voltar à pasta pai (breadcrumb)
          $(document).on('dragover', '#breadcrumb a', function (e) {
            if (!self.isDragging) return;
            e.preventDefault();
            e.originalEvent.dataTransfer.dropEffect = 'move';
            $(this).addClass('drop-zone drag-over');
          });

          $(document).on('dragleave', '#breadcrumb a', function (e) {
            $(this).removeClass('drag-over');
          });

          $(document).on('drop', '#breadcrumb a', function (e) {
            if (!self.isDragging) return;
            e.preventDefault();

            var href = $(this).attr('href');
            if (href && href.startsWith('#')) {
              var targetPath = decodeURIComponent(href.substring(1)) || '.';
              self.moveFiles(self.draggedFiles, targetPath);
            }

            $(this).removeClass('drop-zone drag-over');
          });
        },

        moveFiles: function (files, targetPath) {
          if (!files || !files.length || !targetPath) return;

          // Verificar se algum dos arquivos sendo movidos é uma pasta pai do destino
          var isInvalidMove = files.some(function (file) {
            return targetPath.startsWith(file + '/') || targetPath === file;
          });

          if (isInvalidMove) {
            alert('Não é possível mover uma pasta para dentro de si mesma ou de suas subpastas.');
            return;
          }

          var fileNames = files.map(function (f) { return f.split('/').pop(); }).join(', ');
          var targetName = targetPath === '.' ? 'pasta raiz' : targetPath.split('/').pop();

          if (!confirm('Mover ' + files.length + ' item(s) (' + fileNames + ') para "' + targetName + '"?')) {
            return;
          }

          // Mostrar indicador de carregamento
          var $loading = $('<div class="drag-loading">Movendo arquivos...</div>');
          $('body').append($loading);

          $.post('?', {
            do: 'movecopy',
            files: files,
            dest: targetPath,
            action: 'move',
            xsrf: (document.cookie.match('(^|; )_sfm_xsrf=([^;]*)') || 0)[2]
          }, function (response) {
            $loading.remove();

            if (response && response.success) {
              var successCount = 0;
              var errorCount = 0;
              var errorMessages = [];

              Object.keys(response.results || {}).forEach(function (file) {
                var result = response.results[file];
                if (result.success) {
                  successCount++;
                } else {
                  errorCount++;
                  errorMessages.push(file + ': ' + (result.msg || 'Erro desconhecido'));
                }
              });

              if (successCount > 0) {
                showSuccessNotification(successCount + ' arquivo(s) movido(s) com sucesso!');

                // Atualizar lista e limpar seleções
                setTimeout(function () {
                  $('.select-item').prop('checked', false);
                  $('#selectAll').prop('checked', false);
                  if (typeof updateDeleteSelectedBtn === 'function') {
                    updateDeleteSelectedBtn();
                  }
                  if (typeof list === 'function') {
                    list();
                  }
                }, 500);
              }

              if (errorCount > 0) {
                var errorMsg = 'Alguns arquivos não puderam ser movidos:\n' + errorMessages.join('\n');
                alert(errorMsg);
              }
            } else {
              alert('Erro ao mover arquivos: ' + (response && response.error ? response.error.msg : 'Erro desconhecido'));
            }
          }, 'json').fail(function () {
            $loading.remove();
            alert('Erro na comunicação com o servidor');
          });
        }
      };

      // Inicializar sistema de drag and drop
      dragAndDropMover.init();

      // ADDED: servidor -> JS config snapshot (usado para popular o modal)
      var serverConfig = <?php echo json_encode([
        'allow_delete' => $allow_delete,
        'allow_upload' => $allow_upload,
        'allow_create_folder' => $allow_create_folder,
        'allow_create_file' => $allow_create_file,
        'allow_direct_link' => $allow_direct_link,
        'allow_show_folders' => $allow_show_folders,
        'configTime' => $configTime,
        'max_upload_size_mb' => $max_upload_size_mb,
        'disallowed_patterns' => $disallowed_patterns,
        'hidden_patterns' => $hidden_patterns,
        'SENHA' => $SENHA,
        'permissionAdmin' => $permissionAdmin,
        'effective_upload_limit_mb' => round($MAX_UPLOAD_SIZE / 1024 / 1024, 2),
        'php_post_max_mb' => round($php_post_max / 1024 / 1024, 2),
        'php_upload_max_mb' => round($php_upload_max / 1024 / 1024, 2)
      ]); ?>;

      // Verificar se há limitação do PHP afetando o upload
      if (serverConfig.effective_upload_limit_mb < serverConfig.max_upload_size_mb) {
        console.warn('Aviso: Limite de upload configurado (' + serverConfig.max_upload_size_mb +
          'MB) é maior que as limitações do PHP (' + serverConfig.effective_upload_limit_mb + 'MB)');

        // Mostrar aviso visual se for administrador
        if (serverConfig.permissionAdmin) {
          setTimeout(function () {
            alert('Aviso: O limite de upload está sendo limitado pelas configurações do PHP.\n\n' +
              'Configurado: ' + serverConfig.max_upload_size_mb + 'MB\n' +
              'Efetivo: ' + serverConfig.effective_upload_limit_mb + 'MB\n\n' +
              'Para uploads maiores, ajuste post_max_size e upload_max_filesize no php.ini');
          }, 2000);
        }
      }

      var $tbody = $('#list');
      var $table = $('#table');
      var $fileGrid = $('<div id="fileGrid" class="file-grid-view" style="display:none;"></div>');
      // Adicionar o grid após a tabela dentro do main-content
      $table.after($fileGrid);

      var viewMode = localStorage.getItem('fileViewMode') || 'list';
      function setViewMode(mode) {
        viewMode = mode;
        localStorage.setItem('fileViewMode', mode);
        if (mode === 'grid') {
          $table.hide();
          $fileGrid.show();
          $('#toggleViewText').text('Lista');
          $('#toggleViewBtn i').removeClass('fa-th-large').addClass('fa-list');
        } else {
          $table.show();
          $fileGrid.hide();
          $('#toggleViewText').text('Blocos');
          $('#toggleViewBtn i').removeClass('fa-list').addClass('fa-th-large');
        }
      }
      setViewMode(viewMode);

      $('#toggleViewBtn').on('click', function () {
        setViewMode(viewMode === 'list' ? 'grid' : 'list');
        list();
      });
      $(window).on('hashchange', list).trigger('hashchange');
      $('#table').tablesorter();

      // Função unificada para o Timer de sessão
      function initializeSessionTimer(sessionTimeout) {
        var lastActivity = Date.now();

        function updateSessionTimer() {
          var now = Date.now();
          var elapsed = now - lastActivity;
          var remaining = sessionTimeout - elapsed;
          if (remaining < 0) remaining = 0;
          var totalSeconds = Math.floor(remaining / 1000);
          var min = Math.floor(totalSeconds / 60);
          var sec = totalSeconds % 60;
          var text = 'Tempo restante da sessão: ' + min + 'm ' + (sec < 10 ? '0' : '') + sec + 's';
          $('#session-timer').text(text);
          if (remaining === 0) {
            $('#session-timer').text('Sessão expirada!');
            // Chama logout no backend
            $.get('?do=logout', function () {
              window.location.reload();
            });
          }
        }

        setInterval(updateSessionTimer, 1000);
        $(document).on('mousemove keydown click', function () {
          lastActivity = Date.now();
        });
      }

      // Inicializar o Timer de sessão com o valor vindo do PHP
      var sessionTimeout = <?php echo isset($timeout) ? $timeout : 15000; ?>; // valor em milissegundos
      initializeSessionTimer(sessionTimeout);

      // ========== CONTROLE DO MENU LATERAL ==========

      // Inicializar sidebar baseado no tamanho da tela
      function initializeSidebar() {
        if (window.innerWidth >= 1200) {
          // Desktop: mostrar sidebar sempre
          $('#sidebar').addClass('active');
          $('#mainContent').addClass('sidebar-open');
        } else {
          // Mobile/Tablet: esconder sidebar por padrão
          $('#sidebar').removeClass('active');
          $('#mainContent').removeClass('sidebar-open');
        }
      }

      // Toggle do sidebar
      $('#toggleSidebarBtn').on('click', function () {
        toggleSidebar();
      });

      $('#closeSidebarBtn').on('click', function () {
        closeSidebar();
      });

      // Fechar sidebar ao clicar no overlay
      $('#sidebarOverlay').on('click', function () {
        closeSidebar();
      });

      function toggleSidebar() {
        var $sidebar = $('#sidebar');
        var $overlay = $('#sidebarOverlay');

        if ($sidebar.hasClass('active')) {
          closeSidebar();
        } else {
          openSidebar();
        }
      }

      function openSidebar() {
        var $sidebar = $('#sidebar');
        var $overlay = $('#sidebarOverlay');

        $sidebar.addClass('active');

        // Mostrar overlay apenas em telas pequenas
        if (window.innerWidth < 1200) {
          $overlay.addClass('active');
        }
      }

      function closeSidebar() {
        var $sidebar = $('#sidebar');
        var $overlay = $('#sidebarOverlay');

        // Em desktop (>=1200px), não permitir fechar
        if (window.innerWidth >= 1200) {
          return;
        }

        $sidebar.removeClass('active');
        $overlay.removeClass('active');
      }

      // Reajustar sidebar ao redimensionar janela
      $(window).on('resize', function () {
        initializeSidebar();
      });

      // Inicializar sidebar
      initializeSidebar();

      // Fechar sidebar automaticamente em mobile após clicar em um item do menu
      $('.menu-item').on('click', function () {
        if (window.innerWidth < 1200) {
          setTimeout(function () {
            closeSidebar();
          }, 300);
        }
      });

      // Atualizar timer de sessão no sidebar
      function updateSidebarSessionTimer() {
        var timerText = $('#session-timer').text();
        $('.session-text').text(timerText);
      }

      // Atualizar a cada segundo
      setInterval(updateSidebarSessionTimer, 1000);

      // Atualizar badge da lixeira no sidebar
      function updateSidebarTrashBadge() {
        $.get('?do=listtrash', function (res) {
          if (res && res.success && res.results) {
            var count = res.results.length;
            var $badge = $('#trashCountBadge');
            if (count > 0) {
              $badge.text(count).show();
            } else {
              $badge.hide();
            }
          }
        }, 'json');
      }

      // Atualizar badge da lixeira na inicialização
      updateSidebarTrashBadge();

      // Atualizar indicador do player no sidebar
      function updateSidebarPlayerStatus() {
        if (typeof FloatingAudioPlayer !== 'undefined' && FloatingAudioPlayer.isPlaying) {
          $('#toggleAudioPlayerBtn').addClass('playing');
          $('#audioPlayerStatusText').text('Tocando música');
        } else {
          $('#toggleAudioPlayerBtn').removeClass('playing');
          $('#audioPlayerStatusText').text('Reprodutor musical');
        }
      }

      // Atualizar status do player periodicamente
      setInterval(updateSidebarPlayerStatus, 1000);

      // Handler de logout 
      $('#logoutBtn').on('click', function () {
        if (confirm('Tem certeza que deseja sair?')) {
          window.location.href = '?do=logout';
        }
      });

      // Handler de exclusão movido para delegateFileActions para evitar duplicação

      $('#mkdir').submit(function (e) {
        var hashval = decodeURIComponent(window.location.hash.substr(1)),
          $dir = $(this).find('[name=dirname]');
        e.preventDefault();
        $dir.val().length && $.post('?', { 'do': 'mkdir', name: $dir.val(), xsrf: XSRF, file: hashval }, function (data) {
          list();
        }, 'json');
        $dir.val('');
        return false;
      });
      <?php if ($allow_upload): ?>
        // file upload stuff
        $('#file_drop_target').on('dragover', function () {
          $(this).addClass('drag_over');
          return false;
        }).on('dragend', function () {
          $(this).removeClass('drag_over');
          return false;
        }).on('drop', function (e) {
          e.preventDefault();
          var files = e.originalEvent.dataTransfer.files;
          $.each(files, function (k, file) {
            uploadFile(file, false);
          });
          $(this).removeClass('drag_over');
        });
        $('input[type=file]').change(function (e) {
          e.preventDefault();
          $.each(this.files, function (k, file) {
            uploadFile(file, false);
          });
        });

        function updateDeleteSelectedBtn() {
          var anyChecked = $('.select-item:checked').length > 0;
          $('#deleteSelectedBtn').prop('disabled', !anyChecked);
          $('#zipSelectedBtn').prop('disabled', !anyChecked);
          $('#moveCopyBtn').prop('disabled', !anyChecked);

          // Para o botão de conversão, verificar se há imagens selecionadas
          var imageExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
          var hasImages = $('.select-item:checked').filter(function () {
            var file = $(this).attr('data-file');
            var ext = file.split('.').pop().toLowerCase();
            return imageExts.includes(ext);
          }).length > 0;
          $('#convertImagesBtn').prop('disabled', !hasImages);

          // Atualizar visual dos botões no sidebar
          if (anyChecked) {
            $('.menu-item:disabled').removeClass('menu-item-disabled');
          } else {
            $('#deleteSelectedBtn, #zipSelectedBtn, #moveCopyBtn, #convertImagesBtn').addClass('menu-item-disabled');
          }
        }

        // Atualiza o botão ao clicar em qualquer checkbox (tabela ou grid)
        $('#table').on('change', '.select-item', function () {
          updateDeleteSelectedBtn();
        });
        $('#fileGrid').on('change', '.select-item', function () {
          updateDeleteSelectedBtn();
        });

        // Ao clicar em "Excluir selecionados"
        $('#deleteSelectedBtn').click(function () {
          if (!confirm('Tem certeza que deseja excluir os arquivos/pastas selecionados?')) return;
          // Confirmação do modal de exclusão
          $('#confirmDeleteBtn').click(function () {
            var file = $('#confirmDeleteModal').data('file');
            if (file) {
              $.post("", { 'do': 'delete', file: file, xsrf: XSRF }, function (response) {
                list();
              }, 'json');
            }
            $('#confirmDeleteModal').modal('hide');
          });

          var filesToDelete = $('.select-item:checked').map(function () {
            return $(this).attr('data-file');
          }).get();

          // Envia exclusão para cada arquivo/pasta
          filesToDelete.forEach(function (file) {
            $.post("", { 'do': 'delete', file: file, xsrf: XSRF }, function () {
              // Opcional: pode esperar todos retornarem antes de atualizar a lista
            }, 'json');
          });

          // Após exclusão, atualiza a lista e desabilita o botão
          setTimeout(function () {
            list();
            $('#deleteSelectedBtn').prop('disabled', true);
            $('#selectAll').prop('checked', false);
          }, 500);
        });

        // ADDED: criar ZIP dos selecionados com barra de progresso
        // Modal de configuração de ZIP
        if ($('#zipConfigModal').length === 0) {
          $('body').append(`
            <div class="modal fade" id="zipConfigModal" tabindex="-1" aria-labelledby="zipConfigModalLabel" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="zipConfigModalLabel">Configurações do ZIP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body">
                    <div class="mb-3">
                      <label for="zipFileNameZip" class="form-label">Nome do arquivo ZIP:</label>
                      <input type="text" class="form-control" id="zipFileNameZip" placeholder="arquivo.zip">
                      <div class="form-text text-warning">
                        <small>Nome do arquivo ZIP a ser criado. Se não especificado, será gerado automaticamente.</small>
                      </div>
                    </div>
                    <div class="mb-3">
                      <label for="zipCompressionLevel" class="form-label">Nível de compressão:</label>
                      <select class="form-select" id="zipCompressionLevel">
                        <option value="0">Sem compressão (mais rápido)</option>
                        <option value="1">Compressão mínima</option>
                        <option value="3">Compressão baixa</option>
                        <option value="6" selected>Compressão padrão (recomendado)</option>
                        <option value="9">Compressão máxima (mais lento)</option>
                      </select>
                      <div class="form-text text-warning">
                        <small>Níveis mais altos reduzem o tamanho do arquivo, mas demoram mais para processar.</small>
                      </div>
                    </div>
                    <div class="mb-3">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="zipPreservePaths" checked>
                        <label class="form-check-label" for="zipPreservePaths">
                          Preservar estrutura de pastas
                        </label>
                      </div>
                    </div>
                    <div id="zipSelectedFilesList" class="mb-3">
                      <small class="text-muted">Arquivos selecionados aparecerão aqui...</small>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="startZipProcess">Criar ZIP</button>
                  </div>
                </div>
              </div>
            </div>
          `);
        }

        // Modal de progresso
        if ($('#zipProgressModal').length === 0) {
          $('body').append(`
            <div class="modal fade" id="zipProgressModal" tabindex="-1" aria-labelledby="zipProgressModalLabel" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="zipProgressModalLabel">Progresso da Compressão ZIP</h5>
                  </div>
                  <div class="modal-body">
                    <div class="progress mb-2">
                      <div id="zipProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">0%</div>
                    </div>
                    <div id="zipProgressStatus">Compressão em andamento...</div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                  </div>
                </div>
              </div>
            </div>
          `);
        }

        $('#zipSelectedBtn').click(function () {
          var filesToZip = $('.select-item:checked').map(function () {
            return $(this).attr('data-file');
          }).get();
          if (!filesToZip.length) return alert('Nenhum arquivo selecionado.');

          // Preencher modal de configuração
          // Se já existe valor, mantém; senão, usa o nome padrão
          $('#zipCompressionLevel').val('6'); // Padrão
          $('#zipPreservePaths').prop('checked', true);

          // Mostrar lista de arquivos selecionados
          var filesList = '<strong>Arquivos selecionados (' + filesToZip.length + '):</strong><br>';
          filesToZip.forEach(function (file) {
            filesList += '<small class="text-muted">• ' + file + '</small><br>';
          });
          $('#zipSelectedFilesList').html(filesList);

          // Mostrar modal de configuração
          var configModal = new bootstrap.Modal(document.getElementById('zipConfigModal'));
          configModal.show();
        });

        // Handler para iniciar o processo de ZIP
        $(document).on('click', '#startZipProcess', function () {
          var filesToZip = $('.select-item:checked').map(function () {
            return $(this).attr('data-file');
          }).get();

          var zipName = $('#zipFileNameZip').val().trim();
          console.log('Nome do ZIP fornecido:', zipName); // Debug
          if (!zipName) {
            // Se não digitou nome, usar o nome padrão gerado
            var now = new Date();
            var dia = String(now.getDate()).padStart(2, '0');
            var mes = String(now.getMonth() + 1).padStart(2, '0');
            var ano = now.getFullYear();
            var hora = String(now.getHours()).padStart(2, '0');
            var minuto = String(now.getMinutes()).padStart(2, '0');
            zipName = 'Zip_' + dia + '_' + mes + '_' + ano + '_' + hora + '_' + minuto + '.zip';
          }
          if (!zipName.toLowerCase().endsWith('.zip')) {
            zipName = zipName + '.zip';
          }

          console.log('Nome do ZIP a ser enviado:', zipName); // Debug

          var compressionLevel = $('#zipCompressionLevel').val();
          var preservePaths = $('#zipPreservePaths').prop('checked');
          var folder = decodeURIComponent(window.location.hash.substr(1));

          // Debug: Verificar dados que serão enviados
          console.log('Dados do POST:', {
            do: 'zip',
            files: filesToZip,
            folder: folder,
            name: zipName,
            compression_level: compressionLevel,
            preserve_paths: preservePaths ? '1' : '0'
          });

          // Fechar modal de configuração
          bootstrap.Modal.getInstance(document.getElementById('zipConfigModal')).hide();

          // Mostra modal de progresso
          var zipModal = new bootstrap.Modal(document.getElementById('zipProgressModal'));
          $('#zipProgressBar').css('width', '0%').text('0%');
          $('#zipProgressStatus').text('Compressão em andamento...');
          zipModal.show();

          // Inicia compressão com nível de compressão
          $.post('?', {
            do: 'zip',
            files: filesToZip,
            folder: folder,
            name: zipName,
            compression_level: compressionLevel,
            preserve_paths: preservePaths ? '1' : '0',
            xsrf: XSRF
          }, function (res) {
            // Inicia polling do progresso
            var pollInterval = setInterval(function () {
              $.get('?', { do: 'zipprogress' }, function (progressRes) {
                if (progressRes && progressRes.success && progressRes.progress) {
                  var prog = progressRes.progress;
                  var total = prog.total || 1;
                  var current = prog.current || 0;
                  var percent = Math.round((current / total) * 100);
                  if (percent > 100) percent = 100;
                  $('#zipProgressBar').css('width', percent + '%').text(percent + '%');
                  $('#zipProgressStatus').text(prog.status === 'finalizado' ? 'Compressão finalizada!' : (prog.status || 'Compressão em andamento...'));
                  if (prog.status === 'finalizado' || prog.status === 'erro') {
                    clearInterval(pollInterval);
                    setTimeout(function () {
                      zipModal.hide();
                      if (prog.status === 'finalizado') {
                        alert('ZIP criado: ' + prog.zip);
                        list();
                        $('#selectAll').prop('checked', false);
                        $('.select-item').prop('checked', false);
                        updateDeleteSelectedBtn();
                      } else {
                        alert('Erro ao criar ZIP: ' + (prog.msg || 'Erro desconhecido'));
                      }
                    }, 800);
                  }
                }
              }, 'json');
            }, 500);
          }, 'json').fail(function () {
            $('#zipProgressStatus').text('Falha ao criar ZIP.');
            setTimeout(function () { zipModal.hide(); }, 1200);
          });
        });

        function uploadFile(file) {
          var folder = decodeURIComponent(window.location.hash.substr(1));

          if (file.size > MAX_UPLOAD_SIZE) {
            var $error_row = renderFileSizeErrorRow(file, folder);
            $('#upload_progress').append($error_row);
            window.setTimeout(function () { $error_row.fadeOut(); }, 5000);
            return false;
          }

          var $row = renderFileUploadRow(file, folder);
          var $progressText = $('<span class="progress-text"></span>');
          $row.append($progressText);
          $('#upload_progress').append($row);

          var fd = new FormData();
          fd.append('file_data', file);
          fd.append('file', folder);
          fd.append('xsrf', XSRF);
          fd.append('do', 'upload');
          if (arguments.length > 1 && arguments[1] === true) fd.append('overwrite', '1');

          var xhr = new XMLHttpRequest();
          xhr.open('POST', '?');
          xhr.onload = function () {
            if (xhr.status === 200) {
              $row.remove();
              list();
            } else {
              try {
                var res = JSON.parse(xhr.responseText);
                if (res && res.error && res.error.code === 409) {
                  if (confirm('Já existe um arquivo com este nome. Deseja substituir?')) {
                    uploadFile(file, true);
                    $row.remove();
                    return;
                  } else {
                    $row.remove();
                    return;
                  }
                } else {
                  alert('Erro ao enviar arquivo: ' + (res && res.error ? res.error.msg : 'Erro desconhecido.'));
                }
              } catch (e) {
                alert('Erro ao enviar arquivo.');
              }
              $row.remove();
            }
          };

          xhr.upload.onprogress = function (e) {
            if (e.lengthComputable) {
              var percent = (e.loaded / e.total * 100 | 0) + '%';
              $row.find('.progress').css('width', percent);
              $progressText.text('(' + percent + ' completo)'); // Atualiza o texto do progresso
            }
          };
          xhr.send(fd);
        }
        function renderFileUploadRow(file, folder) {
          return $row = $('<div/>')
            .append($('<span class="progress_fileuploadname" />').text((folder ? folder + '/' : '') + file.name))
            .append($('<div class="progress_track"><div class="progress"></div></div>'))
            .append($('<span class="progress_size" />').text(formatFileSize(file.size)))
        };
        function renderFileSizeErrorRow(file, folder) {
          var errorMsg = ' file size - <b>' + formatFileSize(file.size) + '</b>' +
            ' excede o tamanho máximo de upload de <b>' + formatFileSize(MAX_UPLOAD_SIZE) + '</b>';

          // Adicionar informações sobre limitações do PHP se necessário
          if (serverConfig.effective_upload_limit_mb < serverConfig.max_upload_size_mb) {
            errorMsg += '<br><small>Limitado pelo PHP (post_max_size: ' + serverConfig.php_post_max_mb +
              'MB, upload_max_filesize: ' + serverConfig.php_upload_max_mb + 'MB)</small>';
          }

          return $row = $('<div class="error" />')
            .append($('<span class="fileuploadname" />').text('Error: ' + (folder ? folder + '/' : '') + file.name))
            .append($('<span/>').css('color', '#ff0000').html(errorMsg));
        }
      <?php endif; ?>
      function list() {
        var hashval = window.location.hash.substr(1);
        $.get('?do=list&file=' + hashval, function (data) {
          $tbody.empty();
          $fileGrid.empty();
          $('#breadcrumb').empty().html(renderBreadcrumbs(hashval));

          console.log('Current view mode:', viewMode);
          console.log('Data received:', data);

          if (data.success) {
            if (viewMode === 'grid') {
              console.log('Rendering grid view with', data.results.length, 'items');
              $.each(data.results, function (k, v) {
                var card = renderFileCard(v);
                $fileGrid.append(card);
                console.log('Added card for:', v.name);
              });
              if (!data.results.length) $fileGrid.append('<div class="empty w-100 text-center text-white">Esta pasta está vazia</div>');
            } else {
              console.log('Rendering list view with', data.results.length, 'items');
              $.each(data.results, function (k, v) {
                $tbody.append(renderFileRow(v));
              });
              !data.results.length && $tbody.append('<tr><td class="empty" colspan=5>Esta pasta está vazia</td></tr>');
            }
            data.is_writable ? $('body').removeClass('no_write') : $('body').addClass('no_write');
          } else {
            console.warn(data.error.msg);
          }
          $('#table').retablesort();

          // Atualizar botões do player após carregar a lista
          setTimeout(function () {
            if (typeof updateNavbarPlayerButton === 'function') {
              updateNavbarPlayerButton();
            }
          }, 100);
        }, 'json');
      }
      // Renderização de arquivos/pastas em cards (grid)
      function renderFileCard(data) {
        var $card = $('<div class="file-card draggable-item"></div>');
        $card.attr('draggable', 'true');
        if (data.is_dir) {
          $card.addClass('is-dir');
        }
        var cbId = 'cb_grid_' + data.path.replace(/[^a-zA-Z0-9]/g, '');
        var $checkbox = $('<div class="form-check d-inline-block me-2"><input type="checkbox" class="form-check-input select-item" data-file="' + data.path + '" id="' + cbId + '"><label class="form-check-label" for="' + cbId + '"></label></div>');
        var nameClass = data.is_dir ? 'name name-dir' : 'name name-arc';
        var $link = $('<a />')
          .addClass(nameClass)
          .attr('href', data.is_dir ? '#' + encodeURIComponent(data.path) : './' + data.path)
          .html($('<div/>').text(data.name).html());
        var $title = $('<div class="file-card-title"></div>')
          .append($checkbox)
          .append($link);
        $card.append($title);

        var $meta = $('<div class="file-card-meta d-flex flex-wrap gap-2 align-items-center w-100"></div>')
          .append($('<span class="badge bg-success px-2 py-1 rounded-pill me-1 w-100"></span>').text(data.is_dir ? 'Pasta' : 'Arquivo'))
          .append($('<span class="badge bg-danger text-white px-2 py-1 rounded-pill me-1 w-100"></span>')
            .attr('data-folder', data.path)
            .html(data.is_dir ? '--' : formatFileSize(data.size))
          )
          .append($('<span class="badge bg-info text-dark px-2 py-1 rounded-pill w-100"></span>').text(formatTimestamp(data.mtime)));
        $card.append($meta);

        // Footer de ações
        var $footer = $('<div class="file-card-footer mt-auto w-100"></div>');
        var $actions = $('<div class="file-card-actions"></div>');

        // Visualizar/Editar/Outros (imagem, áudio, vídeo, pdf, texto)
        var ext = data.name.split('.').pop().toLowerCase();
        var imageExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff', 'tif', 'svg', 'avif'];
        var audioExts = ['mp3', 'wav', 'ogg', 'm4a'];
        var videoExts = ['mp4', 'webm', 'ogg', 'mkv'];
        var pdfExts = ['pdf'];
        var textExts = ['ssc', 'txt', 'md', 'csv', 'html', 'htm', 'js', 'css', 'json', 'log', 'ini', 'xml', 'yaml', 'yml'];

        if (!data.is_dir && imageExts.includes(ext)) {
          $actions.append(
            $('<a href="#" class="btn btn-outline-info btn-alt-config btn-sm view-image" title="Visualizar imagem"><i class="fa fa-eye"></i></a>')
              .attr('data-file', data.path)
          );
        }
        if (!data.is_dir && audioExts.includes(ext)) {
          $actions.append(
            $('<a href="#" class="btn btn-outline-info btn-alt-config btn-sm view-audio" title="Ouvir áudio"><i class="fa fa-headphones"></i></a>')
              .attr('data-file', data.path)
          );
        }
        if (!data.is_dir && videoExts.includes(ext)) {
          $actions.append(
            $('<a href="#" class="btn btn-outline-info btn-alt-config btn-sm view-video" title="Ver vídeo"><i class="fa fa-video-camera"></i></a>')
              .attr('data-file', data.path)
          );
        }
        if (!data.is_dir && pdfExts.includes(ext)) {
          $actions.append(
            $('<a href="#" class="btn btn-outline-info btn-alt-config btn-sm view-pdf" title="Ver PDF"><i class="fa fa-file-pdf-o"></i></a>')
              .attr('data-file', data.path)
          );
        }
        if (!data.is_dir && textExts.includes(ext) && data.is_writable) {
          $actions.append(
            $('<a href="#" class="btn btn-outline-info btn-alt-config btn-sm edit-text" title="Editar texto"><i class="fa fa-edit"></i></a>')
              .attr('data-file', data.path)
          );
        }

        // ADDED: botão para visualizar ZIP no modo grid
        if (!data.is_dir && ext === 'zip') {
          $actions.append(
            $('<a href="#" class="btn btn-outline-warning btn-alt-config btn-sm view-zip" title="Ver conteúdo do ZIP"><i class="fa fa-file-archive-o"></i></a>')
              .attr('data-file', data.path)
          );
        }

        // Compartilhar
        if (!data.is_dir) {
          $actions.append(
            $('<a href="#" class="btn btn-outline-warning btn-alt-config btn-sm share-file" title="Compartilhar"><i class="fa fa-share-alt"></i></a>')
              .attr('data-file', data.path)
          );
        }

        // Download
        if (!data.is_dir) {
          $actions.append(
            $('<a class="btn btn-outline-primary btn-alt-config btn-sm" title="Download"><i class="fa fa-download"></i></a>')
              .attr('href', '?do=download&file=' + encodeURIComponent(data.path))
          );
        }
        // Renomear
        if (data.is_writable || data.is_deleteable) {
          $actions.append(
            $('<a href="#" class="btn btn-outline-success btn-alt-config btn-sm rename-btn" title="Renomear"><i class="fa fa-pencil"></i></a>')
              .attr('data-file', data.path)
              .attr('data-is-dir', data.is_dir ? '1' : '0')
          );
        }
        // Excluir
        if (data.is_deleteable) {
          $actions.append(
            $('<a href="#" class="btn btn-outline-danger btn-alt-config btn-sm delete" title="Excluir"><i class="fa fa-trash"></i></a>')
              .attr('data-file', data.path)
          );
        }
        // informações sobre pastas
        if (data.is_dir) {
          $actions.append(
            $('<a href="#" class="btn btn-sm btn-outline-info btn-alt-config folder-info-btn" title="Informações"><i class="fa fa-info-circle"></i></a>')
              .attr('data-folder', data.path)
          );
        }
        $footer.append($actions);
        $card.append($footer);
        return $card;
      }
      function renderFileRow(data) {
        var iconHtml = data.is_dir ? '<i class="fa fa-folder fa-lg text-warning"></i>' : '<i class="fa fa-file-o fa-lg text-secondary"></i>';
        var $checkbox = $('<input type="checkbox" class="form-check-input select-item me-2">').attr('data-file', data.path);
        var nameClass = data.is_dir ? 'name name-dir' : 'name name-arc';
        var iconHtml = data.is_dir
          ? '<span class="file-card-icon d-flex align-items-center justify-content-center me-2" style="width:28px;height:28px;"><i class="fa fa-folder fa-lg text-warning"></i></span>'
          : '<span class="file-card-icon d-flex align-items-center justify-content-center me-2" style="width:28px;height:28px;"><i class="fa fa-file-o fa-lg text-secondary"></i></span>';
        var $link = $('<a />')
          .addClass(nameClass)
          .attr('href', data.is_dir ? '#' + encodeURIComponent(data.path) : './' + data.path)
          .html(iconHtml + $('<div/>').text(data.name).html());

        // botão de renomear (ícone lápis) ao lado do nome
        // var $rename_btn = null;
        // if (data.is_writable || data.is_deleteable) {
        //   $rename_btn = $('<a href="#" class="rename-btn btn btn-outline-success btn-sm ms-2" title="Renomear"><i class="fa fa-pencil"></i></a>')
        //     .attr('data-file', data.path)
        //     .attr('data-is-dir', data.is_dir ? '1' : '0');
        // }

        var allow_direct_link = <?php echo $allow_direct_link ? 'true' : 'false'; ?>;
        if (!data.is_dir && !allow_direct_link) $link.css('pointer-events', 'none');

        var $share_link = null;
        if (!data.is_dir) {
          $share_link = $('<a href="#" class="btn btn-outline-warning btn-md me-2" title="Compartilhar"><i class="fa fa-share-alt"></i></a>').attr('data-file', data.path).addClass('share-file');
        }
        var $dl_link = $('<a class="btn btn-outline-primary btn-md me-2"><i class="fa fa-download"></i></a>').attr('href', '?do=download&file=' + encodeURIComponent(data.path)).addClass('download');
        var $delete_link = $('<a href="#" class="btn btn-outline-danger btn-md me-2"><i class="fa fa-trash"></i></a>').attr('data-file', data.path).addClass('delete');
        var imageExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
        var audioExts = ['mp3', 'wav', 'ogg', 'm4a'];
        var videoExts = ['mp4', 'webm', 'ogg', 'mkv'];
        var pdfExts = ['pdf'];
        var textExts = ['ssc', 'txt', 'md', 'csv', 'html', 'htm', 'js', 'css', 'json', 'log', 'ini', 'xml', 'yaml', 'yml'];
        // ADDED: extensões ZIP
        var zipExts = ['zip', 'rar', '7z'];
        var ext = data.name.split('.').pop().toLowerCase();

        var $view_link = null;
        if (!data.is_dir && imageExts.includes(ext)) {
          $view_link = $('<a href="#" class="btn btn-outline-info btn-md me-2"><i class="fa fa-eye" aria-hidden="true"></i></a>').attr('data-file', data.path).addClass('view-image');
        }
        var $audio_link = null;
        if (!data.is_dir && audioExts.includes(ext)) {
          $audio_link = $('<a href="#" class="btn btn-outline-info btn-md me-2"><i class="fa fa-headphones" aria-hidden="true"></i></a>').attr('data-file', data.path).addClass('view-audio');
        }
        var $video_link = null;
        if (!data.is_dir && videoExts.includes(ext)) {
          $video_link = $('<a href="#" class="btn btn-outline-info btn-md me-2"><i class="fa fa-video-camera" aria-hidden="true"></i></a>').attr('data-file', data.path).addClass('view-video');
        }
        var $pdf_link = null;
        if (!data.is_dir && pdfExts.includes(ext)) {
          $pdf_link = $('<a href="#" class="btn btn-outline-info btn-md me-2"><i class="fa fa-file-pdf-o" aria-hidden="true"></i></a>').attr('data-file', data.path).addClass('view-pdf');
        }
        var $edit_link = null;
        if (!data.is_dir && textExts.includes(ext) && data.is_writable) {
          $edit_link = $('<a href="#" class="btn btn-outline-info btn-md me-2"><i class="fa fa-edit"></i></a>').attr('data-file', data.path).addClass('edit-text');
        }
        var $rename_btn = null;
        if (data.is_writable || data.is_deleteable) {
          $rename_btn = $('<a href="#" class="btn btn-outline-success btn-md me-2" title="Renomear"><i class="fa fa-pencil"></i></a>')
            .attr('data-file', data.path)
            .attr('data-is-dir', data.is_dir ? '1' : '0')
            .addClass('rename-btn');
        }

        // ADDED: botão para visualizar ZIP
        var $zip_link = null;
        if (!data.is_dir && ext === 'zip') {
          $zip_link = $('<a href="#" class="btn btn-outline-warning btn-md me-2" title="Ver conteúdo do ZIP"><i class="fa fa-file-archive-o"></i></a>').attr('data-file', data.path).addClass('view-zip');
        }

        var $info_btn = null;
        if (data.is_dir) {
          $info_btn = $('<a href="#" class="btn btn-sm btn-outline-info btn-md me-2" title="Informações"><i class="fa fa-info-circle"></i></a>')
            .attr('data-folder', data.path);
        }

        var perms = [];
        if (data.is_readable) perms.push('Visualizar ');
        if (data.is_writable) perms.push(' Editar ');
        if (data.is_executable) perms.push(' Executar');

        var $html = $('<tr />')
          .addClass(data.is_dir ? 'is_dir' : '')
          .addClass('draggable-item')
          .attr('draggable', 'true')
          .append(
            $('<td class="first d-flex align-items-center" />').append($checkbox).append($link)
          )
          .append($('<td/>').attr('data-sort', data.is_dir ? -1 : data.size)
            .html(data.is_dir ? '--' : formatFileSize(data.size, data.is_dir, data.path)))
          .append($('<td/>').attr('data-sort', data.mtime).text(formatTimestamp(data.mtime)))
          .append($('<td/>').text(perms.join('~')))
          .append($('<td class="text-center" />')
            .append($rename_btn ? $rename_btn : '')
            .append($view_link ? $view_link : '')
            .append($audio_link ? $audio_link : '')
            .append($video_link ? $video_link : '')
            .append($pdf_link ? $pdf_link : '')
            .append($zip_link ? $zip_link : '') // ADDED: botão ZIP
            .append(data.is_dir ? ($info_btn ? $info_btn : '') : '')
            .append($edit_link ? $edit_link : '')
            .append(data.is_dir ? '' : $dl_link)
            .append(data.is_deleteable ? $delete_link : '')
          );

        return $html;
      }
      function renderBreadcrumbs(path) {
        var base = "",
          $html = $('<div/>').append($('<a href=#><i class="fa fa-home xlarge"></i></a></div>'));
        $.each(path.split('%2F'), function (k, v) {
          if (v) {
            var v_as_text = decodeURIComponent(v);
            $html.append($('<span/>').text(' ⤑ '))
              .append($('<a/>').attr('href', '#' + base + v).text(v_as_text));
            base += v + '%2F';
          }
        });
        return $html;
      }
      function formatTimestamp(unix_timestamp) {
        var m = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'];
        var d = new Date(unix_timestamp * 1000);
        return [(d.getDate() < 10 ? '0' : '') + d.getDate(), '/', m[d.getMonth()], '/', d.getFullYear(), " ",
        (d.getHours() % 12 || 12), ":", (d.getMinutes() < 10 ? '0' : '') + d.getMinutes(),
          " ", d.getHours() >= 12 ? 'PM' : 'AM'].join('');
      }

      // ADDED: função navigate se não existir
      function navigate(path) {
        if (path === '') {
          window.location.hash = '';
        } else {
          window.location.hash = encodeURIComponent(path);
        }
        if (typeof list === 'function') {
          list();
        }
      }

      // Formata tamanho para arquivos e pastas
      function formatFileSize(bytes, isDir, path) {
        var s = ['bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];
        var pos = 0;
        var origBytes = bytes;
        for (; bytes >= 1000; pos++, bytes /= 1024);
        var d = Math.round(bytes * 10);
        var str = pos ? [parseInt(d / 10), ".", d % 10, " ", s[pos]].join('') : origBytes + ' bytes';

        // Se for pasta, busca info detalhada via AJAX (folderinfo)
        if (isDir && path) {
          // Adiciona spinner enquanto carrega
          str = '<span class="spinner-border spinner-border-sm text-success" role="status"></span> ...';
          $.get('?do=folderinfo&folder=' + encodeURIComponent(path), function (resp) {
            if (resp && resp.success) {
              var info = resp.size + ' (' + resp.files + ' arquivos, ' + resp.dirs + ' pastas)';
              // Atualiza badge na grid
              $('[data-folder="' + path + '"]').closest('.file-card').find('.badge.bg-danger').html(info);
              // Atualiza na tabela
              $('#list tr').each(function () {
                var $tr = $(this);
                var $cb = $tr.find('.select-item');
                if ($cb.length && $cb.attr('data-file') === path) {
                  $tr.find('td').eq(1).html(info);
                }
              });
            }
          }, 'json');
        }
        return str;
      }

      $('#selectAll').on('change', function () {
        var checked = $(this).prop('checked');
        $('.select-item').prop('checked', checked);
        updateDeleteSelectedBtn();
      });

      // Atualizar selectAll se todas forem selecionadas manualmente
      $('#table').on('change', '.select-item', function () {
        var allChecked = $('.select-item').length === $('.select-item:checked').length;
        $('#selectAll').prop('checked', allChecked);
        updateDeleteSelectedBtn();
      });


      // Delegar eventos para botões tanto na tabela quanto no grid de blocos
      function delegateFileActions(container) {
        // Visualizar imagem com galeria
        container.on('click', '.view-image', function (e) {
          e.preventDefault();
          var file = $(this).attr('data-file');
          openImageGallery(file);
        });
        // ADDED: Visualizar ZIP
        container.on('click', '.view-zip', function (e) {
          e.preventDefault();
          var file = $(this).attr('data-file');
          openZipViewer(file);
        });
        // Editar texto com CodeMirror
        container.on('click', '.edit-text', function (e) {
          e.preventDefault();
          var file = $(this).attr('data-file');
          if (!file) return;

          currentEditingFile = file;
          $('#textEditModal').data('file', file);
          $('#currentFileName').text(file);
          $('#textEditStatus').text('Carregando arquivo...');

          // Detectar modo baseado na extensão do arquivo
          var mode = getModeFromFilename(file);
          $('#languageSelect').val(mode);

          // Inicializar CodeMirror se ainda não foi inicializado
          if (!codeMirrorEditor) {
            initCodeMirror();
          }

          // Mostrar modal
          $('#textEditModal').css('display', 'flex').hide().fadeIn(150);

          // Carregar conteúdo do arquivo
          $.get('?do=getfile&file=' + encodeURIComponent(file), function (res) {
            if (res && res.success) {
              codeMirrorEditor.setValue(res.content);
              codeMirrorEditor.setOption('mode', mode);

              // Configurações específicas para HTML
              if (mode === 'htmlmixed') {
                codeMirrorEditor.setOption('autoCloseTags', true);
                codeMirrorEditor.setOption('matchTags', { bothTags: true });
                codeMirrorEditor.setOption('extraKeys', {
                  'Ctrl-Space': 'autocomplete',
                  'F11': function (cm) {
                    cm.setOption('fullScreen', !cm.getOption('fullScreen'));
                  },
                  'Esc': function (cm) {
                    if (cm.getOption('fullScreen')) cm.setOption('fullScreen', false);
                  }
                });
                codeMirrorEditor.setOption('hintOptions', {
                  completeSingle: false,
                  hint: CodeMirror.hint.html
                });
              }

              codeMirrorEditor.markClean();
              codeMirrorEditor.focus();

              // Atualizar nome do arquivo na aba
              var fileName = file.split('/').pop();
              $('#tabFileName').text(fileName);

              $('#textEditStatus').text('Arquivo carregado').removeClass('text-danger').addClass('text-success');
              setTimeout(function () {
                $('#textEditStatus').text('').removeClass('text-success');
              }, 2000);
              updateEditorInfo();
            } else {
              $('#textEditStatus').text('Erro ao carregar arquivo').removeClass('text-success').addClass('text-danger');
              alert('Erro ao carregar arquivo: ' + (res && res.error ? res.error.msg : 'unknown'));
            }
          }, 'json').fail(function () {
            $('#textEditStatus').text('Falha na requisição').removeClass('text-success').addClass('text-danger');
            alert('Falha ao buscar o arquivo.');
          });
        });
        // Visualizar PDF
        container.on('click', '.view-pdf', function (e) {
          e.preventDefault();
          var file = $(this).attr('data-file');
          if (!file) return;
          var src = './' + file;
          $('#pdfViewModal').css('display', 'flex').hide().fadeIn(150);
          $('#pdfViewModalIframe').attr('src', src);
        });
        // Áudio - usar o player flutuante melhorado
        container.on('click', '.view-audio', function (e) {
          e.preventDefault();
          var file = $(this).attr('data-file');
          if (!file) return;
          var src = './' + file;

          // Adicionar à playlist e reproduzir
          FloatingAudioPlayer.addToPlaylist(file, src);
          FloatingAudioPlayer.playTrack(file);

          // Mostrar player se não estiver visível
          if (!$('#floatingAudioPlayer').is(':visible')) {
            FloatingAudioPlayer.show();
          } else if (FloatingAudioPlayer.isMinimized) {
            // Se estiver minimizado, expandir temporariamente
            FloatingAudioPlayer.toggleMinimize();
            // Minimizar novamente após 3 segundos
            setTimeout(function () {
              if ($('#floatingAudioPlayer').is(':visible') && !FloatingAudioPlayer.isMinimized) {
                FloatingAudioPlayer.toggleMinimize();
              }
            }, 3000);
          }
        });
        // Vídeo
        container.on('click', '.view-video', function (e) {
          e.preventDefault();
          var file = $(this).attr('data-file');
          if (!file) return;
          var src = './' + file;
          $('#mediaModal').css('display', 'flex').hide().fadeIn(150);
          $('#mediaTitle').text(file);
          $('#mediaPlayerAudio').hide().attr('src', '');
          $('#mediaPlayerVideo').attr('src', src).show()[0].play();
        });
        // Renomear
        container.on('click', '.rename-btn', function (e) {
          e.preventDefault();
          var $btn = $(this);
          var oldPath = $btn.attr('data-file');
          if (!oldPath) return alert('Caminho inválido.');
          var isDir = $btn.attr('data-is-dir') === '1';

          var parts = oldPath.split('/');
          var currentName = parts.pop();
          var currentExt = '';
          var dotPos = currentName.lastIndexOf('.');
          if (!isDir && dotPos > 0) {
            currentExt = currentName.substring(dotPos + 1);
          }

          var newName = prompt('Novo nome para: ' + currentName, currentName);
          if (newName === null) return; // cancelou
          newName = String(newName).trim();
          if (!newName) return alert('Nome inválido.');
          if (newName.indexOf('/') !== -1 || newName.indexOf('\\') !== -1 || newName.indexOf('..') !== -1)
            return alert('Nome inválido: não é permitido barras ou "..".');
          // Se for arquivo com extensão original, não permitir alterar extensão.
          if (!isDir && currentExt) {
            var newDot = newName.lastIndexOf('.');
            if (newDot > 0) {
              var newExt = newName.substring(newDot + 1);
              if (newExt.toLowerCase() !== currentExt.toLowerCase()) {
                return alert('Alterar a extensão do arquivo não é permitido.');
              }
            } else {
              // se usuário não informou extensão, anexa a original automaticamente
              newName = newName + '.' + currentExt;
            }
          }
          // enviar pedido de renomear ao servidor
          $.post('?', { do: 'rename', file: oldPath, newname: newName, xsrf: XSRF }, function (res) {
            if (res && res.success) {
              list();
            } else {
              alert('Erro ao renomear: ' + (res && res.error ? res.error.msg : 'unknown'));
            }
          }, 'json').fail(function (xhr) {
            var msg = 'Falha na requisição.';
            try { var json = JSON.parse(xhr.responseText); if (json && json.error && json.error.msg) msg = json.error.msg; } catch (e) { }
            alert('Erro ao renomear: ' + msg);
          });
        });
        // Excluir
        container.on('click', '.delete', function (e) {
          e.preventDefault();
          var fileToDelete = $(this).attr('data-file');
          if (confirm('Tem certeza que deseja excluir este arquivo ou pasta?')) {
            $.post("", { 'do': 'delete', file: fileToDelete, xsrf: XSRF }, function (response) {
              updateTrashCount();
              loadTrashList();
              list();
            }, 'json');
          }
          return false;
        });
      }
      // Delegar para tabela e grid
      delegateFileActions($('#table'));
      delegateFileActions($('#fileGrid'));

      // ----------------------------
      // JavaScript: adicionar ícone lápis e handlers de renomear
      // procure no arquivo JS embutido a função renderFileRow e substitua/edite a parte correspondente.
      /* dentro da função renderFileRow(data) - substitua/adicione conforme abaixo (mostramos apenas o trecho alterado) */
      function renderFileRow(data) {
        var $checkbox = $('<input type="checkbox" class="form-check-input select-item me-2">').attr('data-file', data.path);
        var $link = $('<a class="name" />')
          .attr('href', data.is_dir ? '#' + encodeURIComponent(data.path) : './' + data.path)
          .text(data.name);

        // botão de renomear (ícone lápis) ao lado do nome
        var $rename_btn = null;
        // mostrar se o arquivo/pasta puder ser renomeado (permite para itens editáveis ou deletáveis)
        // if (data.is_writable || data.is_deleteable) {
        //   // ADDED: anexa também se é diretório para o handler cliente aplicar regras de extensão
        //   $rename_btn = $('<a href="#" class="rename-btn btn btn-outline-success btn-sm ms-2" title="Renomear"><i class="fa fa-pencil"></i></a>')
        //     .attr('data-file', data.path)
        //     .attr('data-is-dir', data.is_dir ? '1' : '0');
        // }

        var allow_direct_link = <?php echo $allow_direct_link ? 'true' : 'false'; ?>;
        if (!data.is_dir && !allow_direct_link) $link.css('pointer-events', 'none');

        var $dl_link = $('<a class="btn btn-outline-primary btn-md me-2" title="Baixar"><i class="fa fa-download"></i></a>').attr('href', '?do=download&file=' + encodeURIComponent(data.path)).addClass('download');
        var $delete_link = $('<a href="#" class="btn btn-outline-danger btn-md me-2" title="Excluir"><i class="fa fa-trash"></i></a>').attr('data-file', data.path).addClass('delete');
        // Botão visualizar imagem
        var imageExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
        // ADDED: extensões de mídia
        var audioExts = ['mp3', 'wav', 'ogg', 'm4a'];
        var videoExts = ['mp4', 'webm', 'ogg', 'mkv'];
        // ADDED: extensões PDF e botão visualizar PDF
        var pdfExts = ['pdf'];
        // ADDED: extensões de texto e botão editar
        var textExts = ['ssc', 'txt', 'md', 'csv', 'html', 'htm', 'js', 'css', 'json', 'log', 'ini', 'xml', 'yaml', 'yml'];
        var ext = data.name.split('.').pop().toLowerCase();
        var $view_link = null;
        if (!data.is_dir && imageExts.includes(ext)) {
          $view_link = $('<a href="#" class="btn btn-outline-info btn-md me-2" title="Visualizar Imagem"><i class="fa fa-eye" aria-hidden="true"></i></a>').attr('data-file', data.path).addClass('view-image');
        }

        // ADDED: botão para reproduzir áudio
        var $audio_link = null;
        if (!data.is_dir && audioExts.includes(ext)) {
          $audio_link = $('<a href="#" class="btn btn-outline-info btn-md me-2" title="Reproduzir Áudio"><i class="fa fa-headphones" aria-hidden="true"></i></a>').attr('data-file', data.path).addClass('view-audio');
        }
        // ADDED: botão para reproduzir vídeo
        var $video_link = null;
        if (!data.is_dir && videoExts.includes(ext)) {
          $video_link = $('<a href="#" class="btn btn-outline-info btn-md me-2" title="Visualizar Vídeo"><i class="fa fa-video-camera" aria-hidden="true"></i></a>').attr('data-file', data.path).addClass('view-video');
        }

        // ADDED: botão para visualizar PDF inline
        var $pdf_link = null;
        if (!data.is_dir && pdfExts.includes(ext)) {
          $pdf_link = $('<a href="#" class="btn btn-outline-info btn-md me-2" title="Visualizar PDF"><i class="fa fa-file-pdf-o" aria-hidden="true"></i></a>').attr('data-file', data.path).addClass('view-pdf');
        }

        // ADDED: botão para edição de texto
        var $edit_link = null;
        if (!data.is_dir && textExts.includes(ext) && data.is_writable) {
          $edit_link = $('<a href="#" class="btn btn-outline-info btn-md me-2" title="Editar"><i class="fa fa-edit"></i></a>').attr('data-file', data.path).addClass('edit-text');
        }

        // ADDED: botão para visualizar ZIP
        var $zip_link = null;
        if (!data.is_dir && ext === 'zip') {
          $zip_link = $('<a href="#" class="btn btn-outline-warning btn-md me-2" title="Ver conteúdo do ZIP"><i class="fa fa-file-archive-o"></i></a>').attr('data-file', data.path).addClass('view-zip');
        }

        // ADDED: botão para compartilhar arquivo
        var $share_link = null;
        if (!data.is_dir) {
          $share_link = $('<a href="#" class="btn btn-outline-warning btn-md me-2" title="Compartilhar"><i class="fa fa-share-alt"></i></a>').attr('data-file', data.path).addClass('share-file');
        }

        // ADDED: botão informações sobre pastas
        var $info_btn = null;
        if (data.is_dir) {
          $info_btn = $('<a href="#" class="btn btn-link btn-sm me-2 folder-info-btn" title="Informações"><i class="fa fa-info-circle"></i></a>')
            .attr('data-folder', data.path);
        }

        var $rename_btn = null;
        if (data.is_writable || data.is_deleteable) {
          $rename_btn = $('<a href="#" class="btn btn-outline-success btn-md me-2" title="Renomear"><i class="fa fa-pencil"></i></a>')
            .attr('data-file', data.path)
            .attr('data-is-dir', data.is_dir ? '1' : '0')
            .addClass('rename-btn');
        }

        var perms = [];
        if (data.is_readable) perms.push('Visualizar ');
        if (data.is_writable) perms.push(' Editar ');
        if (data.is_executable) perms.push(' Executar');

        var $html = $('<tr />')
          .addClass(data.is_dir ? 'is_dir' : '')
          .append(
            $('<td class="first d-flex align-items-center" />').append($checkbox).append($link).append(data.is_dir ? ($info_btn ? $info_btn : '') : '')
          )
          .append($('<td/>').attr('data-sort', data.is_dir ? -1 : data.size)
            .html(data.is_dir ? $('<span class="size" />').text('--') : $('<span class="size" />').text(formatFileSize(data.size))))
          .append($('<td/>').attr('data-sort', data.mtime).text(formatTimestamp(data.mtime)))
          .append($('<td/>').text(perms.join('~')))
          .append($('<td class="text-center" />')
            .append($view_link ? $view_link : '')
            .append($audio_link ? $audio_link : '')
            .append($video_link ? $video_link : '')
            .append($pdf_link ? $pdf_link : '')
            .append($zip_link ? $zip_link : '') // ADDED: botão ZIP
            .append($share_link ? $share_link : '') // ADDED: botão compartilhar
            .append(data.is_dir ? '' : $dl_link)
            .append($edit_link ? $edit_link : '')
            .append($rename_btn ? $rename_btn : '')
            .append(data.is_deleteable ? $delete_link : '')
          );

        return $html;
      }

      // Handler de renomear movido para delegateFileActions para evitar duplicação

      // ADDED: código unificado para mostrar/popular/salvar modal de configurações
      function populateConfigModal() {
        // Preenche campos do modal que estiver presente (compatível com both formats)
        if ($('#settingsModal').length) {
          $('#allow_delete').prop('checked', !!serverConfig.allow_delete);
          $('#allow_upload').prop('checked', !!serverConfig.allow_upload);
          $('#allow_create_folder').prop('checked', !!serverConfig.allow_create_folder);
          $('#allow_create_file').prop('checked', !!serverConfig.allow_create_file);
          $('#allow_direct_link').prop('checked', !!serverConfig.allow_direct_link);
          $('#allow_show_folders').prop('checked', !!serverConfig.allow_show_folders);
          $('#configTime').val(serverConfig.configTime || 5);
          $('#disallowed_patterns').val(Array.isArray(serverConfig.disallowed_patterns) ? serverConfig.disallowed_patterns.join(',') : (serverConfig.disallowed_patterns || ''));
          $('#hidden_patterns').val(Array.isArray(serverConfig.hidden_patterns) ? serverConfig.hidden_patterns.join(',') : (serverConfig.hidden_patterns || ''));
          $('#senha').val(serverConfig.SENHA || '');
        }
        if ($('#configModal').length) {
          $('#cfg_allow_delete').prop('checked', !!serverConfig.allow_delete);
          $('#cfg_allow_upload').prop('checked', !!serverConfig.allow_upload);
          $('#cfg_allow_create_folder').prop('checked', !!serverConfig.allow_create_folder);
          $('#cfg_allow_create_file').prop('checked', !!serverConfig.allow_create_file);
          $('#cfg_allow_direct_link').prop('checked', !!serverConfig.allow_direct_link);
          $('#cfg_allow_show_folders').prop('checked', !!serverConfig.allow_show_folders);
          $('#cfg_configTime').val(serverConfig.configTime || 5);
          $('#cfg_max_upload_size_mb').val(serverConfig.max_upload_size_mb);
          $('#cfg_disallowed').val(Array.isArray(serverConfig.disallowed_patterns) ? serverConfig.disallowed_patterns.join(',') : (serverConfig.disallowed_patterns || ''));
          $('#cfg_hidden').val(Array.isArray(serverConfig.hidden_patterns) ? serverConfig.hidden_patterns.join(',') : (serverConfig.hidden_patterns || ''));
          $('#cfg_SENHA').val(serverConfig.SENHA || '');
        }
      }

      // Ao clicar no botão de abrir configurações, popular modal e mostrar (usa openConfigBtn presente no HTML)
      $('#openConfigBtn').off('click').on('click', function (e) {
        e.preventDefault();
        populateConfigModal();
        // garantir que o modal correto seja exibido (o HTML já contém #configModal estático)
        if ($('#configModal').length) {
          $('#configModal').modal('show');
        } else if ($('#settingsModal').length) {
          $('#settingsModal').modal('show');
        }
      });

      // Handler unificado para salvar configurações (botão com id saveConfigBtn presente em ambos os modais)
      $('#saveConfigBtn').off('click').on('click', function (event) {
        event.preventDefault(); // Prevent default form submission
        var $btn = $(this);
        $btn.prop('disabled', true);
        var formData = { do: 'saveconfig', xsrf: XSRF };

        // Prioriza os campos sem prefixo (settingsModal). Se não existirem, usa os com prefixo cfg_.
        if ($('#allow_delete').length) {
          formData.allow_delete = $('#allow_delete').prop('checked') ? 1 : 0;
          formData.allow_upload = $('#allow_upload').prop('checked') ? 1 : 0;
          formData.allow_create_folder = $('#allow_create_folder').prop('checked') ? 1 : 0;
          formData.allow_create_file = $('#allow_create_file').prop('checked') ? 1 : 0;
          formData.allow_direct_link = $('#allow_direct_link').prop('checked') ? 1 : 0;
          formData.allow_show_folders = $('#allow_show_folders').prop('checked') ? 1 : 0;
          formData.configTime = $('#configTime').val();
          formData.disallowed_patterns = $('#disallowed_patterns').val();
          formData.hidden_patterns = $('#hidden_patterns').val();
          formData.SENHA = $('#senha').val();
        } else {
          formData.allow_delete = $('#cfg_allow_delete').prop('checked') ? 1 : 0;
          formData.allow_upload = $('#cfg_allow_upload').prop('checked') ? 1 : 0;
          formData.allow_create_folder = $('#cfg_allow_create_folder').prop('checked') ? 1 : 0;
          formData.allow_create_file = $('#cfg_allow_create_file').prop('checked') ? 1 : 0;
          formData.allow_direct_link = $('#cfg_allow_direct_link').prop('checked') ? 1 : 0;
          formData.allow_show_folders = $('#cfg_allow_show_folders').prop('checked') ? 1 : 0;
          formData.configTime = $('#cfg_configTime').val();
          formData.disallowed_patterns = $('#cfg_disallowed').prop('value');
          formData.hidden_patterns = $('#cfg_hidden').prop('value');
          formData.SENHA = $('#cfg_SENHA').val();
        }

        $.post('?', formData, function (res) {
          $btn.prop('disabled', false);
          if (res && res.success) {
            alert('Configurações salvas com sucesso!');
            location.reload();
          } else {
            alert('Erro ao salvar configurações: ' + (res && res.error ? res.error.msg : 'unknown'));
          }
        }, 'json').fail(function () {
          $btn.prop('disabled', false);
          alert('Configurações salvas com sucesso!');
          location.reload();
        });
      });

      // Evita submit do formulário ao pressionar Enter
      $('#configForm').on('submit', function (event) {
        event.preventDefault();
        $('#saveConfigBtn').click();
      });

      // garantir população inicial caso o modal seja aberto por outro caminho
      populateConfigModal();

      // Handler para logout com confirmação
      $('#logoutBtn').off('click').on('click', function (e) {
        e.preventDefault();
        if (confirm('Tem certeza que deseja sair da sessão?')) {
          window.location.href = '?do=logout';
        }
      });

      // Handler para criar novo arquivo
      $('#createFileForm').on('submit', function (e) {
        e.preventDefault();
        var filename = $('#newfilename').val().trim();
        var content = $('#newfilecontent').val();
        var $error = $('#createFileError');
        $error.hide().text('');
        if (!filename) {
          $error.text('Nome do arquivo obrigatório.').show();
          return;
        }
        var hashval = decodeURIComponent(window.location.hash.substr(1)) || '.';
        $.post('?', {
          do: 'createfile',
          name: filename,
          content: content,
          file: hashval,
          xsrf: XSRF
        }, function (resp) {
          try {
            var data = typeof resp === 'string' ? JSON.parse(resp) : resp;
            if (data.success) {
              $('#createFileModal').modal('hide');
              $('#newfilename').val('');
              $('#newfilecontent').val('');
              if (typeof list === 'function') list();
            } else {
              $error.text((data.error && data.error.msg) || 'Erro desconhecido.').show();
            }
          } catch (ex) {
            $error.text('Erro ao processar resposta do servidor.').show();
          }
        }, 'json').fail(function (xhr) {
          var msg = 'Erro ao criar arquivo.';
          if (xhr.responseText) {
            try {
              var data = JSON.parse(xhr.responseText);
              msg = (data.error && data.error.msg) || msg;
            } catch { }
          }
          $error.text(msg).show();
        });
      });

      // Atualiza o contador de itens na lixeira
      function updateTrashCount() {
        $.get('?do=listtrash', function (res) {
          if (res && res.success && res.results) {
            var count = res.results.length;
            var badge = $('#trashCountBadge');
            if (count > 0) {
              badge.text(count).show();
            } else {
              badge.hide();
            }
          }
        }, 'json');
      }
      // Atualizar ao carregar a página e ao abrir a lixeira
      $(function () {
        updateTrashCount();
        $('#openTrashBtn').on('click', function () {
          setTimeout(updateTrashCount, 500); // Atualiza após possíveis alterações
        });
      });

      // Atualizar lista e badge ao fechar a modal da lixeira
      $('#trashModal').on('hidden.bs.modal', function () {
        loadTrashList();
        updateTrashCount();
        if (typeof list === 'function') list(); // Atualiza a lista principal se função existir
      });

      // ADDED: Atualizar lista ao fechar a modal de conversão de imagens
      $('#convertImagesModal').on('hidden.bs.modal', function () {
        console.log('Modal de conversão fechada - atualizando lista de arquivos...');

        // Atualizar a lista de arquivos para mostrar os novos arquivos convertidos
        if (typeof list === 'function') {
          list();
        }

        // Limpar seleções
        $('.select-item').prop('checked', false);
        $('#selectAll').prop('checked', false);
        updateBulkButtons();

        // Resetar modal para próximo uso
        $('#conversionProgress').hide();
        $('#conversionResults').hide();
        $('#conversionCompleteActions').hide();
        $('#conversionProgressBar').css('width', '0%').text('0%');
        $('#conversionStatus').text('Preparando conversão...');
        $('#resultsContainer').html('');
        $('#selectedImagesList').html('<small class="text-muted">Carregando...</small>');

        // Resetar configurações para padrões
        $('#outputFormat').val('webp');
        $('#imageQuality').val('85');
        $('#qualityValue').text('85');
        $('#maxWidth').val('1920');
        $('#maxHeight').val('1080');
        $('#preserveOriginal').prop('checked', true);
        $('#addSuffix').prop('checked', true).prop('disabled', false);

        console.log('Lista de arquivos atualizada e modal resetada.');
      });
    })

  </script>

</head>

<body>
  <!-- Overlay para mobile quando sidebar está aberto -->
  <div id="sidebarOverlay" class="sidebar-overlay"></div>

  <!-- Menu lateral moderno -->
  <div id="sidebar" class="sidebar">
    <!-- Header do sidebar -->
    <div class="sidebar-header">
      <div class="sidebar-brand">
        <i class="fa fa-folder-o"></i>
        <span class="sidebar-brand-text">File Manager</span>
      </div>
      <button id="closeSidebarBtn" class="sidebar-close-btn">
        <i class="fa fa-times"></i>
      </button>
    </div>

    <!-- Timer de sessão no sidebar -->
    <div id="session-timer" class="sidebar-session-timer">
      <i class="fa fa-clock-o"></i>
      <span class="session-text">Carregando...</span>
    </div>

    <!-- Menu do sidebar -->
    <div class="sidebar-menu">

      <!-- Seção: Visualização -->
      <div class="menu-section">
        <div class="menu-section-title">
          <i class="fa fa-eye"></i>
          Visualização
        </div>
        <div class="menu-items">
          <button id="toggleViewBtn" class="menu-item" title="Alternar visualização">
            <i class="fa fa-th-large"></i>
            <span class="menu-text">
              <span class="menu-label">Modo Exibição</span>
              <span id="toggleViewText" class="menu-value">Blocos</span>
            </span>
          </button>
        </div>
      </div>

      <!-- Seção: Criar -->
      <?php if ($allow_create_folder || $allow_create_file): ?>
        <div class="menu-section">
          <div class="menu-section-title">
            <i class="fa fa-plus"></i>
            Criar
          </div>
          <div class="menu-items">
            <?php if ($allow_create_folder): ?>
              <button class="menu-item" data-bs-toggle="modal" data-bs-target="#createFolder" title="Criar Pasta">
                <i class="fa fa-folder-o"></i>
                <span class="menu-text">
                  <span class="menu-label">Nova Pasta</span>
                  <span class="menu-desc">Criar diretório</span>
                </span>
              </button>
            <?php endif; ?>
            <?php if ($allow_create_file): ?>
              <button class="menu-item" data-bs-toggle="modal" data-bs-target="#createFileModal" title="Criar Arquivo">
                <i class="fa fa-file-o"></i>
                <span class="menu-text">
                  <span class="menu-label">Novo Arquivo</span>
                  <span class="menu-desc">Criar arquivo de texto</span>
                </span>
              </button>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Seção: Upload -->
      <?php if ($allow_upload): ?>
        <div class="menu-section">
          <div class="menu-section-title">
            <i class="fa fa-upload"></i>
            Upload
          </div>
          <div class="menu-items">
            <button class="menu-item" data-bs-toggle="modal" data-bs-target="#makeUpload" title="Carregar arquivos">
              <i class="fa fa-upload"></i>
              <span class="menu-text">
                <span class="menu-label">Carregar Arquivos</span>
                <span class="menu-desc">Upload de documentos</span>
              </span>
            </button>
          </div>
        </div>
      <?php endif; ?>

      <!-- Seção: Selecionados -->
      <div class="menu-section">
        <div class="menu-section-title">
          <i class="fa fa-check-square-o"></i>
          Selecionados
        </div>
        <div class="menu-items">
          <?php if ($allow_delete): ?>
            <button id="deleteSelectedBtn" class="menu-item menu-item-danger" title="Excluir Selecionados" disabled>
              <i class="fa fa-trash"></i>
              <span class="menu-text">
                <span class="menu-label">Excluir</span>
                <span class="menu-desc">Remover selecionados</span>
              </span>
            </button>
          <?php endif; ?>
          <?php if ($allow_upload): ?>
            <button id="zipSelectedBtn" class="menu-item menu-item-info" title="Zip Selecionados" disabled>
              <i class="fa fa-file-archive-o"></i>
              <span class="menu-text">
                <span class="menu-label">Criar ZIP</span>
                <span class="menu-desc">Comprimir selecionados</span>
              </span>
            </button>
            <button id="moveCopyBtn" class="menu-item menu-item-warning" title="Mover/Copiar Selecionados" disabled>
              <i class="fa fa-exchange"></i>
              <span class="menu-text">
                <span class="menu-label">Mover/Copiar</span>
                <span class="menu-desc">Organizar arquivos</span>
              </span>
            </button>
            <button id="convertImagesBtn" class="menu-item menu-item-success" title="Converter/Comprimir Imagens Selecionadas" disabled>
              <i class="fa fa-image"></i>
              <span class="menu-text">
                <span class="menu-label">Converter Imagens</span>
                <span class="menu-desc">Otimizar fotos</span>
              </span>
            </button>
          <?php endif; ?>
        </div>
      </div>

      <!-- Seção: Ferramentas -->
      <div class="menu-section">
        <div class="menu-section-title">
          <i class="fa fa-wrench"></i>
          Ferramentas
        </div>
        <div class="menu-items">
          <button class="menu-item" onClick="window.location.reload()" title="Atualizar lista">
            <i class="fa fa-refresh"></i>
            <span class="menu-text">
              <span class="menu-label">Atualizar</span>
              <span class="menu-desc">Recarregar lista</span>
            </span>
          </button>
          <button id="toggleAudioPlayerBtn" class="menu-item menu-item-player" title="Mostrar/Ocultar Player de Áudio">
            <i class="fa fa-music"></i>
            <span class="menu-text">
              <span class="menu-label">Player de Áudio</span>
              <span id="audioPlayerStatusText" class="menu-desc">Reprodutor musical</span>
            </span>
          </button>
          <button id="shareLinksBtn" class="menu-item" title="Gerenciar Links de Compartilhamento">
            <i class="fa fa-share-alt"></i>
            <span class="menu-text">
              <span class="menu-label">Compartilhamento</span>
              <span class="menu-desc">Links públicos</span>
            </span>
          </button>
          <button id="openTrashBtn" class="menu-item" title="Lixeira">
            <i class="fa fa-trash"></i>
            <span class="menu-text">
              <span class="menu-label">Lixeira</span>
              <span class="menu-desc">Arquivos excluídos</span>
            </span>
            <span id="trashCountBadge" class="menu-badge">0</span>
          </button>
        </div>
      </div>

      <!-- Seção: Sistema -->
      <div class="menu-section">
        <div class="menu-section-title">
          <i class="fa fa-cog"></i>
          Sistema
        </div>
        <div class="menu-items">
          <?php if ($permissionAdmin): ?>
            <button id="openConfigBtn" class="menu-item" data-bs-toggle="modal" data-bs-target="#configModal"
              title="Configurações">
              <i class="fa fa-cog"></i>
              <span class="menu-text">
                <span class="menu-label">Configurações</span>
                <span class="menu-desc">Painel admin</span>
              </span>
            </button>
          <?php endif; ?>
          <button id="logoutBtn" class="menu-item menu-item-danger" title="Sair">
            <i class="fa fa-sign-out"></i>
            <span class="menu-text">
              <span class="menu-label">Deslogar</span>
              <span class="menu-desc">Encerrar sessão</span>
            </span>
          </button>
        </div>
      </div>

    </div>
  </div>

  <!-- Conteúdo principal -->
  <div id="mainContent" class="main-content">
    <!-- Header superior com breadcrumb e botão do menu -->
    <div class="top-header">
      <button id="toggleSidebarBtn" class="sidebar-toggle-btn">
        <i class="fa fa-bars"></i>
      </button>
      <div id="breadcrumb" class="breadcrumb-container"></div>
    </div>
    <div class="d-flex align-items-center p-2 text-white bg-success border-theme-default">
      <div class="form-check">
        <input type="checkbox" id="selectAll" class="form-check-input me-2">
        <label class="form-check-label" for="selectAll">Selecionar Todos</label>
      </div>
      <div class="ms-auto text-end">
        <small class="opacity-75">
          💡 <strong>Dica:</strong> Arraste arquivos/pastas para dentro de pastas para movê-los rapidamente
        </small>
      </div>
    </div>
    <table id="table">
      <thead>
        <tr>
          <th>Nome</th>
          <th>Tamanho</th>
          <th>Modificado</th>
          <th>Permissões</th>
          <th class="text-center">Ações</th>
        </tr>
      </thead>
      <tbody id="list">
      </tbody>
    </table>
  </div> <!-- Fim do main-content -->

  <!-- Modal para galeria de imagens com carrossel -->
  <div id="imagePreviewModal"
    style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.9);z-index:9999;align-items:center;justify-content:center;">

    <!-- Controles de navegação -->
    <button id="prevImageBtn" style="position:absolute;left:20px;top:50%;transform:translateY(-50%);z-index:10001;"
      class="btn btn-light btn-lg" title="Imagem anterior">
      <i class="fa fa-chevron-left"></i>
    </button>

    <button id="nextImageBtn" style="position:absolute;right:20px;top:50%;transform:translateY(-50%);z-index:10001;"
      class="btn btn-light btn-lg" title="Próxima imagem">
      <i class="fa fa-chevron-right"></i>
    </button>

    <!-- Container principal da imagem -->
    <div
      style="position:relative;max-width:90vw;max-height:90vh;margin:auto;display:flex;flex-direction:column;align-items:center;justify-content:center;">

      <!-- Informações da imagem -->
      <div id="imageInfo"
        style="position:absolute;top:-60px;left:50%;transform:translateX(-50%);color:white;text-align:center;z-index:10001;">
        <div id="imageTitle" style="font-size:18px;font-weight:bold;margin-bottom:5px;"></div>
        <div id="imageCounter" style="font-size:14px;opacity:0.8;"></div>
      </div>

      <!-- Imagem principal -->
      <img id="imagePreviewModalImg" src="" alt="Preview"
        style="max-width:75vw;max-height:75vh;border-radius:8px;box-shadow:0 0 30px rgba(0,0,0,0.8);cursor:pointer;"
        title="Clique para zoom" />

      <!-- Controles inferiores -->
      <div
        style="position:absolute;bottom:-80px;left:50%;transform:translateX(-50%);display:flex;gap:10px;z-index:10001;">
        <button id="imagePreviewModalClose" class="btn btn-light">
          <i class="fa fa-times"></i> Fechar
        </button>
        <button id="zoomToggleBtn" class="btn btn-secondary">
          <i class="fa fa-search-plus"></i> Zoom
        </button>
        <button id="downloadCurrentBtn" class="btn btn-primary">
          <i class="fa fa-download"></i> Download
        </button>
      </div>
    </div>

    <!-- Miniatura da galeria (parte inferior) -->
    <div id="imageThumbnailGallery"
      style="position:absolute;bottom:20px;left:50%;transform:translateX(-50%);display:flex;gap:8px;max-width:90vw;overflow-x:auto;padding:10px;background:rgba(0,0,0,0.7);border-radius:8px;z-index:10001;">
      <!-- Thumbnails serão adicionadas dinamicamente -->
    </div>
  </div>

  <!-- ADDED: Modal para visualização de PDF -->
  <div id="pdfViewModal"
    style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.75);z-index:10001;align-items:center;justify-content:center;">
    <div
      style="position:relative;width:90%;height:90%;background:#fff;border-radius:8px;overflow:hidden;display:flex;flex-direction:column;">
      <button id="pdfViewModalClose" class="btn btn-light" style="z-index:10002;">Fechar</button>
      <iframe id="pdfViewModalIframe" src="" style="flex:1;border:0;width:100%;height:100%;"></iframe>
    </div>
  </div>

  <!-- ADDED: Modal para reprodução de áudio / vídeo -->
  <div id="mediaModal"
    style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.75);z-index:10002;align-items:center;justify-content:center;">
    <div
      style="position:relative;width:90%;max-width:1100px;height:80%;background:#fff;border-radius:8px;overflow:hidden;display:flex;flex-direction:column;">
      <div
        style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border-bottom:1px solid #ddd;">
        <div id="mediaTitle" style="font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
        </div>
        <button id="mediaModalClose" class="btn btn-light">Fechar</button>
      </div>
      <div style="flex:1;display:flex;align-items:center;justify-content:center;padding:12px;background:#000;">
        <video id="mediaPlayerVideo" style="width:100dvh;display:none;background:#000;" controls></video>
        <audio id="mediaPlayerAudio" style="width:100dvh;display:none;" controls></audio>
      </div>
    </div>
  </div>

  <!-- Player de áudio flutuante melhorado -->
  <div id="floatingAudioPlayer" style="display:none;">
    <div class="floating-player-header">
      <div class="player-title">
        <i class="fa fa-music"></i>
        <span id="currentTrackName">Nenhuma música</span>
      </div>
      <div class="player-controls-header">
        <button id="togglePlayerBtn" class="btn-player-control" title="Minimizar/Expandir">
          <i class="fa fa-chevron-down"></i>
        </button>
        <button id="closePlayerBtn" class="btn-player-control" title="Fechar Player">
          <i class="fa fa-times"></i>
        </button>
      </div>
    </div>

    <div class="floating-player-body" id="playerBody">
      <div class="player-info">
        <div class="track-info">
          <div class="track-name" id="trackDisplayName">Nenhuma música selecionada</div>
          <div class="track-time">
            <span id="currentTime">0:00</span>
            <span>/</span>
            <span id="totalTime">0:00</span>
          </div>
        </div>
      </div>

      <div class="player-progress">
        <div class="progress-bar-container">
          <div class="progress-bar" id="progressBar">
            <div class="progress-fill" id="progressFill"></div>
            <div class="progress-handle" id="progressHandle"></div>
          </div>
        </div>
      </div>

      <div class="player-controls">
        <button id="prevBtn" class="btn-player-control" title="Anterior">
          <i class="fa fa-step-backward"></i>
        </button>
        <button id="playPauseBtn" class="btn-player-control play-pause-btn" title="Play/Pause">
          <i class="fa fa-play"></i>
        </button>
        <button id="nextBtn" class="btn-player-control" title="Próximo">
          <i class="fa fa-step-forward"></i>
        </button>
        <button id="repeatBtn" class="btn-player-control" title="Repetir">
          <i class="fa fa-repeat"></i>
        </button>
        <button id="shuffleBtn" class="btn-player-control" title="Aleatório">
          <i class="fa fa-random"></i>
        </button>
        <div class="volume-control">
          <button id="muteBtn" class="btn-player-control" title="Mudo">
            <i class="fa fa-volume-up"></i>
          </button>
          <div class="volume-slider">
            <input type="range" id="volumeSlider" min="0" max="100" value="100">
          </div>
        </div>
      </div>

      <div class="playlist-container" id="playlistContainer" style="display:none;">
        <div class="playlist-header">
          <h6>Lista de Reprodução</h6>
          <button id="clearPlaylistBtn" class="btn btn-sm btn-outline-danger">
            <i class="fa fa-trash"></i> Limpar
          </button>
        </div>
        <div class="playlist" id="playlist">
          <!-- Playlist será preenchida dinamicamente -->
        </div>
      </div>
    </div>

    <audio id="floatingAudioElement" preload="metadata"></audio>
  </div>

  <div class="modal" id="createFolder">
    <div class="modal-dialog">
      <div class="modal-content">
        <!-- Modal Header -->
        <div class="modal-header">
          <h4 class="modal-title">Criar Pasta</h4>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <!-- Modal body -->
        <div class="modal-body">
          <?php if ($allow_create_folder): ?>
            <form action="?" method="post" id="mkdir" />
            <label for=dirname>Qual será o nome da pasta ?</label>
            <input type="text" class="form-control" id="dirname" placeholder="Nova pasta" name="dirname">
            <div class="modal-footer-alt">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-success" data-bs-dismiss="modal">Criar</button>
            </div>
            </form>
          <?php endif; ?>
          <?php if (!$allow_create_folder): ?>
            <p>Sem permissão para criar pasta</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>


  <div class="modal" id="makeUpload">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <!-- Modal de confirmação de exclusão -->
        <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteLabel"
          aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="confirmDeleteLabel">Confirmar Exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                Tem certeza que deseja excluir este arquivo ou pasta?
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Excluir</button>
              </div>
            </div>
          </div>
        </div>
        <!-- Modal Header -->
        <div class="modal-header">
          <h4 class="modal-title">Carregar arquivos</h4>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <!-- Modal body -->
        <div class="modal-body d-grid">
          <div id="upload_progress"></div>
          <?php if ($allow_upload): ?>
            <div id="file_drop_target">
              Arraste os arquivos aqui para fazer o carregamento<br>
              <b>ou</b>
              <div class="custom-file mb-3">
                <input type="file" multiple class="custom-file-input" id="customFile" name="filename">
                <label class="custom-file-label" for="customFile">Nenhum arquivo selecionado...</label>
              </div>
            </div>
          <?php endif; ?>
          <button type="button" class="btn btn-secondary btn-block mt-3" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ADDED: Modal para criar novo arquivo -->
  <div class="modal" id="createFileModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form id="createFileForm">
          <div class="modal-header">
            <h5 class="modal-title">Criar Novo Arquivo de Texto</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-2">
              <label for="newfilename" class="form-label">Nome do arquivo (ex: novo.txt)</label>
              <input type="text" id="newfilename" class="form-control" placeholder="nome-do-arquivo.txt" required>
            </div>
            <div class="mb-2">
              <label for="newfilecontent" class="form-label">Conteúdo inicial (opcional)</label>
              <textarea id="newfilecontent" class="form-control" rows="6" placeholder="Conteúdo inicial..."></textarea>
            </div>
            <div id="createFileError" class="text-danger" style="display:none;"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Criar Arquivo</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal para informações da pasta -->
  <div class="modal fade" id="folderInfoModal" tabindex="-1" aria-labelledby="folderInfoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="folderInfoModalLabel">Informações da Pasta</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body" id="folderInfoContent">
          <!-- Conteúdo preenchido via JS -->
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ADDED: Modal com CodeMirror para editar arquivos de texto -->
  <div id="textEditModal"
    style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:#252526;z-index:10000;">
    <div style="display:flex;flex-direction:column;width: 100vw;height:100vh;background:#1e1e1e;">

      <!-- Barra superior estilo VS Code -->
      <div class="vscode-titlebar"
        style="background:#3c3c3c;height:35px;display:flex;align-items:center;padding:0 15px;justify-content:space-between;border-bottom:1px solid #464647;">
        <div class="d-flex align-items-center">
          <i class="fa fa-code" style="color:#007acc;margin-right:8px;"></i>
          <span style="color:#cccccc;font-size:13px;font-weight:500;">Editor de Código</span>
          <span style="color:#969696;margin-left:10px;font-size:12px;" id="currentFileName"></span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <button id="minimizeBtn" class="vscode-btn"
            style="color:#cccccc;background:none;border:none;padding:0;width:28px;height:28px;" title="Minimizar">
            <i class="fa fa-window-minimize" style="font-size:10px;"></i>
          </button>
          <button id="maximizeBtn" class="vscode-btn"
            style="color:#cccccc;background:none;border:none;padding:0;width:28px;height:28px;" title="Maximizar">
            <i class="fa fa-window-maximize" style="font-size:10px;"></i>
          </button>
          <button id="textEditClose" class="vscode-btn vscode-close"
            style="color:#cccccc;background:none;border:none;padding:0;width:28px;height:28px;" title="Fechar">
            <i class="fa fa-times" style="font-size:12px;"></i>
          </button>
        </div>
      </div>

      <!-- Barra de abas -->
      <div class="vscode-tabs"
        style="background:#2d2d30;height:35px;display:flex;align-items:center;border-bottom:1px solid #464647;">
        <div class="tab-active"
          style="background:#1e1e1e;height:100%;display:flex;align-items:center;padding:10px 15px;border-right:1px solid #464647;min-width:150px;">
          <i class="fa fa-file-code-o" style="color:#75beff;margin-right:8px;font-size:12px;"></i>
          <span style="color:#cccccc;font-size:12px;" id="tabFileName">arquivo.txt</span>
        </div>
      </div>

      <!-- Barra de ferramentas estilo VS Code -->
      <div class="vscode-toolbar"
        style="background:#2d2d30;border-bottom:1px solid #464647;padding:8px 15px;display:flex;flex-wrap:wrap;align-items:center;gap:15px;">

        <!-- Grupo 1: Linguagem e configurações -->
        <div class="d-flex align-items-center gap-10px">
          <select id="languageSelect" class="vscode-select"
            style="background:#3c3c3c;color:#cccccc;border:1px solid #464647;border-radius:3px;padding:4px 8px;font-size:11px;">
            <option value="text">Plain Text</option>
            <option value="javascript">JavaScript</option>
            <option value="application/json">JSON</option>
            <option value="css">CSS</option>
            <option value="htmlmixed">HTML</option>
            <option value="xml">XML</option>
            <option value="php">PHP</option>
            <option value="python">Python</option>
            <option value="sql">SQL</option>
            <option value="markdown">Markdown</option>
            <option value="yaml">YAML</option>
            <option value="shell">Shell</option>
            <option value="dockerfile">Dockerfile</option>
            <option value="text/x-csrc">C</option>
            <option value="text/x-c++src">C++</option>
            <option value="text/x-java">Java</option>
          </select>

          <select id="themeSelect" class="vscode-select"
            style="background:#3c3c3c;color:#cccccc;border:1px solid #464647;border-radius:3px;padding:4px 8px;font-size:11px;">
            <option value="default">Light</option>
            <option value="monokai" selected>Dark+ (default dark)</option>
            <option value="dracula">Dracula</option>
            <option value="material">Material Dark</option>
            <option value="solarized light">Solarized Light</option>
            <option value="solarized dark">Solarized Dark</option>
          </select>

          <select id="fontSizeSelect" class="vscode-select"
            style="background:#3c3c3c;color:#cccccc;border:1px solid #464647;border-radius:3px;padding:4px 8px;font-size:11px;width:60px;">
            <option value="11px">11</option>
            <option value="12px">12</option>
            <option value="13px">13</option>
            <option value="14px" selected>14</option>
            <option value="16px">16</option>
            <option value="18px">18</option>
            <option value="20px">20</option>
          </select>
        </div>

        <!-- Separador -->
        <div style="width:1px;height:20px;background:#464647;margin:0 5px;"></div>

        <!-- Grupo 2: Ações do editor -->
        <div class="d-flex align-items-center gap-5px">
          <button type="button" class="vscode-toolbar-btn" id="saveTextBtn" title="Salvar (Ctrl+S)"
            style="background:#007acc;color:white;">
            <i class="fa fa-save"></i>
          </button>
          <button type="button" class="vscode-toolbar-btn" id="searchBtn" title="Buscar (Ctrl+F)">
            <i class="fa fa-search"></i>
          </button>
          <button type="button" class="vscode-toolbar-btn" id="replaceBtn" title="Substituir (Ctrl+H)">
            <i class="fa fa-exchange"></i>
          </button>
          <button type="button" class="vscode-toolbar-btn" id="gotoLineBtn" title="Ir para linha (Alt+G)">
            <i class="fa fa-hashtag"></i>
          </button>
          <button type="button" class="vscode-toolbar-btn" id="foldBtn" title="Dobrar código (Ctrl+Q)">
            <i class="fa fa-minus-square-o"></i>
          </button>
          <button type="button" class="vscode-toolbar-btn" id="unfoldAllBtn" title="Expandir tudo (Ctrl+Shift+Q)">
            <i class="fa fa-plus-square-o"></i>
          </button>
          <button type="button" class="vscode-toolbar-btn" id="previewBtn" title="Preview (F5)">
            <i class="fa fa-eye"></i>
          </button>
          <button type="button" class="vscode-toolbar-btn" id="indentBtn" title="Indentar linhas selecionadas (Tab)">
            <i class="fa fa-indent"></i>
          </button>
          <button type="button" class="vscode-toolbar-btn" id="unindentBtn" title="Remover indentação (Shift+Tab)">
            <i class="fa fa-outdent"></i>
          </button>
          <button type="button" class="vscode-toolbar-btn" id="formatCodeBtn" title="Formatar código (Shift+Alt+F)">
            <i class="fa fa-magic"></i>
          </button>
        </div>

        <!-- Separador -->
        <div style="width:1px;height:20px;background:#464647;margin:0 5px;"></div>

        <!-- Grupo 3: Opções -->
        <div class="d-flex align-items-center gap-15px">
          <label class="vscode-checkbox">
            <input type="checkbox" id="wrapLinesToggle" checked>
            <span>Word Wrap</span>
          </label>
          <label class="vscode-checkbox">
            <input type="checkbox" id="showLineNumbers" checked>
            <span>Line Numbers</span>
          </label>
        </div>
      </div>

      <!-- Área do CodeMirror e Preview -->
      <div id="editorContainer" class="d-flex" style="height: calc(90% - 30px);gap: 10px;overflow: auto;">
        <!-- Editor -->
        <div id="codeMirrorContainer" style="flex:1;border:1px solid #ddd;border-radius:4px;">
          <textarea id="codeEditor"></textarea>
        </div>

        <!-- Preview (inicialmente oculto) -->
        <div id="previewContainer"
          style="flex:1;border:1px solid #ddd;border-radius:4px;background:#fff;overflow:auto;display:none;">
          <div id="previewHeader" style="background:#f8f9fa;padding:8px;border-bottom:1px solid #ddd;font-weight:500;">
            <i class="fa fa-eye"></i> Preview
            <button type="button" class="btn btn-sm btn-outline-secondary float-end" id="closePreviewBtn"
              title="Fechar Preview">
              <i class="fa fa-times"></i>
            </button>
          </div>
          <div id="previewContent" style="padding:15px;"></div>
        </div>
      </div>

      <!-- Barra de status estilo VS Code -->
      <div class="vscode-status-bar">
        <div class="d-flex align-items-center gap-15px">
          <span id="editorInfo" style="color:#ffffff;">Ln 1, Col 1</span>
          <span id="encodingInfo" style="color:#ffffff;">UTF-8</span>
          <span id="languageInfo" style="color:#ffffff;">Plain Text</span>
        </div>
        <div class="d-flex align-items-center gap-10px">
          <span id="textEditStatus" style="color:#ffffff;"></span>
        </div>
      </div>
    </div>
  </div>
  <script>
    // Variáveis globais do CodeMirror
    var codeMirrorEditor = null;
    var currentEditingFile = null;

    // Função para detectar modo do CodeMirror baseado na extensão do arquivo
    function getModeFromFilename(filename) {
      if (!filename) return 'text';

      var ext = filename.toLowerCase().split('.').pop();
      var modeMap = {
        'js': 'javascript',
        'json': 'application/json',
        'css': 'css',
        'html': 'htmlmixed',
        'htm': 'htmlmixed',
        'xml': 'xml',
        'php': 'php',
        'py': 'python',
        'sql': 'sql',
        'md': 'markdown',
        'markdown': 'markdown',
        'yml': 'yaml',
        'yaml': 'yaml',
        'sh': 'shell',
        'bash': 'shell',
        'dockerfile': 'dockerfile',
        'c': 'text/x-csrc',
        'h': 'text/x-csrc',
        'cpp': 'text/x-c++src',
        'cc': 'text/x-c++src',
        'cxx': 'text/x-c++src',
        'java': 'text/x-java',
        'txt': 'text',
        'log': 'text',
        'ini': 'text',
        'conf': 'text',
        'cfg': 'text'
      };

      return modeMap[ext] || 'text';
    }

    // Inicializar CodeMirror
    function initCodeMirror() {
      if (codeMirrorEditor) {
        codeMirrorEditor.toTextArea();
      }

      codeMirrorEditor = CodeMirror.fromTextArea(document.getElementById('codeEditor'), {
        lineNumbers: true,
        mode: 'text',
        theme: 'monokai',
        indentUnit: 2,
        smartIndent: true,
        tabSize: 2,
        indentWithTabs: false,
        electricChars: true,
        autoCloseBrackets: true,
        matchBrackets: true,
        matchTags: { bothTags: true },
        styleActiveLine: true,
        lineWrapping: true,
        foldGutter: true,
        gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
        autoCloseTags: true,
        extraKeys: {
          "Ctrl-F": "findPersistent",
          "Ctrl-H": "replace",
          "Alt-G": "jumpToLine",
          "Ctrl-S": function (cm) { $('#saveTextBtn').click(); },
          "Esc": function (cm) { $('#textEditClose').click(); },
          "Ctrl-Q": function (cm) { cm.foldCode(cm.getCursor()); },
          "Ctrl-Shift-Q": function (cm) { cm.execCommand("unfoldAll"); },
          "Alt-F": "fold",
          "Alt-Shift-F": "unfold",
          "F5": function (cm) { togglePreview(); },
          "Ctrl-Space": "autocomplete",
          "Ctrl-J": "toMatchingTag",
          "Tab": function (cm) { indentSelection(cm); },
          "Shift-Tab": function (cm) { unindentSelection(cm); },
          "Shift-Alt-F": function (cm) { formatCode(cm); },
          "Ctrl-]": function (cm) { indentSelection(cm); },
          "Ctrl-[": function (cm) { unindentSelection(cm); }
        }
      });

      // Atualizar informações do editor
      codeMirrorEditor.on('cursorActivity', updateEditorInfo);
      codeMirrorEditor.on('change', updateEditorInfo);

      // Aplicar configurações iniciais
      updateFontSize();
    }

    // Atualizar informações do editor (linha/coluna/caracteres)
    function updateEditorInfo() {
      if (!codeMirrorEditor) return;

      var cursor = codeMirrorEditor.getCursor();
      var doc = codeMirrorEditor.getDoc();
      var lines = doc.lineCount();
      var chars = doc.getValue().length;
      var selected = doc.getSelection().length;

      // Estilo VS Code na barra de status
      var info = `Ln ${cursor.line + 1}, Col ${cursor.ch + 1}`;
      if (selected > 0) {
        info += ` (${selected} selected)`;
      }

      $('#editorInfo').text(info);

      // Atualizar linguagem na barra de status
      var mode = codeMirrorEditor.getOption('mode');
      var languageName = getLanguageDisplayName(mode);
      $('#languageInfo').text(languageName);
    }

    // Converter modo para nome de exibição
    function getLanguageDisplayName(mode) {
      var modeNames = {
        'javascript': 'JavaScript',
        'application/json': 'JSON',
        'css': 'CSS',
        'htmlmixed': 'HTML',
        'xml': 'XML',
        'php': 'PHP',
        'python': 'Python',
        'sql': 'SQL',
        'markdown': 'Markdown',
        'yaml': 'YAML',
        'shell': 'Shell',
        'dockerfile': 'Dockerfile',
        'text/x-csrc': 'C',
        'text/x-c++src': 'C++',
        'text/x-java': 'Java',
        'text': 'Plain Text'
      };

      return modeNames[mode] || 'Plain Text';
    }

    // Atualizar tamanho da fonte
    function updateFontSize() {
      if (!codeMirrorEditor) return;

      var fontSize = $('#fontSizeSelect').val();
      $('.CodeMirror').css('font-size', fontSize);
      codeMirrorEditor.refresh();
    }

    // Funções de indentação
    function indentSelection(cm) {
      if (cm.somethingSelected()) {
        cm.indentSelection("add");
      } else {
        // Se nada estiver selecionado, inserir tab na posição do cursor
        var cursor = cm.getCursor();
        var indentUnit = cm.getOption("indentUnit");
        var spaces = Array(indentUnit + 1).join(" ");
        cm.replaceSelection(spaces);
      }
    }

    function unindentSelection(cm) {
      if (cm.somethingSelected()) {
        cm.indentSelection("subtract");
      } else {
        // Se nada estiver selecionado, remover indentação da linha atual
        var cursor = cm.getCursor();
        var line = cm.getLine(cursor.line);
        var indentUnit = cm.getOption("indentUnit");

        // Verificar se há espaços ou tabs no início da linha para remover
        var match = line.match(/^(\s*)/);
        if (match && match[1].length > 0) {
          var indent = match[1];
          var toRemove = Math.min(indentUnit, indent.length);
          cm.replaceRange("", { line: cursor.line, ch: 0 }, { line: cursor.line, ch: toRemove });
        }
      }
    }

    function formatCode(cm) {
      var mode = cm.getOption('mode');
      var totalLines = cm.lineCount();

      // Mostrar indicador visual
      $('#formatCodeBtn').addClass('active').find('i').removeClass('fa-magic').addClass('fa-spinner fa-spin');
      $('#textEditStatus').text('Formatando código...');

      // Usar setTimeout para permitir que a UI atualize
      setTimeout(function () {
        // Auto-indentação básica para diferentes linguagens
        cm.operation(function () {
          for (var i = 0; i < totalLines; i++) {
            cm.indentLine(i, "smart");
          }
        });

        // Formatação específica por linguagem
        switch (mode) {
          case 'application/json':
            try {
              var content = cm.getValue();
              var parsed = JSON.parse(content);
              var formatted = JSON.stringify(parsed, null, 2);
              cm.setValue(formatted);
            } catch (e) {
              console.log('Erro ao formatar JSON:', e);
            }
            break;

          case 'htmlmixed':
            // Formatação básica de HTML
            formatHtml(cm);
            break;

          case 'css':
            // Formatação básica de CSS
            formatCss(cm);
            break;

          default:
            // Para outras linguagens, apenas auto-indentar
            break;
        }

        showSuccessNotification('Código formatado com sucesso!');

        // Resetar indicador visual
        $('#formatCodeBtn').removeClass('active').find('i').removeClass('fa-spinner fa-spin').addClass('fa-magic');
        $('#textEditStatus').text('');
      }, 100);
    }

    function formatHtml(cm) {
      var content = cm.getValue();
      var formatted = content
        .replace(/></g, '>\n<')
        .replace(/^\s+|\s+$/g, '')
        .split('\n');

      var result = [];
      var indent = 0;
      var indentStr = '  '; // 2 espaços

      formatted.forEach(function (line) {
        line = line.trim();
        if (line) {
          if (line.match(/^<\//) && !line.match(/^<\/.+>.*<\/.+>$/)) {
            indent--;
          }

          result.push(Array(Math.max(0, indent)).join(indentStr) + line);

          if (line.match(/^<[^\/][^>]*[^\/]>/) && !line.match(/^<[^\/][^>]*[^\/]>.*<\/.+>$/)) {
            indent++;
          }
        }
      });

      cm.setValue(result.join('\n'));
    }

    function formatCss(cm) {
      var content = cm.getValue();
      var formatted = content
        .replace(/\s*{\s*/g, ' {\n  ')
        .replace(/;\s*/g, ';\n  ')
        .replace(/\s*}\s*/g, '\n}\n')
        .replace(/,\s*/g, ',\n')
        .replace(/\n\s*\n/g, '\n')
        .replace(/^\s+|\s+$/g, '');

      cm.setValue(formatted);
    }

    // Mostrar notificação de sucesso
    function showSuccessNotification(message) {
      // Remover notificação anterior se existir
      $('.success-notification').remove();

      // Criar nova notificação
      var notification = $('<div class="success-notification">' +
        '<i class="fa fa-check-circle"></i>' +
        '<span>' + message + '</span>' +
        '</div>');

      // Adicionar ao body
      $('body').append(notification);

      // Remover automaticamente após 3 segundos
      setTimeout(function () {
        notification.remove();
      }, 3000);
    }

    // Funções do Preview
    function togglePreview() {
      if ($('#previewContainer').is(':visible')) {
        hidePreview();
      } else {
        showPreview();
      }
    }

    function showPreview() {
      if (!codeMirrorEditor) return;

      $('#previewContainer').show();
      $('#previewBtn').addClass('active').find('i').removeClass('fa-eye').addClass('fa-eye-slash');

      // Atualizar preview
      updatePreview();

      // Auto-atualizar preview quando o código mudar
      codeMirrorEditor.on('change', updatePreview);
    }

    function hidePreview() {
      $('#previewContainer').hide();
      $('#previewBtn').removeClass('active').find('i').removeClass('fa-eye-slash').addClass('fa-eye');

      // Remover listener de mudança
      if (codeMirrorEditor) {
        codeMirrorEditor.off('change', updatePreview);
      }
    }

    function updatePreview() {
      if (!codeMirrorEditor || !$('#previewContainer').is(':visible')) return;

      var content = codeMirrorEditor.getValue();
      var mode = codeMirrorEditor.getOption('mode');
      var filename = currentEditingFile || '';
      var ext = filename.toLowerCase().split('.').pop();

      var previewHtml = '';

      try {
        switch (mode) {
          case 'htmlmixed':
            // HTML Preview em iframe isolado
            var iframe = document.createElement('iframe');
            iframe.style.width = '100%';
            iframe.style.height = '400px';
            iframe.style.border = '1px solid #ddd';
            iframe.style.borderRadius = '4px';
            iframe.srcdoc = content;

            previewHtml = '<div style="margin-bottom:10px;">' +
              '<div class="alert alert-info" style="margin:0;padding:8px;font-size:12px;">' +
              '<i class="fa fa-info-circle"></i> Preview HTML renderizado' +
              '</div></div>';

            // Adicionar iframe após definir o HTML
            setTimeout(function () {
              $('#previewContent').append(iframe);
            }, 100);
            break;

          case 'xml':
            // XML Preview - mostrar como código formatado
            previewHtml = '<pre style="background:#f8f9fa;padding:15px;border-radius:4px;"><code>' +
              escapeHtml(content) + '</code></pre>';
            break;

          case 'markdown':
            // Markdown Preview (básico)
            previewHtml = renderMarkdown(content);
            break;

          case 'application/json':
          case 'javascript':
            if (ext === 'json') {
              // JSON Preview formatado
              try {
                var jsonObj = JSON.parse(content);
                previewHtml = '<pre style="background:#f8f9fa;padding:15px;border-radius:4px;"><code>' +
                  escapeHtml(JSON.stringify(jsonObj, null, 2)) + '</code></pre>';
              } catch (e) {
                previewHtml = '<div class="alert alert-danger">Erro no JSON: ' + e.message + '</div>';
              }
            } else {
              // JavaScript - mostrar código formatado
              previewHtml = '<pre style="background:#f8f9fa;padding:15px;border-radius:4px;"><code>' +
                escapeHtml(content) + '</code></pre>';
            }
            break;

          case 'css':
            // CSS Preview - aplicar estilos
            previewHtml = '<style>' + content + '</style>' +
              '<div class="alert alert-info">Estilos CSS aplicados ao preview. Adicione HTML para testar.</div>' +
              '<div style="padding:20px;border:1px solid #ddd;margin-top:10px;">' +
              '<h1>Título H1</h1><h2>Título H2</h2><p>Parágrafo de exemplo</p>' +
              '<button class="btn btn-primary">Botão</button></div>';
            break;

          default:
            // Outros tipos - mostrar como texto
            previewHtml = '<pre style="background:#f8f9fa;padding:15px;border-radius:4px;white-space:pre-wrap;"><code>' +
              escapeHtml(content) + '</code></pre>';
            break;
        }
      } catch (error) {
        previewHtml = '<div class="alert alert-danger">Erro no preview: ' + error.message + '</div>';
      }

      $('#previewContent').html(previewHtml);
    }

    // Renderizador básico de Markdown
    function renderMarkdown(markdown) {
      return markdown
        .replace(/^### (.*$)/gm, '<h3>$1</h3>')
        .replace(/^## (.*$)/gm, '<h2>$1</h2>')
        .replace(/^# (.*$)/gm, '<h1>$1</h1>')
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.*?)\*/g, '<em>$1</em>')
        .replace(/`(.*?)`/g, '<code>$1</code>')
        .replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>')
        .replace(/\n\n/g, '</p><p>')
        .replace(/^(.+)$/gm, '<p>$1</p>')
        .replace(/<p><\/p>/g, '')
        .replace(/<p>(<h[1-6]>.*<\/h[1-6]>)<\/p>/g, '$1')
        .replace(/<p>(<pre>.*<\/pre>)<\/p>/g, '$1');
    }

    // Função auxiliar para escapar HTML
    function escapeHtml(text) {
      var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      };
      return text.replace(/[&<>"']/g, function (m) { return map[m]; });
    }

    $(function () {
      // Event handlers para as opções do CodeMirror
      $('#languageSelect').on('change', function () {
        if (codeMirrorEditor) {
          codeMirrorEditor.setOption('mode', $(this).val());
          updateEditorInfo(); // Atualizar barra de status
        }
      });

      $('#themeSelect').on('change', function () {
        if (codeMirrorEditor) {
          codeMirrorEditor.setOption('theme', $(this).val());
        }
      });

      $('#fontSizeSelect').on('change', updateFontSize);

      $('#wrapLinesToggle').on('change', function () {
        if (codeMirrorEditor) {
          codeMirrorEditor.setOption('lineWrapping', $(this).is(':checked'));
        }
      });

      $('#showLineNumbers').on('change', function () {
        if (codeMirrorEditor) {
          codeMirrorEditor.setOption('lineNumbers', $(this).is(':checked'));
        }
      });

      // Botões da toolbar
      $('#searchBtn').on('click', function () {
        if (codeMirrorEditor) {
          CodeMirror.commands.findPersistent(codeMirrorEditor);
        }
      });

      $('#replaceBtn').on('click', function () {
        if (codeMirrorEditor) {
          CodeMirror.commands.replace(codeMirrorEditor);
        }
      });

      $('#gotoLineBtn').on('click', function () {
        if (codeMirrorEditor) {
          CodeMirror.commands.jumpToLine(codeMirrorEditor);
        }
      });

      // Botões de folding
      $('#foldBtn').on('click', function () {
        if (codeMirrorEditor) {
          codeMirrorEditor.foldCode(codeMirrorEditor.getCursor());
        }
      });

      $('#unfoldAllBtn').on('click', function () {
        if (codeMirrorEditor) {
          codeMirrorEditor.execCommand("unfoldAll");
        }
      });

      // Botão Salvar
      $('#saveTextBtn').off('click').on('click', function () {
        var file = $('#textEditModal').data('file');
        if (!file || !codeMirrorEditor) return alert('Arquivo inválido.');

        var content = codeMirrorEditor.getValue();
        $('#saveTextBtn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
        $('#textEditStatus').text('Salvando...');

        $.post('?', {
          do: 'savefile',
          file: file,
          content: content,
          xsrf: (document.cookie.match('(^|; )_sfm_xsrf=([^;]*)') || 0)[2]
        }, function (res) {
          $('#saveTextBtn').prop('disabled', false).html('<i class="fa fa-save"></i>');
          if (res && res.success) {
            $('#textEditStatus').html('<i class="fa fa-check"></i> Salvo com sucesso!').removeClass('text-danger').addClass('text-success');
            setTimeout(function () {
              $('#textEditStatus').text('').removeClass('text-success');
            }, 3000);

            // Mostrar notificação visual de sucesso
            var fileName = $('#textEditModal').data('file').split('/').pop();
            showSuccessNotification('Arquivo "' + fileName + '" salvo com sucesso!');

            // Marcar editor como limpo (sem alterações)
            if (codeMirrorEditor) {
              codeMirrorEditor.markClean();
            }
          } else {
            $('#textEditStatus').text('Erro ao salvar.').removeClass('text-success').addClass('text-danger');
            alert('Erro ao salvar arquivo: ' + (res && res.error ? res.error.msg : 'unknown'));
          }
        }, 'json').fail(function () {
          $('#saveTextBtn').prop('disabled', false).html('<i class="fa fa-save"></i>');
          $('#textEditStatus').text('Erro ao salvar.').removeClass('text-success').addClass('text-danger');
          alert('Falha na requisição para salvar o arquivo.');
        });
      });

      // Botão de Preview
      $('#previewBtn').on('click', function () {
        togglePreview();
      });

      $('#closePreviewBtn').on('click', function () {
        hidePreview();
      });

      // Botões de indentação
      $('#indentBtn').on('click', function () {
        if (codeMirrorEditor) {
          indentSelection(codeMirrorEditor);
        }
      });

      $('#unindentBtn').on('click', function () {
        if (codeMirrorEditor) {
          unindentSelection(codeMirrorEditor);
        }
      });

      $('#formatCodeBtn').on('click', function () {
        if (codeMirrorEditor) {
          formatCode(codeMirrorEditor);
        }
      });

      // Botões da barra de título estilo VS Code
      $('#minimizeBtn').on('click', function () {
        $('#textEditModal').fadeOut(200);
      });

      $('#maximizeBtn').on('click', function () {
        var modal = $('#textEditModal');
        if (modal.hasClass('maximized')) {
          modal.removeClass('maximized');
        } else {
          modal.addClass('maximized');
        }
      });

      // Fechar modal editor de texto
      $(document).on('click', '#textEditClose', function () {
        if (codeMirrorEditor && !codeMirrorEditor.isClean()) {
          if (!confirm('Há alterações não salvas. Deseja realmente fechar?')) {
            return;
          }
        }

        $('#textEditModal').fadeOut(120, function () {
          $(this).css('display', 'none');
        });

        if (codeMirrorEditor) {
          codeMirrorEditor.setValue('');
          codeMirrorEditor.markClean();
        }

        $('#textEditStatus').text('').removeClass('text-success text-danger');
        $('#currentFileName').text('');
        currentEditingFile = null;
      });
      // Fechar modal de preview de imagem (delegação)
      // Handler para fechar modal de imagem (legacy - agora controlado pela galeria)
      $(document).on('click', '#imagePreviewModalClose', function () {
        imageGallery.closeGallery();
      });

      // Fechar modal PDF (delegação)
      $(document).on('click', '#pdfViewModalClose', function () {
        $('#pdfViewModal').fadeOut(120, function () { $(this).css('display', 'none'); });
        $('#pdfViewModalIframe').attr('src', '');
      });

      // Fechar modal de mídia: pausa e limpa src (delegação)
      $(document).on('click', '#mediaModalClose', function () {
        var v = $('#mediaPlayerVideo')[0];
        var a = $('#mediaPlayerAudio')[0];
        try { if (v && !v.paused) v.pause(); } catch (e) { }
        try { if (a && !a.paused) a.pause(); } catch (e) { }
        $('#mediaPlayerVideo').attr('src', '').hide();
        $('#mediaPlayerAudio').attr('src', '').hide();
        $('#mediaModal').fadeOut(120, function () { $(this).css('display', 'none'); });
        $('#mediaTitle').text('');
      });
    });

    // Lixeira: abrir modal e listar itens
    $('#openTrashBtn').on('click', function () {
      $('#trashModal').modal('show');
      loadTrashList();
    });

    function updateTrashCount() {
      $.get('?do=listtrash', function (res) {
        if (res && res.success && res.results) {
          var count = res.results.length;
          var badge = $('#trashCountBadge');
          if (count > 0) {
            badge.text(count).show();
          } else {
            badge.hide();
          }
        }
      }, 'json');
    }

    function loadTrashList() {
      var $tbody = $('#trashList');
      $tbody.html('<tr><td colspan="5" class="text-center">Carregando...</td></tr>');
      $.get('?do=listtrash', function (res) {
        if (res && res.success && res.results && res.results.length) {
          var rows = res.results.map(function (item) {
            var name = $('<span/>').text(item.name).html();
            var tipo = item.is_dir ? 'Pasta de arquivos' : 'Arquivo';
            var original = $('<span/>').text(item.original).html();
            var date = item.deleted_at ? $('<span/>').text(item.deleted_at).html() : '-';
            var restoreBtn = '<button class="btn btn-success btn-sm me-1 restore-trash" data-trash="' + encodeURIComponent(item.trash_name) + '"><i class="fa fa-undo"></i> Restaurar</button>';
            var deleteBtn = '<button class="btn btn-danger btn-sm delete-trash" data-trash="' + encodeURIComponent(item.trash_name) + '"><i class="fa fa-trash"></i> Excluir</button>';
            return '<tr>' +
              '<td>' + name + '</td>' +
              '<td>' + tipo + '</td>' +
              // '<td>' + original + '</td>' +
              '<td>' + date + '</td>' +
              '<td>' + restoreBtn + deleteBtn + '</td>' +
              '</tr>';
          });
          $tbody.html(rows.join(''));
        } else {
          $tbody.html('<tr><td colspan="5" class="text-center">Lixeira vazia.</td></tr>');
        }
      }, 'json').fail(function () {
        $tbody.html('<tr><td colspan="4" class="text-danger text-center">Erro ao carregar lixeira.</td></tr>');
      });
    }

    // Restaurar item da lixeira
    $(document).on('click', '.restore-trash', function () {
      var trash = $(this).data('trash');
      if (!trash) return;
      if (!confirm('Restaurar este item?')) return;
      $.post('?', { do: 'restoretrash', trash: trash, xsrf: (document.cookie.match('(^|; )_sfm_xsrf=([^;]*)') || 0)[2] }, function (res) {
        if (res && res.success) {
          showTrashAlert('Item restaurado com sucesso.', 'success');
          loadTrashList();
        } else {
          showTrashAlert('Erro ao restaurar: ' + (res && res.error ? res.error.msg : 'unknown'), 'danger');
        }
      }, 'json').fail(function () {
        showTrashAlert('Falha na requisição.', 'danger');
      });
    });

    // Excluir permanentemente item da lixeira
    $(document).on('click', '.delete-trash', function () {
      var trash = $(this).data('trash');
      if (!trash) return;
      if (!confirm('Excluir permanentemente este item? Esta ação não pode ser desfeita.')) return;
      $.post('?', { do: 'deletetrash', trash: trash, xsrf: (document.cookie.match('(^|; )_sfm_xsrf=([^;]*)') || 0)[2] }, function (res) {
        if (res && res.success) {
          showTrashAlert('Item excluído permanentemente.', 'success');
          loadTrashList();
        } else {
          showTrashAlert('Erro ao excluir: ' + (res && res.error ? res.error.msg : 'unknown'), 'danger');
        }
      }, 'json').fail(function () {
        showTrashAlert('Falha na requisição.', 'danger');
      });
    });

    function showTrashAlert(msg, type) {
      var $alert = $('#trashAlert');
      $alert.removeClass().addClass('alert alert-' + type).text(msg).show();
      setTimeout(function () { $alert.fadeOut(); }, 2500);
    }

    // A atualização da lixeira após exclusão já é feita no handler principal de exclusão
    // Atualizar badge e lista ao excluir selecionados
    $('#deleteSelectedBtn').on('click', function () {
      setTimeout(function () {
        console.log('Atualizando lixeira após exclusão...');
        updateTrashCount();
        loadTrashList();
      }, 2000);
    });

    // ADDED: Sistema de compartilhamento de arquivos
    var ShareSystem = {
      currentFile: null,

      openShareModal: function (file) {
        this.currentFile = file;
        $('#shareFileName').text(file);
        $('#sharePassword').val('');
        $('#shareExpires').val('24');
        $('#shareMaxDownloads').val('0');
        $('#shareResult').hide();

        var modal = new bootstrap.Modal(document.getElementById('shareModal'));
        modal.show();
      },

      generateLink: function () {
        if (!this.currentFile) return;

        var password = $('#sharePassword').val().trim();
        var expires = parseInt($('#shareExpires').val());
        var maxDownloads = parseInt($('#shareMaxDownloads').val());

        var btn = $('#generateShareBtn');
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Gerando...');

        $.post('?', {
          do: 'generatesharelink',
          file: this.currentFile,
          password: password,
          expires_hours: expires,
          max_downloads: maxDownloads,
          xsrf: XSRF
        }, function (res) {
          if (res && res.success) {
            $('#shareUrl').val(res.share_url);

            var info = 'Expira em: ' + res.expires_at;
            if (res.has_password) {
              info += ' • Protegido por senha';
            }
            if (maxDownloads > 0) {
              info += ' • Máx. ' + maxDownloads + ' downloads';
            }
            $('#shareInfo').text(info);

            $('#shareResult').show();

            // Mostrar notificação
            showSuccessNotification('Link de compartilhamento gerado com sucesso!');
          } else {
            alert('Erro ao gerar link: ' + (res && res.error ? res.error.msg : 'Erro desconhecido'));
          }
        }, 'json').fail(function () {
          alert('Erro na comunicação com o servidor');
        }).always(function () {
          btn.prop('disabled', false).html('<i class="fa fa-share-alt"></i> Gerar Link');
        });
      },

      copyUrl: function () {
        var urlField = document.getElementById('shareUrl');
        urlField.select();
        urlField.setSelectionRange(0, 99999); // Para mobile

        try {
          document.execCommand('copy');
          showSuccessNotification('URL copiada para a área de transferência!');
        } catch (err) {
          // Fallback para browsers que não suportam execCommand
          if (navigator.clipboard) {
            navigator.clipboard.writeText(urlField.value).then(function () {
              showSuccessNotification('URL copiada para a área de transferência!');
            }).catch(function () {
              alert('Não foi possível copiar a URL automaticamente. Copie manualmente.');
            });
          } else {
            alert('Não foi possível copiar a URL automaticamente. Copie manualmente.');
          }
        }
      },

      loadShareLinks: function () {
        $('#shareLinksTable').html('<tr><td colspan="8" class="text-center">Carregando...</td></tr>');

        $.get('?do=listsharelinks', function (res) {
          if (res && res.success) {
            var tbody = $('#shareLinksTable');
            tbody.empty();

            if (res.shares && res.shares.length > 0) {
              res.shares.forEach(function (share) {
                // Debug: verificar dados do share
                //console.log('Processando share:', share);

                // Validar dados essenciais
                if (!share.id || !share.security_hash) {
                  console.error('Share com dados incompletos:', share);
                  return; // Pular este share
                }

                var statusBadge = share.expired ?
                  '<span class="badge bg-danger">Expirado</span>' :
                  '<span class="badge bg-success">Ativo</span>';

                var passwordBadge = share.has_password ?
                  '<i class="fa fa-lock text-warning" title="Protegido por senha"></i>' :
                  '<i class="fa fa-unlock text-muted" title="Sem senha"></i>';

                var downloadsText = share.max_downloads > 0 ?
                  share.downloads + '/' + share.max_downloads :
                  share.downloads + '/∞';

                // Escapar dados para evitar problemas de HTML
                var shareId = String(share.id).replace(/['"]/g, '');
                var securityHash = String(share.security_hash).replace(/['"]/g, '');

                // Gerar URL completa do link
                var protocol = window.location.protocol;
                var host = window.location.host;
                var pathname = window.location.pathname;
                var baseUrl = protocol + '//' + host + pathname.substring(0, pathname.lastIndexOf('/'));
                var shareUrl = baseUrl + '/share.php?id=' + shareId + '&hash=' + securityHash;

                // Criar campo com link selecionável
                var uniqueId = 'link_' + shareId;
                var linkHtml = '<div class="input-group input-group-md">' +
                  '<input type="text" class="form-control form-control-md" id="' + uniqueId + '" value="' + shareUrl + '" readonly onclick="this.select()" style="font-size: 11px;">' +
                  '<button class="btn btn-outline-success btn-md copy-url-btn" type="button" data-url="' + shareUrl + '" title="Copiar link">' +
                  '<i class="fa fa-copy"></i>' +
                  '</button>' +
                  '</div>';

                var deleteBtn = '<button class="btn btn-md btn-outline-danger delete-share" data-id="' +
                  shareId + '" title="Excluir link"><i class="fa fa-trash"></i></button>';

                var row = $('<tr>' +
                  '<td>' + share.file_path + '</td>' +
                  '<td>' + share.created_at + '</td>' +
                  '<td>' + share.expires_at + '</td>' +
                  '<td>' + statusBadge + '</td>' +
                  '<td>' + downloadsText + '</td>' +
                  '<td class="text-center">' + passwordBadge + '</td>' +
                  '<td style="min-width: 300px;">' + linkHtml + '</td>' +
                  '<td>' + deleteBtn + '</td>' +
                  '</tr>');

                tbody.append(row);
              });
            } else {
              tbody.html('<tr><td colspan="8" class="text-center text-muted">Nenhum link de compartilhamento encontrado</td></tr>');
            }
          } else {
            $('#shareLinksTable').html('<tr><td colspan="8" class="text-center text-danger">Erro ao carregar links</td></tr>');
          }
        }, 'json').fail(function () {
          $('#shareLinksTable').html('<tr><td colspan="8" class="text-center text-danger">Erro na comunicação com o servidor</td></tr>');
        });
      },

      deleteLink: function (shareId) {
        if (!confirm('Tem certeza que deseja excluir este link de compartilhamento?')) {
          return;
        }

        $.post('?', {
          do: 'deletesharelink',
          share_id: shareId,
          xsrf: XSRF
        }, function (res) {
          if (res && res.success) {
            showSuccessNotification('Link de compartilhamento excluído!');
            ShareSystem.loadShareLinks();
          } else {
            alert('Erro ao excluir link: ' + (res && res.error ? res.error.msg : 'Erro desconhecido'));
          }
        }, 'json').fail(function () {
          alert('Erro na comunicação com o servidor');
        });
      },

      copyShareLink: function (shareId, securityHash) {
        console.log('copyShareLink chamado com:', { shareId: shareId, securityHash: securityHash });

        if (!shareId || !securityHash) {
          alert('Erro: Parâmetros inválidos para copiar link');
          return;
        }

        // Gerar URL completa
        var protocol = window.location.protocol;
        var host = window.location.host;
        var pathname = window.location.pathname;
        var baseUrl = protocol + '//' + host + pathname.substring(0, pathname.lastIndexOf('/'));

        var shareUrl = baseUrl + '/share.php?id=' + shareId + '&hash=' + securityHash;

        console.log('URL gerada:', shareUrl);

        // Método 1: Tentar API moderna do clipboard
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
          console.log('Tentando API moderna do clipboard...');
          navigator.clipboard.writeText(shareUrl)
            .then(function () {
              console.log('Sucesso com API moderna');
              showSuccessNotification('Link copiado para a área de transferência!');
            })
            .catch(function (err) {
              console.log('Falha na API moderna:', err);
              ShareSystem.copyWithFallback(shareUrl);
            });
        } else {
          console.log('API moderna não disponível, usando fallback');
          ShareSystem.copyWithFallback(shareUrl);
        }
      },

      copyWithFallback: function (url) {
        console.log('Executando copyWithFallback para:', url);

        // Criar input temporário
        var tempInput = document.createElement('input');
        tempInput.type = 'text';
        tempInput.value = url;
        tempInput.style.position = 'fixed';
        tempInput.style.left = '-9999px';
        tempInput.style.top = '-9999px';
        tempInput.style.opacity = '0';

        document.body.appendChild(tempInput);

        try {
          // Selecionar o texto
          tempInput.focus();
          tempInput.select();
          tempInput.setSelectionRange(0, 99999);

          // Tentar copiar
          var success = document.execCommand('copy');

          if (success) {
            console.log('Sucesso com execCommand');
            showSuccessNotification('Link copiado para a área de transferência!');
          } else {
            console.log('execCommand falhou, mostrando prompt');
            this.showCopyPrompt(url);
          }

        } catch (err) {
          console.log('Erro no execCommand:', err);
          this.showCopyPrompt(url);
        } finally {
          // Limpar
          if (tempInput && tempInput.parentNode) {
            document.body.removeChild(tempInput);
          }
        }
      },

      showCopyPrompt: function (url) {
        // Criar modal personalizado para cópia manual
        var modal = $('<div class="modal fade" tabindex="-1">' +
          '<div class="modal-dialog">' +
          '<div class="modal-content">' +
          '<div class="modal-header">' +
          '<h5 class="modal-title"><i class="fa fa-copy"></i> Copiar Link</h5>' +
          '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>' +
          '</div>' +
          '<div class="modal-body">' +
          '<p>Copie o link abaixo:</p>' +
          '<div class="input-group">' +
          '<input type="text" class="form-control" id="copyUrlInput" value="' + url + '" readonly>' +
          '<button class="btn btn-primary" type="button" onclick="document.getElementById(\'copyUrlInput\').select(); document.execCommand(\'copy\'); showSuccessNotification(\'Link copiado!\');">' +
          '<i class="fa fa-copy"></i> Copiar' +
          '</button>' +
          '</div>' +
          '</div>' +
          '</div>' +
          '</div>' +
          '</div>');

        $('body').append(modal);
        modal.modal('show');

        // Remover modal ao fechar
        modal.on('hidden.bs.modal', function () {
          modal.remove();
        });

        // Auto-selecionar o texto
        modal.on('shown.bs.modal', function () {
          $('#copyUrlInput').select();
        });
      }
    };



    // Event handlers para o sistema de compartilhamento
    $(function () {
      // Debug: Verificar se jQuery e ShareSystem estão carregados
      // console.log('jQuery carregado:', typeof $ !== 'undefined');
      // console.log('ShareSystem carregado:', typeof ShareSystem !== 'undefined');

      // Event handler para copiar URL do link de compartilhamento
      $(document).on('click', '.copy-url-btn', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var url = $btn.data('url');
        var $icon = $btn.find('i');

        // Feedback visual
        var originalClass = $icon.attr('class');
        $icon.attr('class', 'fa fa-spinner fa-spin');
        $btn.prop('disabled', true);

        // Tentar copiar
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
          navigator.clipboard.writeText(url)
            .then(function () {
              showSuccessNotification('Link copiado para a área de transferência!');
            })
            .catch(function () {
              // Fallback
              copyToClipboardFallback(url);
            })
            .finally(function () {
              // Restaurar botão
              setTimeout(function () {
                $icon.attr('class', originalClass);
                $btn.prop('disabled', false);
              }, 1000);
            });
        } else {
          copyToClipboardFallback(url);
          setTimeout(function () {
            $icon.attr('class', originalClass);
            $btn.prop('disabled', false);
          }, 1000);
        }
      });

      // Função de fallback para copiar
      function copyToClipboardFallback(text) {
        var tempInput = document.createElement('input');
        tempInput.type = 'text';
        tempInput.value = text;
        tempInput.style.position = 'fixed';
        tempInput.style.left = '-9999px';
        document.body.appendChild(tempInput);
        tempInput.select();
        tempInput.setSelectionRange(0, 99999);

        try {
          var success = document.execCommand('copy');
          if (success) {
            showSuccessNotification('Link copiado para a área de transferência!');
          } else {
            prompt('Copie o link abaixo (Ctrl+C):', text);
          }
        } catch (err) {
          prompt('Copie o link abaixo (Ctrl+C):', text);
        } finally {
          document.body.removeChild(tempInput);
        }
      }

      // Botão de compartilhar arquivo
      $(document).on('click', '.share-file', function (e) {
        e.preventDefault();
        var file = $(this).data('file');
        if (file) {
          ShareSystem.openShareModal(file);
        }
      });

      // Gerar link de compartilhamento
      $('#generateShareBtn').on('click', function () {
        ShareSystem.generateLink();
      });

      // Copiar URL
      $('#copyShareUrl').on('click', function () {
        ShareSystem.copyUrl();
      });

      // Abrir modal de gerenciamento de links
      $('#shareLinksBtn').on('click', function () {
        var modal = new bootstrap.Modal(document.getElementById('shareLinksModal'));
        modal.show();
        ShareSystem.loadShareLinks();
      });

      // Atualizar lista de links
      $('#refreshShareLinks').on('click', function () {
        ShareSystem.loadShareLinks();
      });

      // Excluir link de compartilhamento
      $(document).on('click', '.delete-share', function () {
        var shareId = $(this).data('id');
        ShareSystem.deleteLink(shareId);
      });


    });

    // Floating Audio Player - Sistema completo de reprodução de áudio
    var FloatingAudioPlayer = {
      playlist: [],
      currentTrackIndex: -1,
      isPlaying: false,
      isMinimized: false,
      isRepeat: false,
      isShuffle: false,
      currentVolume: 100,
      isMuted: false,
      audio: null,

      init: function () {
        this.audio = document.getElementById('floatingAudioElement');
        this.bindEvents();
        this.updateDisplay();
      },

      bindEvents: function () {
        var self = this;

        // Controles principais
        $('#playPauseBtn').on('click', function () { self.togglePlayPause(); });
        $('#prevBtn').on('click', function () { self.previousTrack(); });
        $('#nextBtn').on('click', function () { self.nextTrack(); });
        $('#repeatBtn').on('click', function () { self.toggleRepeat(); });
        $('#shuffleBtn').on('click', function () { self.toggleShuffle(); });

        // Controles do header
        $('#togglePlayerBtn').on('click', function () { self.toggleMinimize(); });
        $('#closePlayerBtn').on('click', function () { self.hide(); });
        $('.floating-player-header').on('dblclick', function () { self.toggleMinimize(); });

        // Volume
        $('#muteBtn').on('click', function () { self.toggleMute(); });
        $('#volumeSlider').on('input', function () { self.setVolume($(this).val()); });

        // Barra de progresso
        $('#progressBar').on('click', function (e) { self.seekTo(e); });

        // Playlist
        $('#clearPlaylistBtn').on('click', function () { self.clearPlaylist(); });

        // Eventos do audio element
        this.audio.addEventListener('loadedmetadata', function () { self.updateDisplay(); });
        this.audio.addEventListener('timeupdate', function () { self.updateProgress(); });
        this.audio.addEventListener('ended', function () { self.onTrackEnded(); });
        this.audio.addEventListener('error', function () { self.onError(); });

        // Atalhos do teclado (quando o player estiver visível)
        $(document).on('keydown', function (e) {
          if ($('#floatingAudioPlayer').is(':visible')) {
            switch (e.which) {
              case 32: // Spacebar
                e.preventDefault();
                self.togglePlayPause();
                break;
              case 37: // Left Arrow
                self.seekRelative(-10);
                break;
              case 39: // Right Arrow
                self.seekRelative(10);
                break;
              case 38: // Up Arrow
                e.preventDefault();
                self.setVolume(Math.min(100, self.currentVolume + 10));
                break;
              case 40: // Down Arrow
                e.preventDefault();
                self.setVolume(Math.max(0, self.currentVolume - 10));
                break;
            }
          }
        });

        // Auto-minimizar ao navegar por pastas (não ocultar completamente)
        $(window).on('hashchange', function () {
          if ($('#floatingAudioPlayer').is(':visible') && !self.isMinimized) {
            self.toggleMinimize();
          }
        });
      },

      show: function () {
        $('#floatingAudioPlayer').addClass('show').show();
      },

      hide: function () {
        this.stop();
        $('#floatingAudioPlayer').removeClass('show').hide();
      },

      toggleMinimize: function () {
        this.isMinimized = !this.isMinimized;
        $('#floatingAudioPlayer').toggleClass('minimized', this.isMinimized);
        $('#togglePlayerBtn i').toggleClass('fa-chevron-down fa-chevron-up');

        // Salvar estado no localStorage
        localStorage.setItem('audioPlayerMinimized', this.isMinimized);
      },

      addToPlaylist: function (name, src) {
        // Verificar se já existe na playlist
        var exists = this.playlist.find(function (track) { return track.src === src; });
        if (exists) return;

        this.playlist.push({
          name: name,
          src: src,
          displayName: name.split('/').pop()
        });

        this.updatePlaylistDisplay();
      },

      // Função para adicionar todos os arquivos de áudio da pasta atual
      addCurrentFolderAudios: function () {
        var audioExts = ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac'];
        var self = this;

        // Buscar todos os arquivos de áudio na tabela/grid atual
        $('.view-audio').each(function () {
          var file = $(this).attr('data-file');
          if (file) {
            var ext = file.split('.').pop().toLowerCase();
            if (audioExts.includes(ext)) {
              var src = './' + file;
              self.addToPlaylist(file, src);
            }
          }
        });

        if (this.playlist.length > 0) {
          this.updatePlaylistDisplay();
          this.show();
        }
      },

      removeFromPlaylist: function (index) {
        if (index >= 0 && index < this.playlist.length) {
          // Se remover a faixa atual, parar reprodução
          if (index === this.currentTrackIndex) {
            this.stop();
          } else if (index < this.currentTrackIndex) {
            this.currentTrackIndex--;
          }

          this.playlist.splice(index, 1);
          this.updatePlaylistDisplay();
        }
      },

      clearPlaylist: function () {
        this.stop();
        this.playlist = [];
        this.currentTrackIndex = -1;
        this.updatePlaylistDisplay();
        this.updateDisplay();
      },

      playTrack: function (nameOrIndex) {
        var index;

        if (typeof nameOrIndex === 'string') {
          index = this.playlist.findIndex(function (track) { return track.name === nameOrIndex; });
        } else {
          index = nameOrIndex;
        }

        if (index >= 0 && index < this.playlist.length) {
          this.currentTrackIndex = index;
          var track = this.playlist[index];

          this.audio.src = track.src;
          this.audio.load();

          var self = this;
          this.audio.play().then(function () {
            self.isPlaying = true;
            self.updateDisplay();
            self.updatePlaylistDisplay();
          }).catch(function (error) {
            console.error('Erro ao reproduzir áudio:', error);
            self.onError();
          });
        }
      },

      togglePlayPause: function () {
        if (this.playlist.length === 0) return;

        if (this.audio.paused) {
          if (this.currentTrackIndex === -1) {
            this.playTrack(0);
          } else {
            this.audio.play();
            this.isPlaying = true;
          }
        } else {
          this.audio.pause();
          this.isPlaying = false;
        }

        this.updateDisplay();
      },

      stop: function () {
        this.audio.pause();
        this.audio.currentTime = 0;
        this.isPlaying = false;
        this.updateDisplay();
      },

      previousTrack: function () {
        if (this.playlist.length === 0) return;

        var newIndex;
        if (this.isShuffle) {
          newIndex = Math.floor(Math.random() * this.playlist.length);
        } else {
          newIndex = this.currentTrackIndex > 0 ? this.currentTrackIndex - 1 : this.playlist.length - 1;
        }

        this.playTrack(newIndex);
      },

      nextTrack: function () {
        if (this.playlist.length === 0) return;

        var newIndex;
        if (this.isShuffle) {
          newIndex = Math.floor(Math.random() * this.playlist.length);
        } else {
          newIndex = this.currentTrackIndex < this.playlist.length - 1 ? this.currentTrackIndex + 1 : 0;
        }

        this.playTrack(newIndex);
      },

      onTrackEnded: function () {
        if (this.isRepeat) {
          this.audio.currentTime = 0;
          this.audio.play();
        } else {
          this.nextTrack();
        }
      },

      onError: function () {
        console.error('Erro no player de áudio');
        this.isPlaying = false;
        this.updateDisplay();

        // Tentar próxima faixa se houver
        if (this.playlist.length > 1) {
          setTimeout(() => this.nextTrack(), 1000);
        }
      },

      toggleRepeat: function () {
        this.isRepeat = !this.isRepeat;
        $('#repeatBtn').toggleClass('active', this.isRepeat);

        // Se shuffle estiver ativo, desativar
        if (this.isRepeat && this.isShuffle) {
          this.toggleShuffle();
        }
      },

      toggleShuffle: function () {
        this.isShuffle = !this.isShuffle;
        $('#shuffleBtn').toggleClass('active', this.isShuffle);

        // Se repeat estiver ativo, desativar
        if (this.isShuffle && this.isRepeat) {
          this.toggleRepeat();
        }
      },

      setVolume: function (volume) {
        this.currentVolume = Math.max(0, Math.min(100, volume));
        this.audio.volume = this.currentVolume / 100;
        $('#volumeSlider').val(this.currentVolume);

        // Atualizar ícone do volume
        var icon = 'fa-volume-up';
        if (this.currentVolume === 0 || this.isMuted) {
          icon = 'fa-volume-off';
        } else if (this.currentVolume < 50) {
          icon = 'fa-volume-down';
        }

        $('#muteBtn i').removeClass('fa-volume-off fa-volume-down fa-volume-up').addClass(icon);
      },

      toggleMute: function () {
        this.isMuted = !this.isMuted;

        if (this.isMuted) {
          this.audio.volume = 0;
          $('#muteBtn i').removeClass('fa-volume-up fa-volume-down').addClass('fa-volume-off');
        } else {
          this.audio.volume = this.currentVolume / 100;
          this.setVolume(this.currentVolume);
        }
      },

      seekTo: function (e) {
        if (this.audio.duration) {
          var rect = e.currentTarget.getBoundingClientRect();
          var percent = (e.clientX - rect.left) / rect.width;
          this.audio.currentTime = percent * this.audio.duration;
        }
      },

      seekRelative: function (seconds) {
        if (this.audio.duration) {
          this.audio.currentTime = Math.max(0, Math.min(this.audio.duration, this.audio.currentTime + seconds));
        }
      },

      updateProgress: function () {
        if (this.audio.duration) {
          var percent = (this.audio.currentTime / this.audio.duration) * 100;
          $('#progressFill').css('width', percent + '%');

          $('#currentTime').text(this.formatTime(this.audio.currentTime));
          $('#totalTime').text(this.formatTime(this.audio.duration));
        }
      },

      updateDisplay: function () {
        // Atualizar botão play/pause
        if (this.isPlaying && !this.audio.paused) {
          $('#playPauseBtn i').removeClass('fa-play').addClass('fa-pause');
        } else {
          $('#playPauseBtn i').removeClass('fa-pause').addClass('fa-play');
        }

        // Atualizar informações da faixa
        if (this.currentTrackIndex >= 0 && this.currentTrackIndex < this.playlist.length) {
          var track = this.playlist[this.currentTrackIndex];
          $('#currentTrackName').text(track.displayName);
          $('#trackDisplayName').text(track.displayName);
        } else {
          $('#currentTrackName').text('Nenhuma música');
          $('#trackDisplayName').text('Nenhuma música selecionada');
        }

        // Atualizar tempos
        if (this.audio.duration) {
          $('#totalTime').text(this.formatTime(this.audio.duration));
        } else {
          $('#currentTime').text('0:00');
          $('#totalTime').text('0:00');
        }
      },

      updatePlaylistDisplay: function () {
        var $playlist = $('#playlist');
        $playlist.empty();

        if (this.playlist.length === 0) {
          $playlist.append('<div style="text-align:center;padding:20px;opacity:0.6;">Nenhuma música na playlist</div>');
          return;
        }

        var self = this;
        this.playlist.forEach(function (track, index) {
          var $item = $('<div class="playlist-item">')
            .toggleClass('active', index === self.currentTrackIndex);

          var $name = $('<div class="playlist-item-name">').text(track.displayName);
          var $remove = $('<div class="playlist-item-remove" title="Remover">').html('<i class="fa fa-times"></i>');

          $item.append($name).append($remove);

          $name.on('click', function () { self.playTrack(index); });
          $remove.on('click', function (e) {
            e.stopPropagation();
            self.removeFromPlaylist(index);
          });

          $playlist.append($item);
        });

        // Mostrar/ocultar container de playlist
        if (this.playlist.length > 1) {
          $('#playlistContainer').show();
        } else {
          $('#playlistContainer').hide();
        }
      },

      formatTime: function (seconds) {
        if (isNaN(seconds)) return '0:00';

        var mins = Math.floor(seconds / 60);
        var secs = Math.floor(seconds % 60);
        return mins + ':' + (secs < 10 ? '0' : '') + secs;
      }
    };

    // Inicializar o player quando o documento estiver pronto
    $(function () {
      FloatingAudioPlayer.init();

      // Restaurar estado minimizado
      var wasMinimized = localStorage.getItem('audioPlayerMinimized') === 'true';
      if (wasMinimized) {
        FloatingAudioPlayer.isMinimized = true;
        $('#floatingAudioPlayer').addClass('minimized');
        $('#togglePlayerBtn i').removeClass('fa-chevron-down').addClass('fa-chevron-up');
      }

      // Botões na navbar para controlar o player
      $('#toggleAudioPlayerBtn').on('click', function () {
        if ($('#floatingAudioPlayer').is(':visible')) {
          FloatingAudioPlayer.hide();
        } else {
          FloatingAudioPlayer.show();
        }
        updateNavbarPlayerButton();
      });

      $('#addAllAudiosBtn').on('click', function () {
        FloatingAudioPlayer.addCurrentFolderAudios();
      });

      // Função para atualizar os botões do player na navbar
      function updateNavbarPlayerButton() {
        var isVisible = $('#floatingAudioPlayer').is(':visible');
        var hasPlaylist = FloatingAudioPlayer.playlist.length > 0;
        var isPlaying = FloatingAudioPlayer.isPlaying && !FloatingAudioPlayer.audio.paused;
        var hasAudiosInFolder = $('.view-audio').length > 0;

        // Botão para adicionar áudios da pasta
        $('#addAllAudiosBtn').toggle(hasAudiosInFolder);

        // Botão do player (só aparece se houver playlist)
        if (hasPlaylist) {
          $('#toggleAudioPlayerBtn').show();

          if (isPlaying) {
            $('#audioPlayerStatusText').text('Tocando');
          } else if (isVisible) {
            $('#audioPlayerStatusText').text('Player');
          } else {
            $('#audioPlayerStatusText').text('Player');
          }
        }
      }

      // Sobrescrever algumas funções do player para atualizar o botão da navbar
      var originalShow = FloatingAudioPlayer.show;
      FloatingAudioPlayer.show = function () {
        originalShow.call(this);
        updateNavbarPlayerButton();
      };

      var originalHide = FloatingAudioPlayer.hide;
      FloatingAudioPlayer.hide = function () {
        originalHide.call(this);
        updateNavbarPlayerButton();
      };

      var originalUpdateDisplay = FloatingAudioPlayer.updateDisplay;
      FloatingAudioPlayer.updateDisplay = function () {
        originalUpdateDisplay.call(this);
        updateNavbarPlayerButton();
      };

      var originalAddToPlaylist = FloatingAudioPlayer.addToPlaylist;
      FloatingAudioPlayer.addToPlaylist = function (name, src) {
        originalAddToPlaylist.call(this, name, src);
        updateNavbarPlayerButton();
      };

      var originalClearPlaylist = FloatingAudioPlayer.clearPlaylist;
      FloatingAudioPlayer.clearPlaylist = function () {
        originalClearPlaylist.call(this);
        updateNavbarPlayerButton();
      };
    });

    // ADDED: Funções da galeria de imagens
    var imageGallery = {
      images: [],
      currentIndex: 0,
      isZoomed: false,

      init: function () {
        this.bindEvents();
      },

      bindEvents: function () {
        var self = this;

        // Fechar modal
        $('#imagePreviewModalClose').on('click', function () {
          self.closeGallery();
        });

        // Navegação
        $('#prevImageBtn').on('click', function () {
          self.showPrevious();
        });

        $('#nextImageBtn').on('click', function () {
          self.showNext();
        });

        // Zoom
        $('#zoomToggleBtn').on('click', function () {
          self.toggleZoom();
        });

        // Download da imagem atual
        $('#downloadCurrentBtn').on('click', function () {
          if (self.images.length > 0) {
            var currentImage = self.images[self.currentIndex];
            window.open('?do=download&file=' + encodeURIComponent(currentImage.path), '_blank');
          }
        });

        // Navegação por teclado
        $(document).on('keydown', function (e) {
          if ($('#imagePreviewModal').is(':visible')) {
            switch (e.keyCode) {
              case 27: // ESC
                self.closeGallery();
                break;
              case 37: // Seta esquerda
                self.showPrevious();
                break;
              case 39: // Seta direita
                self.showNext();
                break;
              case 32: // Espaço
                e.preventDefault();
                self.toggleZoom();
                break;
            }
          }
        });

        // Clique na imagem para zoom
        $('#imagePreviewModalImg').on('click', function () {
          self.toggleZoom();
        });

        // Fechar modal clicando no fundo
        $('#imagePreviewModal').on('click', function (e) {
          if (e.target === this) {
            self.closeGallery();
          }
        });
      },

      loadImagesFromCurrentFolder: function () {
        var imageExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
        this.images = [];

        // Coletar imagens da lista atual (tanto tabela quanto grid)
        $('.view-image').each(function () {
          var file = $(this).attr('data-file');
          var fileName = file.split('/').pop();
          var ext = fileName.split('.').pop().toLowerCase();

          if (imageExts.includes(ext)) {
            imageGallery.images.push({
              path: file,
              name: fileName,
              src: './' + file
            });
          }
        });

        // Ordenar por nome
        this.images.sort(function (a, b) {
          return a.name.localeCompare(b.name);
        });
      },

      openGallery: function (selectedFile) {
        this.loadImagesFromCurrentFolder();

        if (this.images.length === 0) {
          alert('Nenhuma imagem encontrada na pasta atual.');
          return;
        }

        // Encontrar o índice da imagem selecionada
        this.currentIndex = this.images.findIndex(function (img) {
          return img.path === selectedFile;
        });

        if (this.currentIndex === -1) {
          this.currentIndex = 0;
        }

        this.showImage();
        this.updateThumbnails();
        $('#imagePreviewModal').fadeIn();
      },

      showImage: function () {
        if (this.images.length === 0) return;

        var currentImage = this.images[this.currentIndex];

        // Atualizar imagem principal
        $('#imagePreviewModalImg').attr('src', currentImage.src);

        // Atualizar informações
        $('#imageTitle').text(currentImage.name);
        $('#imageCounter').text((this.currentIndex + 1) + ' de ' + this.images.length);

        // Resetar zoom
        this.isZoomed = false;
        $('#imagePreviewModalImg').removeClass('zoomed');
        $('#zoomToggleBtn i').removeClass('fa-search-minus').addClass('fa-search-plus');

        // Atualizar thumbnails ativas
        $('.thumbnail-item').removeClass('active');
        $('.thumbnail-item[data-index="' + this.currentIndex + '"]').addClass('active');

        // Scroll thumbnail para visível
        this.scrollThumbnailIntoView();

        // Atualizar visibilidade dos botões de navegação
        $('#prevImageBtn').toggle(this.images.length > 1);
        $('#nextImageBtn').toggle(this.images.length > 1);
      },

      showPrevious: function () {
        if (this.images.length <= 1) return;
        this.currentIndex = this.currentIndex > 0 ? this.currentIndex - 1 : this.images.length - 1;
        this.showImage();
      },

      showNext: function () {
        if (this.images.length <= 1) return;
        this.currentIndex = this.currentIndex < this.images.length - 1 ? this.currentIndex + 1 : 0;
        this.showImage();
      },

      toggleZoom: function () {
        var $img = $('#imagePreviewModalImg');
        this.isZoomed = !this.isZoomed;

        if (this.isZoomed) {
          $img.addClass('zoomed');
          $('#zoomToggleBtn i').removeClass('fa-search-plus').addClass('fa-search-minus');
        } else {
          $img.removeClass('zoomed');
          $('#zoomToggleBtn i').removeClass('fa-search-minus').addClass('fa-search-plus');
        }
      },

      updateThumbnails: function () {
        var $container = $('#imageThumbnailGallery');
        $container.empty();

        var self = this;
        this.images.forEach(function (image, index) {
          var $thumb = $('<img>')
            .addClass('thumbnail-item')
            .attr('src', image.src)
            .attr('data-index', index)
            .attr('title', image.name)
            .on('click', function () {
              self.currentIndex = index;
              self.showImage();
            });

          if (index === self.currentIndex) {
            $thumb.addClass('active');
          }

          $container.append($thumb);
        });

        // Mostrar/ocultar galeria de thumbnails
        $container.toggle(this.images.length > 1);
      },

      scrollThumbnailIntoView: function () {
        var $container = $('#imageThumbnailGallery');
        var $activeThumb = $('.thumbnail-item.active');

        if ($activeThumb.length > 0) {
          var containerWidth = $container.width();
          var thumbLeft = $activeThumb.position().left;
          var thumbWidth = $activeThumb.outerWidth();
          var scrollLeft = $container.scrollLeft();

          if (thumbLeft < 0) {
            $container.scrollLeft(scrollLeft + thumbLeft - 20);
          } else if (thumbLeft + thumbWidth > containerWidth) {
            $container.scrollLeft(scrollLeft + thumbLeft + thumbWidth - containerWidth + 20);
          }
        }
      },

      closeGallery: function () {
        $('#imagePreviewModal').fadeOut();
        $('#imagePreviewModalImg').attr('src', '');
        this.isZoomed = false;
      }
    };

    // Função global para abrir galeria
    function openImageGallery(selectedFile) {
      imageGallery.openGallery(selectedFile);
    }

    // Inicializar galeria quando documento estiver pronto
    $(function () {
      imageGallery.init();
    });

    // ADDED: Função para abrir modal de visualização ZIP
    function openZipViewer(file) {
      $.get('?do=listzip&file=' + encodeURIComponent(file), null, 'json')
        .done(function (data) {
          console.log('Resposta do servidor:', data); // Debug
          if (data && data.success) {
            $('#zipFileName').text(data.zip_file);
            $('#zipFileCount').text(data.total_files);

            // Sugerir nome da pasta baseado no nome do arquivo ZIP
            const zipName = data.zip_file.split('/').pop().replace('.zip', '');
            $('#extractFolderName').val(zipName + '_extracted');

            // Preencher lista de arquivos
            let html = '';
            data.files.forEach(function (file) {
              const icon = file.is_dir ? 'fa-folder' : 'fa-file-o';
              const sizeText = file.is_dir ? '-' : formatBytes(file.size);
              const compressedText = file.is_dir ? '-' : formatBytes(file.compressed_size);

              html += `
            <tr>
              <td>
                <i class="fa ${icon}"></i> 
                ${escapeHtml(file.name)}
              </td>
              <td>${file.modified}</td>
              <td>${sizeText}</td>
              <td>${compressedText}</td>
            </tr>
          `;
            });

            $('#zipFilesList').html(html);
            $('#zipViewModal').modal('show');

            // Configurar botão de extração
            $('#extractZipBtn').off('click').on('click', function () {
              extractZip(data.zip_file);
            });

          } else {
            console.log('Erro na resposta do servidor:', data); // Debug
            alert('Erro ao carregar conteúdo do ZIP: ' + (data && data.error ? data.error.msg : 'Erro desconhecido'));
          }
        })
        .fail(function (xhr, status, error) {
          // Tenta extrair mensagem de erro do JSON retornado
          var msg = error;
          if (xhr && xhr.responseText) {
            try {
              var data = JSON.parse(xhr.responseText);
              if (data && data.error && data.error.msg) {
                msg = data.error.msg;
              }
            } catch (e) {
              msg = xhr.responseText;
            }
          }
          alert('Erro ao comunicar com o servidor: ' + msg);
        });
    }

    // ADDED: Função para extrair ZIP
    function extractZip(zipFile) {
      const folderName = $('#extractFolderName').val().trim();

      if (!folderName) {
        alert('Por favor, digite um nome para a pasta de extração.');
        return;
      }

      const btn = $('#extractZipBtn');
      const originalText = btn.html();

      btn.html('<i class="fa fa-spinner fa-spin"></i> Extraindo...').prop('disabled', true);

      $.post('', {
        do: 'extractzip',
        file: zipFile,
        extract_to: folderName,
        xsrf: token
      }, null, 'json')
        .done(function (data) {
          console.log('Resposta extração:', data); // Debug
          if (data && data.success) {
            alert(`ZIP extraído com sucesso!\n${data.extracted} arquivos extraídos para: ${data.extract_path}`);
            $('#zipViewModal').modal('hide');
            // Recarregar lista de arquivos
            if (typeof list === 'function') {
              list();
            } else {
              window.location.reload();
            }
          } else {
            console.log('Erro na extração:', data); // Debug
            alert('Erro ao extrair ZIP: ' + (data && data.error ? data.error.msg : 'Erro desconhecido'));
          }
        })
        .fail(function (xhr, status, error) {
          // Tenta extrair mensagem de erro do JSON retornado
          var msg = error;
          if (xhr && xhr.responseText) {
            try {
              var data = JSON.parse(xhr.responseText);
              if (data && data.error && data.error.msg) {
                msg = data.error.msg;
              }
            } catch (e) {
              msg = xhr.responseText;
            }
          }
          alert('Erro ao processar: ' + msg);
        })
        .always(function () {
          btn.html(originalText).prop('disabled', false);
        });
    }

    // ADDED: Função auxiliar para formatar bytes
    function formatBytes(bytes, decimals = 2) {
      if (bytes === 0) return '0 B';
      const k = 1024;
      const dm = decimals < 0 ? 0 : decimals;
      const sizes = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    // ADDED: Função auxiliar para escape HTML
    function escapeHtml(text) {
      const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      };
      return text.replace(/[&<>"']/g, function (m) { return map[m]; });
    }
  </script>

  <!-- ADDED: Modal para gerar link de compartilhamento -->
  <div class="modal fade" id="shareModal" tabindex="-1" aria-labelledby="shareModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="shareModalLabel">
            <i class="fa fa-share-alt"></i> Gerar Link de Compartilhamento
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label"><strong>Arquivo:</strong></label>
            <div id="shareFileName" class="form-control-plaintext text-success fw-bold"></div>
          </div>

          <div class="mb-3">
            <label for="sharePassword" class="form-label">Senha (opcional)</label>
            <input type="password" class="form-control" id="sharePassword"
              placeholder="Deixe em branco para link sem senha">
            <div class="form-text text-warning">Se definida, será necessário inserir esta senha para acessar o arquivo.
            </div>
          </div>

          <div class="mb-3">
            <label for="shareExpires" class="form-label">Expiração</label>
            <select class="form-select" id="shareExpires">
              <option value="1">1 hora</option>
              <option value="6">6 horas</option>
              <option value="24" selected>24 horas</option>
              <option value="72">3 dias</option>
              <option value="168">7 dias</option>
              <option value="720">30 dias</option>
            </select>
          </div>

          <div class="mb-3">
            <label for="shareMaxDownloads" class="form-label">Limite de downloads</label>
            <select class="form-select" id="shareMaxDownloads">
              <option value="0" selected>Ilimitado</option>
              <option value="1">1 download</option>
              <option value="5">5 downloads</option>
              <option value="10">10 downloads</option>
              <option value="50">50 downloads</option>
            </select>
          </div>

          <div id="shareResult" style="display:none;" class="mt-3">
            <div class="alert alert-success">
              <h6><i class="fa fa-check-circle"></i> Link criado com sucesso!</h6>
              <div class="mb-2">
                <label class="form-label">URL de Compartilhamento:</label>
                <div class="input-group">
                  <input type="text" class="form-control" id="shareUrl" readonly>
                  <button class="btn btn-outline-secondary" type="button" id="copyShareUrl">
                    <i class="fa fa-copy"></i> Copiar
                  </button>
                </div>
              </div>
              <div class="small text-muted" id="shareInfo"></div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary" id="generateShareBtn">
            <i class="fa fa-share-alt"></i> Gerar Link
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ADDED: Modal para gerenciar links de compartilhamento -->
  <div class="modal fade" id="shareLinksModal" tabindex="-1" aria-labelledby="shareLinksModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="shareLinksModalLabel">
            <i class="fa fa-share-alt"></i> Gerenciar Links de Compartilhamento
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h6>Links Ativos</h6>
            <button class="btn btn-outline-primary" id="refreshShareLinks">
              <i class="fa fa-refresh"></i> Atualizar
            </button>
          </div>

          <div class="table-responsive">
            <table class="table table-striped">
              <thead class="table-dark">
                <tr>
                  <th>Arquivo</th>
                  <th>Criado</th>
                  <th>Expira</th>
                  <th>Status</th>
                  <th>Downloads</th>
                  <th>Senha</th>
                  <th>Link de Compartilhamento</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody id="shareLinksTable">
                <tr>
                  <td colspan="7" class="text-center">Carregando...</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ADDED: Modal para conversão/compressão de imagens -->
  <div class="modal fade" id="convertImagesModal" tabindex="-1" aria-labelledby="convertImagesModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="convertImagesModalLabel">
            <i class="fa fa-image"></i> Converter e Comprimir Imagens
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="outputFormat" class="form-label">Formato de saída:</label>
              <select class="form-select" id="outputFormat">
                <option value="webp">WebP (Recomendado - Melhor compressão)</option>
                <option value="jpeg">JPEG (Universal)</option>
                <option value="png">PNG (Sem perda de qualidade)</option>
                <option value="avif">AVIF (Compressão avançada)</option>
              </select>
            </div>
            <div class="col-md-6">
              <label for="imageQuality" class="form-label">Qualidade (<span class="text-white"
                  id="qualityValue">85</span>%):</label>
              <input type="range" class="form-range" id="imageQuality" min="10" max="100" value="85">
              <div class="form-text text-white">Menor = arquivo menor, mas pior qualidade</div>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label for="maxWidth" class="form-label">Largura máxima (px):</label>
              <input type="number" class="form-control" id="maxWidth" value="1920" min="100" max="4000">
              <div class="form-text text-white">Redimensionar se maior que este valor</div>
            </div>
            <div class="col-md-6">
              <label for="maxHeight" class="form-label">Altura máxima (px):</label>
              <input type="number" class="form-control" id="maxHeight" value="1080" min="100" max="4000">
              <div class="form-text text-white">Redimensionar se maior que este valor</div>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="preserveOriginal" checked>
                <label class="form-check-label" for="preserveOriginal">
                  Manter arquivos originais
                </label>
                <div class="form-text text-white">Se desmarcado, substitui os originais</div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="addSuffix" checked>
                <label class="form-check-label" for="addSuffix">
                  Adicionar sufixo "_converted"
                </label>
                <div class="form-text text-white">Para diferenciar dos originais</div>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Imagens selecionadas para conversão:</label>
            <div id="selectedImagesList" class="border rounded p-2 text-white" style="max-height: 200px; overflow-y: auto;">
              <small class="text-muted text-white">Carregando...</small>
            </div>
          </div>

          <div id="conversionProgress" style="display:none;" class="mb-3">
            <label class="form-label">Progresso da conversão:</label>
            <div class="progress mb-2">
              <div id="conversionProgressBar" class="progress-bar progress-bar-striped progress-bar-animated"
                role="progressbar" style="width: 0%">0%</div>
            </div>
            <div id="conversionStatus">Preparando conversão...</div>
          </div>

          <div id="conversionResults" style="display:none;" class="mt-3">
            <h6>Resultados da conversão:</h6>
            <div id="resultsContainer"></div>

            <!-- Botão adicional para fechar e atualizar após conversão bem-sucedida -->
            <div id="conversionCompleteActions" style="display:none;" class="mt-3 text-center">
              <button type="button" class="btn btn-success" data-bs-dismiss="modal">
                <i class="fa fa-check-circle"></i> Fechar e Ver Novos Arquivos
              </button>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary" id="startConversionBtn">
            <i class="fa fa-cog"></i> Iniciar Conversão
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ADDED: Modal de Configurações (visível somente via $permissionAdmin) -->
  <div class="modal" id="configModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form id="configForm">
          <div class="modal-header">
            <h5 class="modal-title">Configurações Principais</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="cfg_allow_delete">
              <label class="form-check-label" for="cfg_allow_delete">Permitir exclusão
                (allow_delete)</label>
            </div>
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="cfg_allow_upload">
              <label class="form-check-label" for="cfg_allow_upload">Permitir upload
                (allow_upload)</label>
            </div>
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="cfg_allow_create_folder">
              <label class="form-check-label" for="cfg_allow_create_folder">Permitir criar pasta
                (allow_create_folder)</label>
            </div>
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="cfg_allow_create_file">
              <label class="form-check-label" for="cfg_allow_create_file">Permitir criar arquivo
                (allow_create_file)</label>
            </div>
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="cfg_allow_direct_link">
              <label class="form-check-label" for="cfg_allow_direct_link">Permitir link direto
                (allow_direct_link)</label>
            </div>
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="cfg_allow_show_folders">
              <label class="form-check-label" for="cfg_allow_show_folders">Mostrar subpastas
                (allow_show_folders)</label>
            </div>
            <div class="mb-2">
              <label for="cfg_configTime" class="form-label">Tempo de sessão (minutos) -
                configTime</label>
              <input type="number" id="cfg_configTime" class="form-control" min="1">
            </div>
            <div class="mb-2">
              <label for="cfg_disallowed" class="form-label">Padrões proibidos (vírgula separada)</label>
              <input type="text" id="cfg_disallowed" class="form-control" placeholder="*.php, *.exe">
            </div>
            <div class="mb-2">
              <label for="cfg_hidden" class="form-label">Padrões ocultos (vírgula separada)</label>
              <input type="text" id="cfg_hidden" class="form-control" placeholder="*.php, *.css, .*">
            </div>
            <div class="mb-2">
              <label for="cfg_SENHA" class="form-label">Senha de acesso (SENHA)</label>
              <input type="text" id="cfg_SENHA" class="form-control" placeholder="abc123">
            </div>
            <div id="configSaveStatus" class="text-success" style="display:none;"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="button" id="saveConfigBtn" class="btn btn-primary">Salvar Configurações</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <!-- end config modal -->

  <!-- Modal para Mover/Copiar Arquivos/Pastas -->
  <div class="modal" id="moveCopyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Mover / Copiar Selecionados</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p id="moveCopySummary">Nenhum item selecionado.</p>

          <div class="mb-2">
            <label class="form-label">Ação</label>
            <div class="d-flex gap-3">
              <input type="radio" name="movecopy_action" id="moveAction" value="move" checked>
              <label for="moveAction">Mover</label>
              <input type="radio" name="movecopy_action" id="copyAction" value="copy">
              <label for="copyAction">Copiar</label>
            </div>
          </div>

          <div class="mb-2">
            <label for="moveCopyDest" class="form-label">Destino</label>
            <div class="position-relative">
              <select id="moveCopyDest" class="form-select"></select>
              <div id="loadingSpinner" class="position-absolute top-50 end-0 translate-middle-y me-2"
                style="display: none;">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                  <span class="visually-hidden">Carregando...</span>
                </div>
              </div>
            </div>
            <small class="form-text text-warning">Escolha a pasta de destino. Todas as pastas serão listadas
              automaticamente.</small>
          </div>

          <div class="mb-2">
            <label class="form-label">Arquivos / Pastas selecionados</label>
            <ul id="moveCopyFileList" style="max-height:200px;overflow:auto;padding-left:16px;margin:0;">
            </ul>
          </div>

          <div id="moveCopyResult" style="display:none;" class="mt-2"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" id="confirmMoveCopyBtn" class="btn btn-primary">Executar</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    $(function () {
      // Atualiza também o botão mover/copiar quando a seleção mudar
      function updateBulkButtons() {
        var anyChecked = $('.select-item:checked').length > 0;
        $('#deleteSelectedBtn').prop('disabled', !anyChecked);
        $('#zipSelectedBtn').prop('disabled', !anyChecked);
        $('#moveCopyBtn').prop('disabled', !anyChecked);

        // Para o botão de conversão, verificar se há imagens selecionadas
        var imageExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
        var hasImages = $('.select-item:checked').filter(function () {
          var file = $(this).attr('data-file');
          var ext = file.split('.').pop().toLowerCase();
          return imageExts.includes(ext);
        }).length > 0;
        $('#convertImagesBtn').prop('disabled', !hasImages);
      }

      // hook: sempre que um checkbox mudar, atualiza botões
      $('#table').on('change', '.select-item', function () {
        var allChecked = $('.select-item').length === $('.select-item:checked').length;
        $('#selectAll').prop('checked', allChecked);
        updateBulkButtons();
      });

      // Função para obter todas as pastas recursivamente
      function getAllFolders(path, callback) {
        console.log('getAllFolders chamado para:', path);
        $.get('?do=listfolders&file=' + encodeURIComponent(path), function (data) {
          console.log('Resposta recebida do listfolders:', data);
          if (data && data.success && data.folders) {
            console.log('Encontradas', data.folders.length, 'pastas');
            callback(data.folders);
          } else {
            console.log('Erro ou nenhuma pasta encontrada');
            callback([]);
          }
        }, 'json').fail(function (xhr, status, error) {
          console.log('Erro na requisição listfolders:', { xhr: xhr, status: status, error: error });
          callback([]);
        });
      }

      // Handler para abrir modal mover/copiar
      $('#moveCopyBtn').on('click', function (e) {
        e.preventDefault();
        var filesToOperate = $('.select-item:checked').map(function () { return $(this).attr('data-file'); }).get();
        if (!filesToOperate.length) return alert('Nenhum arquivo/pasta selecionado.');

        // popular lista
        $('#moveCopyFileList').empty();
        filesToOperate.forEach(function (f) { $('#moveCopyFileList').append($('<li/>').text(f)); });
        $('#moveCopySummary').text('Itens selecionados: ' + filesToOperate.length);
        $('#moveCopyResult').hide().empty();

        // Mostrar loading no select
        var $select = $('#moveCopyDest');
        var $spinner = $('#loadingSpinner');
        $select.empty();
        $select.append($('<option value="">Carregando pastas...</option>'));
        $select.prop('disabled', true);
        $spinner.show();

        // Popular select de pastas recursivamente
        getAllFolders('.', function (folders) {
          console.log('Populando select com', folders.length, 'pastas');
          $select.empty();
          $select.prop('disabled', false);
          $spinner.hide();
          $select.append($('<option value=".">📁 Pasta raiz (./)</option>'));

          folders.forEach(function (folder, index) {
            console.log('Adicionando pasta', index + 1, ':', folder.path);
            var displayText = folder.name;
            // Adicionar indentação visual baseada na profundidade
            var depth = (folder.path.match(/\//g) || []).length;
            var indent = '  '.repeat(depth);
            var folderIcon = '📁 ';
            $select.append($('<option/>').val(folder.path).text(indent + folderIcon + folder.path));
          });

          if (folders.length === 0) {
            console.log('Nenhuma pasta encontrada, adicionando opção de aviso');
            $select.append($('<option value="" disabled>Nenhuma pasta encontrada</option>'));
          }

          console.log('Select populado com', $select.find('option').length, 'opções total');
        });

        $('#moveCopyModal').modal('show');
      });

      // Abrir modal de informações da pasta
      $(document).on('click', '.folder-info-btn', function (e) {
        e.preventDefault();
        var folder = $(this).data('folder');
        $('#folderInfoContent').html('<div class="text-center">Carregando...</div>');
        var modal = new bootstrap.Modal(document.getElementById('folderInfoModal'));
        modal.show();
        $.get('?do=folderinfo&folder=' + encodeURIComponent(folder), function (resp) {
          if (resp.success) {
            if (resp.modified) {
              // Ajusta formato para DD/MM/YYYY HH:mm:ss
              var dt = resp.modified;
              // Se vier como string, tenta converter para Date
              var parts = dt.split(' ');
              if (parts.length === 2) {
                var dateParts = parts[0].split('/');
                var timeParts = parts[1].split(':');
                if (dateParts.length === 3 && timeParts.length === 3) {
                  var d = new Date(dateParts[2], dateParts[1] - 1, dateParts[0], timeParts[0], timeParts[1], timeParts[2]);
                  resp.modified = d.toLocaleString('pt-BR', { hour12: false });
                }
              }
            }
            var html = '<ul class="list-group">';
            html += '<li class="list-group-item"><b>Nome:</b> ' + resp.name + '</li>';
            html += '<li class="list-group-item"><b>Tamanho:</b> ' + resp.size + '</li>';
            html += '<li class="list-group-item"><b>Última modificação:</b> ' + resp.modified + '</li>';
            html += '<li class="list-group-item"><b>Nº de arquivos:</b> ' + resp.files + '</li>';
            html += '<li class="list-group-item"><b>Nº de diretórios:</b> ' + resp.dirs + '</li>';
            html += '</ul>';
            $('#folderInfoContent').html(html);
          } else {
            $('#folderInfoContent').html('<div class="text-danger">Erro ao obter informações.</div>');
          }
        }, 'json');
      });

      // ADDED: Handler para conversão de imagens
      $('#convertImagesBtn').on('click', function (e) {
        e.preventDefault();
        var selectedFiles = $('.select-item:checked').map(function () {
          return $(this).attr('data-file');
        }).get();

        // Filtrar apenas imagens
        var imageExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff', 'tif', 'svg', 'avif'];
        var imageFiles = selectedFiles.filter(function (file) {
          var ext = file.split('.').pop().toLowerCase();
          return imageExts.includes(ext);
        });

        if (!imageFiles.length) {
          alert('Nenhuma imagem selecionada.');
          return;
        }

        // Preencher lista de imagens selecionadas
        var filesList = '<strong>Imagens selecionadas (' + imageFiles.length + '):</strong><br>';
        imageFiles.forEach(function (file) {
          var size = getFileSize(file);
          filesList += '<small class="d-block text-info">• ' + file + (size ? ' (' + size + ')' : '') + '</small>';
        });
        $('#selectedImagesList').html(filesList);

        // Mostrar modal
        var modal = new bootstrap.Modal(document.getElementById('convertImagesModal'));
        modal.show();
      });

      // Handler do slider de qualidade
      $('#imageQuality').on('input', function () {
        $('#qualityValue').text($(this).val());
      });

      // Handler do checkbox "preservar original"
      $('#preserveOriginal').on('change', function () {
        var isChecked = $(this).prop('checked');
        if (!isChecked) {
          $('#addSuffix').prop('checked', false).prop('disabled', true);
        } else {
          $('#addSuffix').prop('disabled', false);
        }
      });

      // Handler para iniciar conversão
      $('#startConversionBtn').on('click', function () {
        // Função local para formatar bytes
        function formatBytes(bytes, decimals = 2) {
          if (bytes === 0) return '0 B';
          const k = 1024;
          const dm = decimals < 0 ? 0 : decimals;
          const sizes = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];
          const i = Math.floor(Math.log(bytes) / Math.log(k));
          return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }

        var selectedFiles = $('.select-item:checked').map(function () {
          return $(this).attr('data-file');
        }).get();

        // Filtrar apenas imagens
        var imageExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff', 'tif', 'svg', 'avif'];
        var imageFiles = selectedFiles.filter(function (file) {
          var ext = file.split('.').pop().toLowerCase();
          return imageExts.includes(ext);
        });

        if (!imageFiles.length) {
          alert('Nenhuma imagem selecionada.');
          return;
        }

        // Coletar configurações
        var settings = {
          do: 'convertimages',
          files: imageFiles,
          format: $('#outputFormat').val(),
          quality: $('#imageQuality').val(),
          max_width: $('#maxWidth').val(),
          max_height: $('#maxHeight').val(),
          preserve_original: $('#preserveOriginal').prop('checked') ? '1' : '0',
          add_suffix: $('#addSuffix').prop('checked') ? '1' : '0',
          xsrf: XSRF
        };

        // Mostrar progresso
        $('#conversionProgress').show();
        $('#conversionResults').hide();
        $('#startConversionBtn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Convertendo...');

        // Simular progresso durante a conversão
        var progress = 0;
        var progressInterval = setInterval(function () {
          progress += Math.random() * 15;
          if (progress > 90) progress = 90;
          $('#conversionProgressBar').css('width', progress + '%').text(Math.round(progress) + '%');
        }, 200);

        // Iniciar conversão
        $.post('?', settings, function (response) {
          clearInterval(progressInterval);
          $('#conversionProgressBar').css('width', '100%').text('100%');
          $('#conversionStatus').text('Conversão finalizada!');

          if (response && response.success) {
            var resultsHtml = '<div class="alert alert-success">';
            resultsHtml += '<strong>Conversão concluída!</strong><br>';
            resultsHtml += 'Total de imagens processadas: ' + response.total + '<br>';
            resultsHtml += 'Imagens convertidas com sucesso: ' + response.converted + '<br>';
            resultsHtml += '</div>';

            // Mostrar detalhes de cada arquivo
            if (response.results && response.results.length > 0) {
              resultsHtml += '<div class="mt-3">';
              response.results.forEach(function (result) {
                var status = result.success ? 'success' : 'danger';
                var icon = result.success ? 'check-circle' : 'times-circle';
                resultsHtml += '<div class="alert alert-' + status + ' py-2 mb-2">';
                resultsHtml += '<i class="fa fa-' + icon + '"></i> <strong>' + result.file + '</strong><br>';

                if (result.success) {
                  resultsHtml += '<small>';
                  resultsHtml += 'Arquivo de saída: ' + result.output_file + '<br>';
                  resultsHtml += 'Tamanho original: ' + formatBytes(result.original_size) + '<br>';
                  resultsHtml += 'Novo tamanho: ' + formatBytes(result.new_size) + '<br>';
                  resultsHtml += 'Compressão: ' + result.compression_ratio + '%';
                  resultsHtml += '</small>';
                } else {
                  resultsHtml += '<small class="text-danger">Erro: ' + (result.error || 'Erro desconhecido') + '</small>';
                }
                resultsHtml += '</div>';
              });
              resultsHtml += '</div>';
            }

            $('#resultsContainer').html(resultsHtml);
            $('#conversionResults').show();

            // Mostrar botão especial para fechar e ver novos arquivos se houver conversões bem-sucedidas
            if (response.converted > 0) {
              $('#conversionCompleteActions').show();
            }

          } else {
            var errorMsg = response && response.error ? response.error.msg : 'Erro desconhecido';
            $('#resultsContainer').html('<div class="alert alert-danger">Erro na conversão: ' + errorMsg + '</div>');
            $('#conversionResults').show();
          }
        }, 'json').fail(function (xhr) {
          clearInterval(progressInterval);
          var errorMsg = 'Falha na requisição';
          try {
            var data = JSON.parse(xhr.responseText);
            if (data && data.error && data.error.msg) {
              errorMsg = data.error.msg;
            }
          } catch (e) { }

          $('#resultsContainer').html('<div class="alert alert-danger">Erro: ' + errorMsg + '</div>');
          $('#conversionResults').show();
        }).always(function () {
          $('#startConversionBtn').prop('disabled', false).html('<i class="fa fa-cog"></i> Iniciar Conversão');
        });
      });

      // Função auxiliar para obter tamanho do arquivo
      function getFileSize(filePath) {
        var $row = $('.select-item[data-file="' + filePath + '"]').closest('tr');
        if ($row.length) {
          return $row.find('td').eq(1).text().trim();
        }
        return null;
      }

      // Executar mover/copy
      $('#confirmMoveCopyBtn').on('click', function () {
        var filesToOperate = $('.select-item:checked').map(function () { return $(this).attr('data-file'); }).get();
        if (!filesToOperate.length) return alert('Nenhum arquivo/pasta selecionado.');
        var dest = $('#moveCopyDest').val();
        if (!dest || dest.indexOf('..') !== -1) return alert('Destino inválido.');
        var action = $('input[name=movecopy_action]:checked').val();
        var payload = { do: 'movecopy', files: filesToOperate, dest: dest, action: action, xsrf: (document.cookie.match('(^|; )_sfm_xsrf=([^;]*)') || 0)[2] };
        $('#confirmMoveCopyBtn').prop('disabled', true).text('Executando...');
        $.post('?', payload, function (res) {
          $('#confirmMoveCopyBtn').prop('disabled', false).text('Executar');
          if (res && res.success) {
            var out = [];
            Object.keys(res.results || {}).forEach(function (k) {
              var r = res.results[k];
              out.push('<div>' + k + ': ' + (r.success ? '<span style="color:green">OK</span>' : '<span style="color:red">' + (r.msg || 'Erro') + '</span>') + '</div>');
            });
            $('#moveCopyResult').html(out.join('')).show();
            // Só atualiza a página se pelo menos um item foi bem-sucedido
            var anySuccess = Object.values(res.results || {}).some(function (r) { return r.success; });
            if (anySuccess) {
              setTimeout(function () {
                $('#moveCopyModal').modal('hide');
                $('#selectAll').prop('checked', false);
                $('.select-item').prop('checked', false);
                updateBulkButtons();
                location.reload(); // Atualiza a página após a operação
              }, 1000);
            }
          } else {
            alert('Erro ao executar operação: ' + (res && res.error ? res.error.msg : 'unknown'));
            $('#moveCopyResult').hide();
          }
        }, 'json').fail(function (xhr) {
          $('#confirmMoveCopyBtn').prop('disabled', false).text('Executar');
          var msg = 'Falha na requisição.';
          try { var json = JSON.parse(xhr.responseText); if (json && json.error && json.error.msg) msg = json.error.msg; } catch (e) { }
          alert('Erro: ' + msg);
        });
      });

    });
  </script>
</body>

</html>