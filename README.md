# 📁 Gerenciador de Arquivos Avançado

Um sistema completo de gerenciamento de arquivos baseado em PHP com interface moderna e diversas funcionalidades avançadas.

## 🚀 Características Principais

### 🔐 Sistema de Autenticação
- **Login protegido por senha** com expiração automática de sessão
- **Controle de tempo de sessão** configurável (padrão: 5 minutos)
- **Interface de login moderna** com slideshow de imagens de fundo
- **Logout seguro** com limpeza completa da sessão

### 📂 Gerenciamento de Arquivos e Pastas

#### 🗂️ Navegação e Visualização
- **Listagem de arquivos e pastas** com informações detalhadas
- **Visualização em grid e lista** com cards modernos
- **Breadcrumb de navegação** para facilitar o acesso a diretórios
- **Informações completas**: tamanho, data de modificação, permissões
- **Ícones diferenciados** para tipos de arquivo e pastas

#### ➕ Criação e Upload
- **Criação de pastas** com validação de nomes
- **Criação de arquivos de texto** diretamente na interface
- **Upload de múltiplos arquivos** com drag & drop
- **Limite de tamanho configurável** (padrão: 200MB por arquivo)
- **Barra de progresso** para uploads grandes
- **Prevenção de sobrescrita** com confirmação

#### ✏️ Edição e Manipulação
- **Editor de código avançado** com syntax highlighting
- **Suporte a múltiplas linguagens**: HTML, CSS, JavaScript, PHP, Python, SQL, Markdown, YAML, etc.
- **Temas do editor**: Monokai, Dracula, Material, Solarized
- **Recursos avançados**:
  - Numeração de linhas
  - Busca e substituição (Ctrl+F, Ctrl+H)
  - Auto-fechamento de brackets
  - Realce de código ativo
  - Dobramento de código (code folding)
  - Ir para linha (Ctrl+G)
  - Preview em tempo real para Markdown

#### 🗑️ Sistema de Lixeira
- **Lixeira virtual** para recuperação de arquivos
- **Movimentação segura** para lixeira em vez de exclusão permanente
- **Restauração individual** ou em lote
- **Visualização de arquivos na lixeira** com data de exclusão
- **Esvaziamento completo** da lixeira
- **Exclusão permanente** de itens específicos

### 📦 Compressão e Extração

#### 🗜️ Criação de ZIP
- **Seleção múltipla** de arquivos e pastas para compressão
- **Preservação da estrutura de diretórios** recursivamente
- **Níveis de compressão configuráveis** (0-9)
- **Barra de progresso** em tempo real
- **Nomenclatura personalizada** do arquivo ZIP
- **Filtragem automática** de arquivos não permitidos

#### 📂 Extração de ZIP
- **Visualização do conteúdo** antes da extração
- **Extração para pasta personalizada**
- **Informações detalhadas** dos arquivos comprimidos
- **Proteção contra path traversal**
- **Feedback de progresso** durante extração

### 🔗 Sistema de Compartilhamento

#### 📤 Geração de Links
- **Links únicos e seguros** com hash de validação
- **Proteção por senha** opcional
- **Tempo de expiração configurável**
- **Limite de downloads** por link
- **Contador de acessos** e estatísticas

#### 🌐 Página de Compartilhamento
- **Interface amigável** para download público
- **Validação de segurança** automática
- **Proteção contra abuso** com limites
- **Remoção automática** de links expirados
- **Design responsivo** para mobile

### 🖼️ Visualização e Mídia

#### 🎵 Player de Áudio
- **Player flutuante** com controles completos
- **Playlist automática** para múltiplos arquivos
- **Controle de volume** e progresso
- **Suporte a formatos**: MP3, WAV, OGG, AAC
- **Interface minimizável** e responsiva

#### 📸 Galeria de Imagens
- **Visualização em tela cheia** com zoom
- **Navegação com teclado** (← → ↑ ↓)
- **Miniaturas de navegação** na parte inferior
- **Informações da imagem**: dimensões, tamanho, formato
- **Suporte a formatos**: JPG, PNG, GIF, WebP, SVG

