<?php

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

$disallowed_patterns = ['*.php'];  // 
$hidden_patterns = ['*.php', '*.css', '*.js', '.*']; // Extensões ocultas no índice do diretório

$SENHA = 'abc123';  // Defina a senha, para acessar o gerenciador de arquivos ... (opcional)

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
    <html>
    <head>
    <title>[Login] Gerenciador de Arquivos</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.1.3/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.1.3/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js"></script>
    <style>
    html,body {
    width: 100vw;
    height: 100vh;
    margin: 0;
    padding: 0;
    background-color: #212529;
    color: #ffffff;
    font-family: Arial, Helvetica, sans-serif;
    display: flex;
    flex-direction: row;
    flex-wrap: nowrap;
    align-content: center;
    justify-content: center;
    align-items: center;
    }
    form {
    height: 50%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    border-radius: 10px;
    padding: 10px;
    text-align: center;
    font-family: monospace;
    }
    p {
    margin: 0;
    }
    input {
    width: 100%;
    height: 40px;
    border-radius: 25px;
    border: 2px solid #979797;
    background: transparent;
    color: #ffffff;
    text-align: center;
    margin: 15px 0 0 0;
    }
    </style>
    </head>
    <body>
    <form action=? method=post
    <p>Digite a senha para acessar o gerenciador de arquivos:</p>
    <input type=password name=p autofocus/>
    </form>
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

