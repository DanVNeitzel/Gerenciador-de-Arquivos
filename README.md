# Gerenciador de Arquivos PHP

Pequeno gerenciador de arquivos baseado em PHP para listar, enviar, baixar, excluir, criar pastas, editar arquivos de texto e criar ZIPs de arquivos selecionados.

## Principais arquivos
- Interface principal e API: [files/index.php](files/index.php)

## Recursos
- Listagem de arquivos e pastas
- Upload de arquivos (via arrastar & soltar ou input)
- Download seguro com verificação de tipos proibidos
- Deleção recursiva de pastas/arquivos
- Criação de pastas
- Editor de texto inline para arquivos de texto
- Preview de imagens, visualizador de PDF, reprodutor de áudio/vídeo
- Criação de ZIP com arquivos selecionados

## Requisitos
- PHP >= 7.x com extensões:
  - Zip (ZipArchive)
  - Fileinfo (finfo)
  - OpenSSL (para geração de tokens/XSRF)

## Instalação / Uso
1. Coloque o projeto em seu servidor web (por exemplo, `C:\xampp\htdocs\...` ou `/var/www/...`).
2. Acesse via navegador o diretório onde está o arquivo [files/index.php](files/index.php).
3. Ajuste permissões de escrita/leitura nas pastas conforme necessário.

## Configuração
As configurações principais estão no topo de [files/index.php](files/index.php):

- Senha de acesso (opcional): [`$SENHA`](files/index.php)  
- Permissões e comportamentos:
  - [`$allow_upload`](files/index.php)
  - [`$allow_create_folder`](files/index.php)
  - [`$allow_delete`](files/index.php)
  - [`$allow_direct_link`](files/index.php)
  - [`$allow_show_folders`](files/index.php)
- Tempo de expiração da sessão: [`$configTime`](files/index.php) (minutos)
- Padrões proibidos/ocultos: [`$disallowed_patterns`](files/index.php), [`$hidden_patterns`](files/index.php)

Edite esses valores diretamente em [files/index.php](files/index.php) conforme sua necessidade.

## Endpoints/API (via [files/index.php](files/index.php))
- Listar: `?do=list&file=...` — retorna JSON com arquivos/pastas
- Download: `?do=download&file=...`
- Upload: POST com `do=upload`, `file_data` (arquivo), `file` (pasta)
- Criar pasta: POST com `do=mkdir`, `name`
- Deletar: POST com `do=delete`, `file`
- Ler arquivo para edição: `?do=getfile&file=...`
- Salvar arquivo editado: POST com `do=savefile`, `file`, `content`
- Criar ZIP: POST com `do=zip`, `files[]`, `name`, `folder`

## Segurança (importante)
- O projeto contém uma proteção simples por senha via [`$SENHA`](files/index.php). Isso NÃO substitui práticas de segurança apropriadas.
- Verifique e modifique [`$disallowed_patterns`](files/index.php) para bloquear tipos sensíveis (ex.: `*.php`).
- Recomenda-se usar HTTPS e um mecanismo de autenticação robusto (ex.: OAuth, autenticação baseada em sessão com usuário/senha real).
- Faça auditoria de permissões das pastas no servidor para evitar exposições.

## Principais funções internas
- Resolução de caminhos seguros: [`get_absolute_path`](files/index.php)
- Remoção recursiva: [`rmrf`](files/index.php)
- Verificação de entrada ignorada: [`is_entry_ignored`](files/index.php)
- Verificação se uma pasta pode ser removida recursivamente: [`is_recursively_deleteable`](files/index.php)

## Personalizações sugeridas
- Integrar autenticação real (usuários/senhas em DB).
- Limitar tipos de upload por MIME e extensão.
- Registrar logs de ações (upload, delete, download).
- Usar CSRF tokens persistentes e validação do lado servidor.

## Licença
Use como desejar. Recomenda-se não expor publicamente sem revisão de segurança.

## Contato
Abra a pasta do projeto e edite [files/index.php](files/index.php) para ajustar configurações.