#### 🎞️ Reprodutor de Vídeo
- **Player integrado** com controles nativos
- **Suporte a formatos**: MP4, WebM, AVI, MOV
- **Reprodução responsiva** para diferentes telas

### 🛠️ Ferramentas Avançadas

#### 🖱️ Operações em Lote
- **Seleção múltipla** com checkboxes
- **Mover/Copiar** múltiplos arquivos
- **Compressão** de seleções
- **Exclusão** em massa para lixeira

#### ✂️ Renomeação e Movimentação
- **Renomeação inline** com validação
- **Movimentação entre pastas** via drag & drop
- **Cópia recursiva** de diretórios
- **Preservação de permissões**

#### 🎨 Conversão de Imagens
- **Conversão entre formatos**: JPEG, PNG, WebP, AVIF
- **Redimensionamento inteligente** mantendo proporção
- **Compressão com qualidade configurável**
- **Processamento em lote** de múltiplas imagens
- **Preservação de originais** opcional

### ⚙️ Configurações Avançadas

#### 🔧 Painel de Configuração
- **Interface administrativa** para configurações
- **Controle de permissões** por funcionalidade
- **Configuração de limites** de upload e tempo
- **Gerenciamento de extensões** permitidas/bloqueadas
- **Alteração de senha** do sistema

#### 🛡️ Segurança
- **Proteção XSRF** com tokens únicos
- **Validação de paths** contra directory traversal
- **Filtragem de uploads** maliciosos
- **Controle de acesso** baseado em sessão
- **Logs de atividade** para auditoria

### 📱 Interface Responsiva

#### 💻 Design Moderno
- **Interface dark** com tema profissional
- **Componentes Bootstrap 5** para consistência
- **Ícones Font Awesome** para clareza visual
- **Animações suaves** para melhor UX

#### 📲 Compatibilidade Mobile
- **Design responsivo** para todos os tamanhos de tela
- **Touch-friendly** com gestos intuitivos
- **Menu lateral** colapsível em mobile
- **Otimizações** específicas para tablets e smartphones

## 🔧 Instalação e Configuração

### Requisitos do Sistema
```
- PHP 7.4+ com extensões:
  - GD (para manipulação de imagens)
  - ZipArchive (para compressão)
  - JSON (para configurações)
  - Session (para autenticação)
- Servidor web (Apache/Nginx)
- Permissões de escrita no diretório
```

### Configuração Inicial
1. **Upload dos arquivos** para o servidor web
2. **Configurar permissões** de escrita (chmod 755 ou 777)
3. **Acessar** `index.php` no navegador
4. **Login inicial** com senha padrão: `abc123`
5. **Alterar configurações** através do painel admin

### Arquivos Principais
```
├── index.php              # Arquivo principal do sistema
├── share.php              # Sistema de compartilhamento público
├── files_config.json      # Configurações do sistema
├── .trash/                # Diretório da lixeira
├── .shares/               # Links de compartilhamento
└── zip_progress.json      # Status de operações ZIP
```

## 📚 Guia de Uso

### 🔐 Primeiro Acesso
1. Acesse o sistema através do navegador
2. Digite a senha padrão: `abc123`
3. Você será redirecionado para a interface principal

### 📁 Navegação Básica
- **Clique em pastas** para navegar
- Use o **breadcrumb** no topo para voltar
- **Clique direito** para menu contextual
- Use a **barra de busca** para encontrar arquivos

### 📤 Upload de Arquivos
1. Clique no botão **"Upload"**
2. **Selecione arquivos** ou arraste para a área
3. Configure opções de **sobrescrita** se necessário
4. Aguarde o **upload completar**

### ✏️ Edição de Arquivos
1. **Clique no arquivo** de texto para abrir
2. Escolha o **tema do editor** preferido
3. **Edite o conteúdo** com syntax highlighting
4. Use **Ctrl+S** para salvar ou clique em "Salvar"

### 🗑️ Gerenciamento da Lixeira
1. Arquivos deletados vão para a **lixeira automaticamente**
2. Acesse via **ícone da lixeira** no menu
3. **Restaure** arquivos específicos ou todos
4. **Esvazie** a lixeira para exclusão permanente

### 🗜️ Operações ZIP
1. **Selecione** arquivos/pastas com checkboxes
2. Clique em **"Criar ZIP"**
3. Configure **nome e compressão**
4. Aguarde o **processamento**