if ($_GET['do'] == 'list') {
  if (is_dir($file)) {
    $directory = $file;
    $result = [];
    $files = array_diff(scandir($directory), ['.', '..']);
    foreach ($files as $entry)
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
    usort($result, function ($f1, $f2) {
      $f1_key = ($f1['is_dir'] ?: 2) . $f1['name'];
      $f2_key = ($f2['is_dir'] ?: 2) . $f2['name'];
      return $f1_key > $f2_key;
    });
  } else {
    err(412, "Not a Directory");
  }
  echo json_encode(['success' => true, 'is_writable' => is_writable($file), 'results' => $result]);
  exit;
} elseif ($_POST['do'] == 'delete') {
  if ($allow_delete) {
    rmrf($file);
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
    err(403, "Forbidden folder.");

  $name = isset($_POST['name']) ? trim($_POST['name']) : '';
  if ($name === '')
    err(400, "Missing file name.");

  // proibir barras, backslashes e parent traversal
  if (strpos($name, '/') !== false || strpos($name, '\\') !== false || strpos($name, '..') !== false)
    err(400, "Invalid file name.");

  // checar padrões proibidos
  foreach ($disallowed_patterns as $pattern)
    if (fnmatch($pattern, $name))
      err(403, "Files of this type are not allowed.");

  $tmp_dir = dirname($_SERVER['SCRIPT_FILENAME']);
  if (DIRECTORY_SEPARATOR === '\\')
    $tmp_dir = str_replace('/', DIRECTORY_SEPARATOR, $tmp_dir);

  $abs = get_absolute_path($tmp_dir . '/' . $folder . '/' . $name);
  if ($abs === false)
    err(500, "Failed to construct path.");

  if (substr($abs, 0, strlen($tmp_dir)) !== $tmp_dir)
    err(403, "Forbidden");

  if (file_exists($abs))
    err(409, "File already exists.");

  $parent_dir = dirname($abs);
  if (!is_writable($parent_dir))
    err(403, "Parent directory not writable.");

  $content = isset($_POST['content']) ? $_POST['content'] : '';
  $res = @file_put_contents($abs, $content, LOCK_EX);
  if ($res === false)
    err(500, "Failed to create file.");

  echo json_encode(['success' => true, 'file' => ($folder === '.' ? $name : ($folder . '/' . $name))]);
  exit;
} elseif ($_POST['do'] == 'upload' && $allow_upload) {
  foreach ($disallowed_patterns as $pattern)
    if (fnmatch($pattern, $_FILES['file_data']['name']))
      err(403, "Files of this type are not allowed.");

  $res = move_uploaded_file($_FILES['file_data']['tmp_name'], $file . '/' . $_FILES['file_data']['name']);
  exit;
} elseif ($_GET['do'] == 'download') {
  foreach ($disallowed_patterns as $pattern)
    if (fnmatch($pattern, $file))
      err(403, "Files of this type are not allowed.");

  $filename = basename($file);
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  header('Content-Type: ' . finfo_file($finfo, $file));
  header('Content-Length: ' . filesize($file));
  header(sprintf(
    'Content-Disposition: attachment; filename=%s',
    strpos('MSIE', $_SERVER['HTTP_REFERER']) ? rawurlencode($filename) : "\"$filename\""
  ));
  ob_flush();
  readfile($file);
  exit;
}

// --- ADDED: endpoint para ler arquivo (para edição) ---
elseif ($_GET['do'] == 'getfile') {
  foreach ($disallowed_patterns as $pattern)
    if (fnmatch($pattern, $file))
      err(403, "Files of this type are not allowed.");

  if (!is_file($file) || !is_readable($file))
    err(404, "File Not Found or Unreadable");

  $max_read = 5 * 1024 * 1024; // 5MB limite de leitura via editor
  if (filesize($file) > $max_read)
    err(413, "File too large to edit via web editor.");

  $content = file_get_contents($file);
  header("Content-Type: application/json; charset=utf-8");
  echo json_encode(['success' => true, 'content' => $content, 'path' => $file]);
  exit;
}

// --- ADDED: endpoint para salvar conteúdo editado ---
elseif ($_POST['do'] == 'savefile') {
  foreach ($disallowed_patterns as $pattern)
    if (fnmatch($pattern, $file = $_POST['file']))
      err(403, "Files of this type are not allowed.");

  if (!is_file($file))
    err(404, "File not found.");

  if (!is_writable($file))
    err(403, "File is not writable.");

  $content = isset($_POST['content']) ? $_POST['content'] : '';
  $res = @file_put_contents($file, $content, LOCK_EX);
  if ($res === false)
    err(500, "Failed to write file.");
  echo json_encode(['success' => true]);
  exit;
}
// --- ADDED: endpoint para criar um ZIP com arquivos selecionados (AGORA suporta diretórios recursivamente) ---
elseif ($_POST['do'] == 'zip') {
  if (!isset($_POST['files']) || !is_array($_POST['files']))
    err(400, "No files specified.");

  // Nome do zip opcional
  $zip_name = isset($_POST['name']) ? trim($_POST['name']) : '';
  // pasta atual (hash)
  $folder = isset($_POST['folder']) && $_POST['folder'] !== '' ? $_POST['folder'] : '.';
  if (strpos($folder, '..') !== false)
    err(403, "Forbidden folder.");

  // sanitize requested zip name
  if ($zip_name === '') {
    $zip_name = 'selected_' . time() . '.zip';
  } else {
    // garantir extensão .zip e caracter seguro
    $zip_name = basename(preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $zip_name));
    if (stripos($zip_name, '.zip') === false) $zip_name .= '.zip';
  }

  if (!class_exists('ZipArchive'))
    err(500, "ZipArchive not available on server.");

  // garantir que a pasta destino exista
  $dest_rel = ($folder === '.' || $folder === '') ? $zip_name : ($folder . '/' . $zip_name);
  $dest_abs = $tmp_dir . '/' . $dest_rel;
  $dest_dir = dirname($dest_abs);
  if (!is_dir($dest_dir)) {
    @mkdir($dest_dir, 0777, true);
  }

  $zip = new ZipArchive();
  if ($zip->open($dest_abs, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true)
    err(500, "Failed to create zip archive.");

  $added = 0;
  foreach ($_POST['files'] as $f) {
    // basic checks
    if (!$f) continue;
    if (strpos($f, '..') !== false) continue;
    if (preg_match('@^.+://@', $f)) continue;

    // proibir padrões indesejados (por base filename)
    foreach ($disallowed_patterns as $pattern) {
      if (fnmatch($pattern, basename($f))) {
        // não adiciona arquivos proibidos
        continue 2;
      }
    }

    $abs = get_absolute_path($tmp_dir . '/' . $f);
    if ($abs === false) continue;
    if (substr($abs, 0, strlen($tmp_dir)) !== $tmp_dir) continue;

    // se for diretório, adicionar recursivamente mantendo estrutura (usa basename($f) como raiz no ZIP)
    if (is_dir($abs)) {
      addPathToZip($zip, $abs, basename($f), $disallowed_patterns, $added);
    } elseif (is_file($abs) && is_readable($abs)) {
      // arquivo simples
      $zip->addFile($abs, basename($abs));
      $added++;
    }
  }

  $zip->close();

  if ($added === 0) {
    // remover zip vazio
    @unlink($dest_abs);
    err(400, "No valid files to add to zip.");
  }

  echo json_encode(['success' => true, 'zip' => $dest_rel, 'added' => $added]);
  exit;
} elseif ($_POST['do'] == 'rename') {
  $old = isset($_POST['file']) ? trim($_POST['file']) : '';
  $newname = isset($_POST['newname']) ? trim($_POST['newname']) : '';
  if ($old === '' || $newname === '')
    err(400, "Missing parameters.");

  // proibir caminhos relativos na nova string e barras
  if (strpos($newname, '/') !== false || strpos($newname, '\\') !== false)
    err(400, "Invalid new name.");

  if (strpos($old, '..') !== false || preg_match('@^.+://@', $old))
    err(403, "Forbidden");

  // basic disallowed extensions check for target name
  foreach ($disallowed_patterns as $pattern)
    if (fnmatch($pattern, $newname))
      err(403, "Target name disallowed by pattern.");

  $tmp_dir = dirname($_SERVER['SCRIPT_FILENAME']);
  if (DIRECTORY_SEPARATOR === '\\')
    $tmp_dir = str_replace('/', DIRECTORY_SEPARATOR, $tmp_dir);

  $old_abs = get_absolute_path($tmp_dir . '/' . $old);
  if ($old_abs === false)
    err(404, 'File or Directory Not Found');

  if (substr($old_abs, 0, strlen($tmp_dir)) !== $tmp_dir)
    err(403, "Forbidden");

  // Ensure source exists
  if (!file_exists($old_abs))
    err(404, "Source not found.");

  // Ensure parent dir writable
  $parent_dir = dirname($old_abs);
  if (!is_writable($parent_dir))
    err(403, "Parent directory not writable.");

  // Build new absolute path (keep same parent)
  $new_rel = ($old === '.' ? $newname : (rtrim(dirname($old), '/\\') . '/' . $newname));
  $new_abs = get_absolute_path($tmp_dir . '/' . $new_rel);
  if ($new_abs === false)
    err(500, "Failed to construct new path.");

  if (substr($new_abs, 0, strlen($tmp_dir)) !== $tmp_dir)
    err(403, "Forbidden");

  // Prevent overwrite
  if (file_exists($new_abs))
    err(409, "Target already exists.");

  // perform rename
  if (!@rename($old_abs, $new_abs))
    err(500, "Rename failed.");

  echo json_encode(['success' => true, 'old' => $old, 'new' => $new_rel]);
  exit;
}

// ADDED: função auxiliar recursiva para adicionar diretórios/arquivos ao zip
function addPathToZip($zip, $absPath, $localPath, $disallowed_patterns, &$added) {
  // $absPath = caminho absoluto no servidor
  // $localPath = caminho relativo a usar dentro do zip (string)
  if (!is_readable($absPath)) return;

  if (is_dir($absPath)) {
    // adiciona diretório vazio no zip (se necessário)
    $zip->addEmptyDir($localPath);

    $files = array_diff(scandir($absPath), ['.', '..']);
    foreach ($files as $entry) {
      // filtrar padrões proibidos pelo nome base
      foreach ($disallowed_patterns as $pattern) {
        if (fnmatch($pattern, $entry)) {
          continue 2;
        }
      }

      $childAbs = $absPath . '/' . $entry;
      $childLocal = $localPath === '' ? $entry : ($localPath . '/' . $entry);

      if (is_dir($childAbs)) {
        addPathToZip($zip, $childAbs, $childLocal, $disallowed_patterns, $added);
      } elseif (is_file($childAbs) && is_readable($childAbs)) {
        // adiciona arquivo preservando o caminho relativo dentro do zip
        $zip->addFile($childAbs, $childLocal);
        $added++;
      }
    }
  } elseif (is_file($absPath) && is_readable($absPath)) {
    $zip->addFile($absPath, $localPath);
    $added++;
  }
}

function is_entry_ignored($entry, $allow_show_folders, $hidden_patterns)
{
  if ($entry === basename(__FILE__)) {
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
$MAX_UPLOAD_SIZE = min(asBytes(ini_get('post_max_size')), asBytes(ini_get('upload_max_filesize')));
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
  <script>
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
    $(function () {
      var XSRF = (document.cookie.match('(^|; )_sfm_xsrf=([^;]*)') || 0)[2];
      var MAX_UPLOAD_SIZE = <?php echo $MAX_UPLOAD_SIZE ?>;
      var $tbody = $('#list');
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

      $('#table').on('click', '.delete', function (data) {
        var fileToDelete = $(this).attr('data-file');
        if (confirm('Tem certeza que deseja excluir este arquivo ou pasta?')) {
          $.post("", { 'do': 'delete', file: fileToDelete, xsrf: XSRF }, function (response) {
            list();
          }, 'json');
        }
        return false;
      });

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
            uploadFile(file);
          });
          $(this).removeClass('drag_over');
        });
        $('input[type=file]').change(function (e) {
          e.preventDefault();
          $.each(this.files, function (k, file) {
            uploadFile(file);
          });
        });

        function updateDeleteSelectedBtn() {
          var anyChecked = $('.select-item:checked').length > 0;
          $('#deleteSelectedBtn').prop('disabled', !anyChecked);
          $('#zipSelectedBtn').prop('disabled', !anyChecked);
        }

        // Atualiza o botão ao clicar em qualquer checkbox
        $('#table').on('change', '.select-item', function () {
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

        // ADDED: criar ZIP dos selecionados
        $('#zipSelectedBtn').click(function () {
          var filesToZip = $('.select-item:checked').map(function () {
            return $(this).attr('data-file');
          }).get();
          if (!filesToZip.length) return alert('Nenhum arquivo selecionado.');

          var now = new Date();
          var dia = String(now.getDate()).padStart(2, '0');
          var mes = String(now.getMonth() + 1).padStart(2, '0');
          var ano = now.getFullYear();
          var hora = String(now.getHours()).padStart(2, '0');
          var minuto = String(now.getMinutes()).padStart(2, '0');
          var defaultName = 'Zip_' + dia + '_' + mes + '_' + ano + '_' + hora + '_' + minuto + '.zip';
          var nameInput = prompt('Nome do arquivo zip (sem extensão) \nDeixe em branco para usar padrão:', defaultName.replace('.zip', ''));
          if (nameInput === null) return; // cancel
          var zipName = nameInput.trim() || defaultName.replace('.zip', '');
          if (!zipName.toLowerCase().endsWith('.zip')) zipName = zipName + '.zip';

          var folder = decodeURIComponent(window.location.hash.substr(1));

          $.post('?', { do: 'zip', files: filesToZip, folder: folder, name: zipName, xsrf: XSRF }, function (res) {
            if (res && res.success) {
              alert('ZIP criado: ' + res.zip);
              list();
              // limpa seleção
              $('#selectAll').prop('checked', false);
              $('.select-item').prop('checked', false);
              updateDeleteSelectedBtn();
            } else {
              alert('Erro ao criar ZIP: ' + (res && res.error ? res.error.msg : 'unknown'));
            }
          }, 'json').fail(function () {
            alert('Falha ao criar ZIP.');
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

          var xhr = new XMLHttpRequest();
          xhr.open('POST', '?');
          xhr.onload = function () {
            $row.remove();
            list();
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
          return $row = $('<div class="error" />')
            .append($('<span class="fileuploadname" />').text('Error: ' + (folder ? folder + '/' : '') + file.name))
            .append($('<span/>').html(' file size - <b>' + formatFileSize(file.size) + '</b>'
              + ' excede o tamanho máximo de upload de <b>' + formatFileSize(MAX_UPLOAD_SIZE) + '</b>'));
        }
      <?php endif; ?>
      function list() {
        var hashval = window.location.hash.substr(1);
        $.get('?do=list&file=' + hashval, function (data) {
          $tbody.empty();
          $('#breadcrumb').empty().html(renderBreadcrumbs(hashval));
          if (data.success) {
            $.each(data.results, function (k, v) {
              $tbody.append(renderFileRow(v));
            });
            !data.results.length && $tbody.append('<tr><td class="empty" colspan=5>Esta pasta está vazia</td></tr>')
            data.is_writable ? $('body').removeClass('no_write') : $('body').addClass('no_write');
          } else {
            console.warn(data.error.msg);
          }
          $('#table').retablesort();
        }, 'json');
      }
      function renderFileRow(data) {
        var $checkbox = $('<input type="checkbox" class="select-item me-2">').attr('data-file', data.path);
        var $link = $('<a class="name" />')
          .attr('href', data.is_dir ? '#' + encodeURIComponent(data.path) : './' + data.path)
          .text(data.name);

        // botão de renomear (ícone lápis) ao lado do nome
        var $rename_btn = null;
        // mostrar se o arquivo/pasta puder ser renomeado (permite para itens editáveis ou deletáveis)
        if (data.is_writable || data.is_deleteable) {
          $rename_btn = $('<a href="#" class="rename-btn btn btn-link btn-sm ms-2" title="Renomear"><i class="fa fa-pencil"></i></a>').attr('data-file', data.path);
        }

        var allow_direct_link = <?php echo $allow_direct_link ? 'true' : 'false'; ?>;
        if (!data.is_dir && !allow_direct_link) $link.css('pointer-events', 'none');

        var $dl_link = $('<a class="btn btn-outline-primary btn-md me-2"><i class="fa fa-download"></i></a>').attr('href', '?do=download&file=' + encodeURIComponent(data.path)).addClass('download');
        var $delete_link = $('<a href="#" class="btn btn-outline-danger btn-md"><i class="fa fa-trash"></i></a>').attr('data-file', data.path).addClass('delete');
        // Botão visualizar imagem
        var imageExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
        // ADDED: extensões de mídia
        var audioExts = ['mp3', 'wav', 'ogg', 'm4a'];
        var videoExts = ['mp4', 'webm', 'ogg', 'mkv'];
        // ADDED: extensões PDF e botão visualizar PDF
        var pdfExts = ['pdf'];
        // ADDED: extensões de texto e botão editar
        var textExts = ['txt', 'md', 'csv', 'html', 'htm', 'js', 'css', 'json', 'log', 'ini', 'xml', 'yaml', 'yml'];
        var ext = data.name.split('.').pop().toLowerCase();
        var $view_link = null;
        if (!data.is_dir && imageExts.includes(ext)) {
          $view_link = $('<a href="#" class="btn btn-outline-info btn-md me-2"><i class="fa fa-eye" aria-hidden="true"></i></a>').attr('data-file', data.path).addClass('view-image');
        }

        // ADDED: botão para reproduzir áudio
        var $audio_link = null;
        if (!data.is_dir && audioExts.includes(ext)) {
          $audio_link = $('<a href="#" class="btn btn-outline-info btn-md me-2"><i class="fa fa-headphones" aria-hidden="true"></i></a>').attr('data-file', data.path).addClass('view-audio');
        }
        // ADDED: botão para reproduzir vídeo
        var $video_link = null;
        if (!data.is_dir && videoExts.includes(ext)) {
          $video_link = $('<a href="#" class="btn btn-outline-info btn-md me-2"><i class="fa fa-video-camera" aria-hidden="true"></i></a>').attr('data-file', data.path).addClass('view-video');
        }

        // ADDED: botão para visualizar PDF inline
        var $pdf_link = null;
        if (!data.is_dir && pdfExts.includes(ext)) {
          $pdf_link = $('<a href="#" class="btn btn-outline-info btn-md me-2"><i class="fa fa-file-pdf-o" aria-hidden="true"></i></a>').attr('data-file', data.path).addClass('view-pdf');
        }

        // ADJUSTED: criar botão Editar somente se o arquivo for gravável
        var $edit_link = null;
        if (!data.is_dir && textExts.includes(ext) && data.is_writable) {
          $edit_link = $('<a href="#" class="btn btn-outline-info btn-md me-2"><i class="fa fa-edit"></i></a>').attr('data-file', data.path).addClass('edit-text');
        }

        var perms = [];
        if (data.is_readable) perms.push('Visualizar ');
        if (data.is_writable) perms.push(' Editar ');
        if (data.is_executable) perms.push(' Executar');

        var $html = $('<tr />')
          .addClass(data.is_dir ? 'is_dir' : '')
          .append(
            $('<td class="first d-flex align-items-center" />').append($checkbox).append($link).append($rename_btn ? $rename_btn : '')
          )
          .append($('<td/>').attr('data-sort', data.is_dir ? -1 : data.size)
            .html($('<span class="size" />').text(formatFileSize(data.size))))
          .append($('<td/>').attr('data-sort', data.mtime).text(formatTimestamp(data.mtime)))
          .append($('<td/>').text(perms.join('~')))
          .append($('<td class="text-center" />')
            .append($view_link ? $view_link : '')
            .append($audio_link ? $audio_link : '')
            .append($video_link ? $video_link : '')
            .append($pdf_link ? $pdf_link : '')
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
      function formatFileSize(bytes) {
        var s = ['bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];
        for (var pos = 0; bytes >= 1000; pos++, bytes /= 1024);
        var d = Math.round(bytes * 10);
        return pos ? [parseInt(d / 10), ".", d % 10, " ", s[pos]].join('') : bytes + ' bytes';
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

      // Evento para visualizar imagem
      $('#table').on('click', '.view-image', function (e) {
        e.preventDefault();
        var file = $(this).attr('data-file');
        var src = './' + file;
        $('#imagePreviewModalImg').attr('src', src);
        $('#imagePreviewModal').fadeIn();
      });

      // Fechar modal de preview
      $('#imagePreviewModalClose').on('click', function () {
        $('#imagePreviewModal').fadeOut();
        $('#imagePreviewModalImg').attr('src', '');
      });

      // ADDED / UPDATED: handlers para editar arquivo de texto (abre modal, carrega e salva)
      // usa display:flex para que o alinhamento central funcione corretamente
      $('#table').on('click', '.edit-text', function (e) {
        e.preventDefault();
        var file = $(this).attr('data-file');
        if (!file) return;
        $('#textEditModal').data('file', file);
        $('#textEditStatus').text('');
        $('#textEditArea').val('Carregando...');
        $.get('?do=getfile&file=' + encodeURIComponent(file), function (res) {
          if (res && res.success) {
            $('#textEditArea').val(res.content);
            // Força display:flex antes de mostrar para manter alinhamento central
            $('#textEditModal').css('display', 'flex').hide().fadeIn(150);
            $('#textEditArea').focus();
          } else {
            alert('Erro ao carregar arquivo: ' + (res && res.error ? res.error.msg : 'unknown'));
          }
        }, 'json').fail(function () {
          alert('Falha ao buscar o arquivo.');
        });
      });

      // fechar modal
      $('#textEditClose').off('click').on('click', function () {
        $('#textEditModal').fadeOut(120, function () {
          $(this).css('display', 'none');
        });
        $('#textEditArea').val('');
        $('#textEditStatus').text('');
      });

      // salvar conteúdo
      $('#saveTextBtn').off('click').on('click', function () {
        var file = $('#textEditModal').data('file');
        if (!file) return alert('Arquivo inválido.');
        var content = $('#textEditArea').val();
        $('#saveTextBtn').prop('disabled', true);
        $('#textEditStatus').text('Salvando...');
        $.post('?', { do: 'savefile', file: file, content: content, xsrf: XSRF }, function (res) {
          $('#saveTextBtn').prop('disabled', false);
          if (res && res.success) {
            $('#textEditStatus').text('Salvo com sucesso.');
            list(); // atualiza listagem (mtime/size)
            setTimeout(function () { $('#textEditModal').fadeOut(150, function () { $(this).css('display', 'none'); }); $('#textEditStatus').text(''); }, 700);
          } else {
            $('#textEditStatus').text('Erro ao salvar.');
            alert('Erro ao salvar arquivo: ' + (res && res.error ? res.error.msg : 'unknown'));
          }
        }, 'json').fail(function () {
          $('#saveTextBtn').prop('disabled', false);
          $('#textEditStatus').text('Erro na requisição.');
          alert('Falha na requisição de salvamento.');
        });
      });

      // ADDED: handler para abrir modal PDF
      $('#table').on('click', '.view-pdf', function (e) {
        e.preventDefault();
        var file = $(this).attr('data-file');
        if (!file) return;
        var src = './' + file;
        // força display:flex para manter centralizado
        $('#pdfViewModal').css('display', 'flex').hide().fadeIn(150);
        $('#pdfViewModalIframe').attr('src', src);
      });

      // fechar modal PDF
      $('#pdfViewModalClose').off('click').on('click', function () {
        $('#pdfViewModal').fadeOut(120, function () { $(this).css('display', 'none'); });
        $('#pdfViewModalIframe').attr('src', '');
      });

      // ADDED: handlers para áudio e vídeo em modal
      $('#table').on('click', '.view-audio', function (e) {
        e.preventDefault();
        var file = $(this).attr('data-file');
        if (!file) return;
        var src = './' + file;
        // configura e mostra modal de mídia
        $('#mediaModal').css('display', 'flex').hide().fadeIn(150);
        $('#mediaTitle').text(file);
        $('#mediaPlayerVideo').hide().attr('src', '');
        $('#mediaPlayerAudio').attr('src', src).show()[0].play();
      });

      $('#table').on('click', '.view-video', function (e) {
        e.preventDefault();
        var file = $(this).attr('data-file');
        if (!file) return;
        var src = './' + file;
        $('#mediaModal').css('display', 'flex').hide().fadeIn(150);
        $('#mediaTitle').text(file);
        $('#mediaPlayerAudio').hide().attr('src', '');
        $('#mediaPlayerVideo').attr('src', src).show()[0].play();
      });

      // fechar modal de mídia: pausa e limpa src
      $('#mediaModalClose').off('click').on('click', function () {
        var v = $('#mediaPlayerVideo')[0];
        var a = $('#mediaPlayerAudio')[0];
        try { if (v && !v.paused) v.pause(); } catch (e) { }
        try { if (a && !a.paused) a.pause(); } catch (e) { }
        $('#mediaPlayerVideo').attr('src', '').hide();
        $('#mediaPlayerAudio').attr('src', '').hide();
        $('#mediaModal').fadeOut(120, function () { $(this).css('display', 'none'); });
        $('#mediaTitle').text('');
      });

      // ----------------------------
      // JavaScript: adicionar ícone lápis e handlers de renomear
      // procure no arquivo JS embutido a função renderFileRow e substitua/edite a parte correspondente.
      /* dentro da função renderFileRow(data) - substitua/adicione conforme abaixo (mostramos apenas o trecho alterado) */
      function renderFileRow(data) {
        var $checkbox = $('<input type="checkbox" class="select-item me-2">').attr('data-file', data.path);
        var $link = $('<a class="name" />')
          .attr('href', data.is_dir ? '#' + encodeURIComponent(data.path) : './' + data.path)
          .text(data.name);

        // botão de renomear (ícone lápis) ao lado do nome
        var $rename_btn = null;
        // mostrar se o arquivo/pasta puder ser renomeado (permite para itens editáveis ou deletáveis)
        if (data.is_writable || data.is_deleteable) {
          $rename_btn = $('<a href="#" class="rename-btn btn btn-link btn-sm ms-2" title="Renomear"><i class="fa fa-pencil"></i></a>').attr('data-file', data.path);
        }

        var allow_direct_link = <?php echo $allow_direct_link ? 'true' : 'false'; ?>;
        if (!data.is_dir && !allow_direct_link) $link.css('pointer-events', 'none');

        var $dl_link = $('<a class="btn btn-outline-primary btn-md me-2"><i class="fa fa-download"></i></a>').attr('href', '?do=download&file=' + encodeURIComponent(data.path)).addClass('download');
        var $delete_link = $('<a href="#" class="btn btn-outline-danger btn-md"><i class="fa fa-trash"></i></a>').attr('data-file', data.path).addClass('delete');
        // Botão visualizar imagem
        var imageExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
        // ADDED: extensões de mídia
        var audioExts = ['mp3', 'wav', 'ogg', 'm4a'];
        var videoExts = ['mp4', 'webm', 'ogg', 'mkv'];
        // ADDED: extensões PDF e botão visualizar PDF
        var pdfExts = ['pdf'];
        // ADDED: extensões de texto e botão editar
        var textExts = ['txt', 'md', 'csv', 'html', 'htm', 'js', 'css', 'json', 'log', 'ini', 'xml', 'yaml', 'yml'];
        var ext = data.name.split('.').pop().toLowerCase();
        var $view_link = null;
        if (!data.is_dir && imageExts.includes(ext)) {
          $view_link = $('<a href="#" class="btn btn-outline-info btn-md me-2"><i class="fa fa-eye" aria-hidden="true"></i></a>').attr('data-file', data.path).addClass('view-image');
        }

        // ADDED: botão para reproduzir áudio
        var $audio_link = null;
        if (!data.is_dir && audioExts.includes(ext)) {
          $audio_link = $('<a href="#" class="btn btn-outline-info btn-md me-2"><i class="fa fa-headphones" aria-hidden="true"></i></a>').attr('data-file', data.path).addClass('view-audio');
        }
        // ADDED: botão para reproduzir vídeo
        var $video_link = null;
        if (!data.is_dir && videoExts.includes(ext)) {
          $video_link = $('<a href="#" class="btn btn-outline-info btn-md me-2"><i class="fa fa-video-camera" aria-hidden="true"></i></a>').attr('data-file', data.path).addClass('view-video');
        }

        // ADDED: botão para visualizar PDF inline
        var $pdf_link = null;
        if (!data.is_dir && pdfExts.includes(ext)) {
          $pdf_link = $('<a href="#" class="btn btn-outline-info btn-md me-2"><i class="fa fa-file-pdf-o" aria-hidden="true"></i></a>').attr('data-file', data.path).addClass('view-pdf');
        }

        // ADJUSTED: criar botão Editar somente se o arquivo for gravável
        var $edit_link = null;
        if (!data.is_dir && textExts.includes(ext) && data.is_writable) {
          $edit_link = $('<a href="#" class="btn btn-outline-info btn-md me-2"><i class="fa fa-edit"></i></a>').attr('data-file', data.path).addClass('edit-text');
        }

        var perms = [];
        if (data.is_readable) perms.push('Visualizar ');
        if (data.is_writable) perms.push(' Editar ');
        if (data.is_executable) perms.push(' Executar');

        var $html = $('<tr />')
          .addClass(data.is_dir ? 'is_dir' : '')
          .append(
            $('<td class="first d-flex align-items-center" />').append($checkbox).append($link).append($rename_btn ? $rename_btn : '')
          )
          .append($('<td/>').attr('data-sort', data.is_dir ? -1 : data.size)
            .html($('<span class="size" />').text(formatFileSize(data.size))))
          .append($('<td/>').attr('data-sort', data.mtime).text(formatTimestamp(data.mtime)))
          .append($('<td/>').text(perms.join('~')))
          .append($('<td class="text-center" />')
            .append($view_link ? $view_link : '')
            .append($audio_link ? $audio_link : '')
            .append($video_link ? $video_link : '')
            .append($pdf_link ? $pdf_link : '')
            .append($edit_link ? $edit_link : '')
            .append(data.is_dir ? '' : $dl_link)
            .append(data.is_deleteable ? $delete_link : '')
          );

        return $html;
      }

      // ADDED: código para criar novo arquivo via modal
      $('#createFileForm').on('submit', function (e) {
        e.preventDefault();
        var folder = decodeURIComponent(window.location.hash.substr(1)) || '.';
        var name = $('#newfilename').val().trim();
        var content = $('#newfilecontent').val();
        if (!name) { alert('Nome inválido.'); return; }
        // client-side sanity
        if (name.indexOf('/') !== -1 || name.indexOf('\\') !== -1 || name.indexOf('..') !== -1) { alert('Nome inválido.'); return; }

        var $btn = $(this).find('button[type=submit]');
        $btn.prop('disabled', true);
        $('#createFileError').hide().text('');

        $.post('?', { do: 'createfile', file: folder, name: name, content: content, xsrf: XSRF }, function (res) {
          $btn.prop('disabled', false);
          if (res && res.success) {
            // fechar modal (Bootstrap)
            var modalEl = document.getElementById('createFileModal');
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            // limpar campos
            $('#newfilename').val(''); $('#newfilecontent').val('');
            list(); // atualiza listagem
          } else {
            $('#createFileError').show().text((res && res.error) ? res.error.msg : 'Erro ao criar arquivo.');
            alert('Erro ao criar arquivo: ' + ((res && res.error) ? res.error.msg : 'unknown'));
          }
        }, 'json').fail(function () {
          $btn.prop('disabled', false);
          $('#createFileError').show().text('Falha na requisição.');
          alert('Falha ao criar arquivo.');
        });
      });

      // limpar modal ao fechar
      var createFileModalEl = document.getElementById('createFileModal');
      if (createFileModalEl) {
        createFileModalEl.addEventListener('hidden.bs.modal', function () {
          $('#newfilename').val(''); $('#newfilecontent').val(''); $('#createFileError').hide().text('');
        });
      }
    })

  </script>

</head>

<body>
  <div id="session-timer" class="d-flex justify-content-center text-white ml-2"
    style="border-bottom: 1px solid #ffffff;background: #1f8657;padding: 8px 0 8px 0;"></div>
  <div id="breadcrumb"></div>
  <nav class="navbar navbar-expand-sm navbar-dark bg-dark">
    <div class="container-fluid">
      <div class=" w-100" id="mynavbar">
        <ul class="navbar-nav d-flex flex-end">
          <li class="nav-item mr-15">
            <button class="btn btn-success btn-width-custom" type="button" data-bs-toggle="modal"
              data-bs-target="#createFolder" title="Criar Pasta">
              <i class="fa fa-folder-o" aria-hidden="true"></i>
            </button>
          </li>

          <!-- ADDED: botão Criar Arquivo -->
          <li class="nav-item mr-15">
            <button class="btn btn-secondary btn-width-custom" type="button" data-bs-toggle="modal"
              data-bs-target="#createFileModal" title="Criar Arquivo">
              <i class="fa fa-file-o" aria-hidden="true"></i>
            </button>
          </li>

          <li class="nav-item mr-15">
            <button class="btn btn-primary btn-width-custom" type="button" data-bs-toggle="modal"
              data-bs-target="#makeUpload" title="Carregar arquivos">
              <i class="fa fa-upload" aria-hidden="true"></i>
            </button>
          </li>
          <li class="nav-item">
            <button class="btn btn-warning btn-width-custom text-white" type="button" onClick="window.location.reload()"
              data-bs-target="#makeUpload" title="Atualizar lista">
              <i class="fa fa-refresh" aria-hidden="true"></i>
            </button>
          </li>
          <li class="nav-item mr-15">
            <button id="deleteSelectedBtn" class="btn btn-danger btn-width-custom" type="button"
              title="Excluir Selecionados" disabled>
              <i class="fa fa-trash"></i> (Selecionados)
            </button>
          </li>
          <!-- ADDED: botão para criar ZIP dos selecionados -->
          <li class="nav-item mr-15">
            <button id="zipSelectedBtn" class="btn btn-info btn-width-custom" type="button" title="Zip Selecionados"
              disabled>
              <i class="fa fa-file-archive-o"></i> (Zip)
            </button>
          </li>
        </ul>
      </div>
    </div>
  </nav>
  <div class="d-flex align-items-center p-2 text-white bg-secondary">
    <input type="checkbox" id="selectAll" class="me-2"> Selecionar Todos
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

  <!-- Modal simples para preview de imagem -->
  <div id="imagePreviewModal"
    style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.8);z-index:9999;align-items:center;justify-content:center;">
    <div
      style="position:relative;max-width:90vw;max-height:90vh;margin:auto;display:flex;flex-direction:column;align-items:center;justify-content:center;">
      <button id="imagePreviewModalClose" style="position:absolute;bottom: -50px;z-index:10000;"
        class="btn btn-light">Fechar</button>
      <img id="imagePreviewModalImg" src="" alt="Preview"
        style="max-width:80vw;max-height:80vh;border-radius:8px;box-shadow:0 0 20px #000;" />
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
        <div id="mediaTitle" style="font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></div>
        <button id="mediaModalClose" class="btn btn-light">Fechar</button>
      </div>
      <div style="flex:1;display:flex;align-items:center;justify-content:center;padding:12px;background:#000;">
        <video id="mediaPlayerVideo" style="width:100dvh;display:none;background:#000;" controls></video>
        <audio id="mediaPlayerAudio" style="width:100dvh;display:none;" controls></audio>
      </div>
    </div>
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
    <div class="modal-dialog">
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

  <!-- ADDED: Modal simples para editar arquivos de texto -->
  <div id="textEditModal"
    style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.6);z-index:10000;align-items:center;justify-content:center;">
    <div
      style="position:relative;max-width:90vw;max-height:90vh;margin:auto;background:#fff;padding:16px;border-radius:8px;">
      <h5>Editor de Texto</h5>
      <textarea id="textEditArea"
        style="width:80vw;height:60vh;display:block;margin-bottom:8px;font-family:monospace;"></textarea>
      <div class="d-flex justify-content-between">
        <div id="textEditStatus" style="align-self:center;color:#333;"></div>
        <div>
          <button id="textEditClose" class="btn btn-secondary btn-sm">Fechar</button>
          <button id="saveTextBtn" class="btn btn-primary btn-sm">Salvar</button>
        </div>
      </div>
    </div>
  </div>

</body>

</html>