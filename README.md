# Gerenciador de Arquivos PHP

Pequeno gerenciador de arquivos em PHP com interface web e API para listar, enviar, baixar, excluir, renomear, criar pastas, criar arquivos de texto, editar arquivos, pré-visualizar mídias e gerar ZIPs (recursivo).

Arquivo principal: [files/index.php](index.php)

## Novas funcionalidades adicionadas
- Criar novo arquivo de texto via UI (modal) e endpoint: POST `do=createfile`.  
  - Endpoint: `do=createfile` — aceita `file` (pasta), `name`, `content`.  
  - Controle por configuração: [`$allow_create_file`](index.php).
- Editor de texto inline (modal) com leitura/salvamento via API:
  - Ler arquivo: GET `?do=getfile&file=...` (limite de leitura: 5MB).
  - Salvar arquivo: POST `do=savefile` com `file` e `content`.
  - Uso condicionado a permissões de escrita.
- Criação de ZIPs aprimorada:
  - Endpoint POST `do=zip` aceita `files[]`, `folder` e `name`.
  - Suporta adicionar diretórios recursivamente (preserva estrutura dentro do ZIP).
  - Função auxiliar: [`addPathToZip`](index.php).
- Renomear arquivo/pasta via endpoint POST `do=rename` (verifica permissões e previne sobrescrita).
- Seleção múltipla na interface:
  - Checkboxes por item, `Select All`, botões para "Excluir Selecionados" e "Zip Selecionados".
  - Exclusão em lote via múltiplos POST `do=delete`.
- Uploads com arrastar & soltar, input file e barra de progresso; validação de tamanho baseada em [`asBytes`](index.php) / `$MAX_UPLOAD_SIZE`.
- Pré-visualizações/players:
  - Imagem: modal de preview.
  - PDF: visualizador em iframe.
  - Áudio/Vídeo: player modal para formatos comuns.
- Timer de sessão visível no topo (contador regressivo) e expiração por inatividade usando a configuração [`$configTime`](index.php).
- Proteção XSRF básica via cookie `_sfm_xsrf` e validação em POSTs.
- Bloqueio de tipos proibidos por padrão (ex.: [`$disallowed_patterns`](index.php) — `*.php`) e ocultação via [`$hidden_patterns`](index.php).

## Recursos (resumido)
- Listagem de arquivos e pastas com permissões e metadados.
- Upload com suporte a múltiplos arquivos e progresso.
- Download seguro (validações de padrão).
- Deleção recursiva (`rmrf`) e verificação de removibilidade recursiva (`is_recursively_deleteable`).
- Criação de pastas.
- Criação/edição de arquivos de texto.
- Pré-visualização de imagens, PDFs e reprodução de áudio/vídeo.
- Criação de ZIPs que suporta diretórios recursivos (`addPathToZip`).
- Renomear itens.
- Seleção múltipla, exclusão e compactação dos selecionados.

## Endpoints / API (via [index.php](index.php))
- Listar: `?do=list&file=...` — retorna JSON com arquivos/pastas.
- Download: `?do=download&file=...`
- Upload: POST com `do=upload`, `file_data` (arquivo), `file` (pasta).
- Criar pasta: POST com `do=mkdir`, `name`.
- Criar arquivo: POST com `do=createfile`, `file`, `name`, `content`.
- Ler arquivo (editor): `?do=getfile&file=...`
- Salvar arquivo (editor): POST com `do=savefile`, `file`, `content`.
- Criar ZIP: POST com `do=zip`, `files[]`, `name`, `folder`.
- Renomear: POST com `do=rename`, `file`, `newname`.
- Deletar: POST com `do=delete`, `file`.

## Variáveis de configuração principais (no topo de [index.php](index.php))
- [`$SENHA`](index.php) — senha de acesso opcional.
- Permissões/flags:
  - [`$allow_upload`](index.php)
  - [`$allow_create_folder`](index.php)
  - [`$allow_create_file`](index.php)
  - [`$allow_delete`](index.php)
  - [`$allow_direct_link`](index.php)
  - [`$allow_show_folders`](index.php)
- Sessão / timeout:
  - [`$configTime`](index.php) — minutos de inatividade até expirar sessão.
- Padrões proibidos/ocultos:
  - [`$disallowed_patterns`](index.php)
  - [`$hidden_patterns`](index.php)

Edite essas constantes diretamente em [index.php](index.php) conforme necessário.

## Funções internas importantes
- Resolução de caminhos seguros: [`get_absolute_path`](index.php)
- Remoção recursiva: [`rmrf`](index.php)
- Verificação de entrada ignorada: [`is_entry_ignored`](index.php)
- Verificação se pode deletar recursivamente: [`is_recursively_deleteable`](index.php)
- Adição recursiva ao ZIP: [`addPathToZip`](index.php)
- Tratamento de erros HTTP/JSON: [`err`](index.php)
- Conversão de valores php.ini para bytes: [`asBytes`](index.php)

## Requisitos
- PHP 7.x ou superior com:
  - Zip (ZipArchive)
  - Fileinfo (finfo)
  - OpenSSL (usado para geração de tokens/XSRF)
- Permissões de arquivo/sistema adequadas para leitura/escrita nas pastas onde o script opera.

## Instalação / Uso
1. Coloque o projeto no servidor web (por exemplo, `C:\xampp\htdocs\...` ou `/var/www/...`).
2. Abra no navegador o local onde está [index.php](index.php).
3. Ajuste as configurações no topo de [index.php](index.php) e permissões das pastas.

## Segurança (recomendações)
- A proteção por [`$SENHA`](index.php) é simples; não é substituto para autenticação robusta.
- Mantenha e expanda [`$disallowed_patterns`](index.php) (por exemplo: bloquear `*.php`, `*.env`, etc.).
- Use HTTPS e autenticação adequada (usuários/senhas, OAuth, etc.).
- Revise permissões do sistema de arquivos para evitar exposições.
- Valide também tipos MIME/headers no servidor para uploads, não apenas extensões.
- Considere registrar (log) ações sensíveis: uploads, downloads, deleções, renomeações, criação de ZIP.

## Sugestões de melhoria
- Autenticação com usuários reais e sessões gerenciadas.
- Logs de auditoria e histórico de alterações.
- Limitar uploads por MIME/assinatura.
- Adicionar paginação/virtualização para pastas com muitos arquivos.
- Verificação/limite de uso do disco por usuário.

## Licença
Use como desejar. Não exponha publicamente sem revisão de segurança.

---

Referências rápidas no código:
- [index.php](index.php) — arquivo principal e todas as rotas/endpoints.
- Funções: [`get_absolute_path`](index.php), [`rmrf`](index.php), [`is_entry_ignored`](index.php), [`is_recursively_deleteable`](index.php), [`addPathToZip`](index.php), [`asBytes`](index.php), [`err`](index.php).