### 🔗 Compartilhamento
1. **Clique direito** no arquivo
2. Selecione **"Compartilhar"**
3. Configure **senha e expiração**
4. **Copie o link** gerado

## ⚙️ Configurações Detalhadas

### 🛡️ Segurança
```json
{
  "SENHA": "sua_senha_aqui",
  "configTime": 35,
  "permissionAdmin": true
}
```

### 📁 Permissões de Sistema
```json
{
  "allow_delete": true,
  "allow_upload": true,
  "allow_create_folder": true,
  "allow_create_file": true,
  "allow_direct_link": true,
  "allow_show_folders": true
}
```

### 📦 Limites e Filtros
```json
{
  "max_upload_size_mb": 1000,
  "disallowed_patterns": ["*.exe", "*.php"],
  "hidden_patterns": [".*", "*.log"]
}
```

## 🌟 Exemplos de Uso

### Exemplo 1: Upload e Organização
```
1. Faça login no sistema
2. Navegue para a pasta desejada
3. Arraste arquivos para upload
4. Crie pastas para organização
5. Mova arquivos entre pastas
```

### Exemplo 2: Edição de Código
```
1. Abra um arquivo .html/.css/.js
2. O editor será carregado automaticamente
3. Use Ctrl+F para buscar
4. Ative o preview para Markdown
5. Salve com Ctrl+S
```

### Exemplo 3: Backup e Compressão
```
1. Selecione múltiplos arquivos
2. Clique em "Criar ZIP"
3. Nomeie como "backup_2024.zip"
4. Escolha compressão nível 9
5. Download do ZIP gerado
```

### Exemplo 4: Compartilhamento Seguro
```
1. Clique direito em arquivo importante
2. Selecione "Compartilhar"
3. Defina senha: "senhaSegura123"
4. Expira em: 24 horas
5. Máximo 5 downloads
6. Envie o link por email
```

## 🐛 Solução de Problemas

### Upload Falhando
- Verifique o **tamanho do arquivo** (limite: 1GB padrão)
- Confirme **permissões de escrita** no diretório
- Verifique **configurações PHP** (post_max_size, upload_max_filesize)

### Editor não Carregando
- Confirme conexão com **CDN do CodeMirror**
- Verifique **console do navegador** para erros JavaScript
- Teste com **arquivo de texto simples** primeiro

### Lixeira não Funcionando
- Verifique **permissões** da pasta `.trash`
- Confirme **espaço em disco** disponível
- Teste **criação manual** da pasta `.trash`

### Links de Compartilhamento
- Verifique **pasta `.shares`** existe
- Confirme **configurações de URL** do servidor
- Teste **acesso direto** ao share.php

## 🔄 Atualizações e Manutenção

### Backup Regular
```bash
# Backup completo do sistema
tar -czf backup_files_$(date +%Y%m%d).tar.gz /caminho/para/files/

# Backup apenas das configurações
cp files_config.json files_config_backup.json
```

### Limpeza Automática
- **Links expirados** são removidos automaticamente
- **Arquivos temporários** de ZIP são limpos
- **Sessões antigas** expiram conforme configurado

### Monitoramento
- Verifique **logs do servidor web** regularmente
- Monitore **uso de espaço** em disco
- Acompanhe **tentativas de login** falhadas

## 📞 Suporte

### Recursos Adicionais
- **Documentação inline** no próprio sistema
- **Tooltips** explicativos na interface
- **Mensagens de erro** detalhadas
- **Validações em tempo real**

### Personalização
- **Temas** do editor são extensíveis
- **Ícones** podem ser alterados via CSS
- **Idioma** pode ser modificado no código
- **Funcionalidades** podem ser habilitadas/desabilitadas

---

## 📄 Licença e Créditos

Sistema desenvolvido com **PHP**, **Bootstrap 5**, **CodeMirror**, **Font Awesome** e outras tecnologias open source.

**Versão**: 2.0  
**Última atualização**: Outubro 2024  
**Compatibilidade**: PHP 7.4+, Navegadores modernos  

---

**🎯 Aproveite todas as funcionalidades do seu novo gerenciador de arquivos!**
