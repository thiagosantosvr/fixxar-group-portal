# FIXXAR GROUP — API administrativa

Backend próprio para hospedagem PHP 8.1+ com MySQL/MariaDB. Foi separado em `server/` para manter o frontend estático e a API isolada. Nenhuma credencial é armazenada no Git.

## Pré-requisitos no servidor

- PHP 8.1 ou superior com PDO MySQL, mbstring e sessões habilitados.
- MySQL 8 ou MariaDB compatível com colunas JSON.
- HTTPS ativo no domínio.
- Acesso ao gerenciador de banco e ao Gerenciador de Arquivos da hospedagem; não é necessário usar terminal.

## Instalação controlada

1. Crie um banco MySQL exclusivo para FIXXAR GROUP no painel da hospedagem.
2. Importe `server/database/schema.sql` nesse banco vazio. Em instalações já existentes, confira os índices com `SHOW INDEX FROM admin_audit_log` e aplique uma única vez `server/database/migrations/002_login_throttle_index.sql` se o índice `idx_audit_login_throttle` ainda não existir.
3. Copie `server/config.example.php` para `server/config.php`.
4. Preencha host, nome, usuário e senha do banco. Configure a origem HTTPS canônica.
5. Garanta que `server/config.php` não seja acessível pela web (o arquivo fica acima da pasta pública da API).
6. Configure um `setup_token` privado de pelo menos 64 caracteres em `server/config.php`, diferente da `security_key`.
7. Abra `https://www.fixxarestudios.com.br/server/instalar-admin.php`, informe o token, nome, e-mail e uma senha com pelo menos 14 caracteres para criar a primeira conta proprietária. O instalador só funciona enquanto não existir um proprietário ativo; após o sucesso, exclua imediatamente `server/instalar-admin.php` pelo Gerenciador de Arquivos.
8. Configure o servidor para encaminhar `/api/*` para `server/api/index.php`, conforme a raiz de publicação adotada.
9. Garanta permissão de escrita PHP na pasta `server/storage/media` (o diretório é criado no primeiro upload; não conceda permissão pública de execução).
10. Ajuste `upload_max_filesize` e `post_max_size` do PHP para pelo menos 12 MB, mantendo o limite da aplicação em 10 MB.
11. Teste `GET /api/health` e depois o fluxo de sessão/autenticação.

Não envie `config.php`, senhas, dumps de produção ou credenciais para o repositório.

## Rotas implementadas

| Método | Rota | Acesso | Função |
|---|---|---|---|
| GET | /api/health | Público | Verifica API e conexão MySQL |
| POST | /api/contact | Público | Valida e registra uma mensagem de contato |
| POST | /api/auth/login | Público | Autenticação por e-mail e senha |
| GET | /api/auth/session | Sessão | Consulta usuário e token CSRF |
| POST | /api/auth/logout | Admin + CSRF | Encerra sessão |
| GET | /api/public/content | Público | Entrega apenas página inicial publicada e universos não arquivados |
| GET | /api/media/{storage_name} | Público | Entrega imagem cadastrada após consulta ao banco |
| GET | /api/admin/audit-log | Owner | Consulta histórico de ações administrativas |
| GET | /api/admin/bootstrap | Admin | Carrega páginas, marcas, mensagens, configurações e biblioteca de mídia |
| GET | /api/admin/users | Owner | Lista contas administrativas |
| POST | /api/admin/users | Owner + CSRF | Cria conta com senha Argon2id |
| PATCH | /api/admin/users/{id} | Owner + CSRF | Atualiza nome, papel, acesso e senha |
| PUT | /api/admin/pages/{slug} | Editor + CSRF | Salva rascunho de página institucional independente |
| POST | /api/admin/pages/{slug}/publish | Editor + CSRF | Publica página institucional no CMS |
| PATCH | /api/admin/pages/home | Editor + CSRF | Salva rascunho da página inicial |
| POST | /api/admin/pages/home/publish | Editor + CSRF | Publica a versão salva da página inicial |
| PUT | /api/admin/universes/{slug} | Editor + CSRF | Cria/atualiza universo |
| PATCH | /api/admin/messages/{id} | Equipe + CSRF | Atualiza status de mensagem |
| PATCH | /api/admin/settings | Owner/Admin + CSRF | Atualiza configurações |
| POST | /api/admin/media | Editor + CSRF | Envia imagem JPG, PNG ou WebP de até 10 MB |
| DELETE | /api/admin/media/{id} | Owner/Admin + CSRF | Remove arquivo da biblioteca e do armazenamento |

## Segurança aplicada

- PDO com prepared statements e exceções.
- Sessão HttpOnly, SameSite Strict e Secure configurável, com strict mode, cookies exclusivos e transporte por cookie obrigatório.
- Regeneração do identificador de sessão no login.
- Token CSRF para operações de escrita.
- Controle de papéis (owner, administrator, editor, support).
- Alterações de papel e suspensão de proprietários são protegidas por transação e bloqueios de linha (`SELECT ... FOR UPDATE`), preservando o último proprietário ativo mesmo diante de requisições concorrentes.
- Validação de URLs HTTPS para links externos.
- Respostas sem cache e cabeçalhos básicos de segurança.
- Auditoria de login, logout, falhas de autenticação e alterações administrativas.
- Bloqueio temporário por combinação de e-mail e IP após cinco falhas em quinze minutos, persistido no banco de auditoria e com cabeçalho `Retry-After`.
- Erros internos registrados no log do servidor, sem expor detalhes ao visitante.

## Estado atual e pendências de produção

O frontend do painel está integrado à API autenticada para sessão, páginas, universos, mensagens, configurações, biblioteca de mídia e contas administrativas. Quando a API não está configurada, o painel entra em modo de demonstração local; quando configurada, exige autenticação real. O gerenciamento de usuários é exclusivo do proprietário; permite criar contas, alterar papéis e suspender acessos, preservando pelo menos um proprietário ativo. A página inicial pode ser salva como rascunho e publicada explicitamente. Páginas institucionais adicionais podem ser criadas, editadas e publicadas individualmente no CMS. O frontend resolve páginas públicas por slug, exibe somente conteúdos publicados e inclui os links publicados na navegação principal. Quando a API não está configurada, o portal mantém a apresentação estática de contingência.

A biblioteca aceita imagens JPG, PNG e WebP de até 10 MB. Além do MIME detectado por `finfo`, o upload valida a assinatura/dimensões com `getimagesize` e rejeita imagens acima de 40 milhões de pixels. O cadastro e o log são transacionais; se o banco falhar, o arquivo recém-enviado é removido. Na exclusão, o arquivo é movido temporariamente para uma extensão não pública, o registro é removido em transação e o arquivo temporário é apagado após o commit; em rollback, tenta restaurar o arquivo original. Os arquivos ficam em `server/storage/media`, fora do acesso HTTP direto; a entrega pública passa por uma rota que valida o nome no banco. Exclusão de mídia exige papel owner/administrator. Antes de produção, ainda é necessário validar permissões de escrita, limites PHP, backup e retenção no ambiente real, definir `security_key` aleatória no `config.php` (mínimo recomendado: 32 bytes aleatórios), revisar política de proxy/IP e CORS, e executar testes de segurança. A limitação de login usa a tabela `admin_audit_log` já criada pelo schema.

A publicação no DreamHost não foi realizada. Não há configuração de hospedagem, banco ou credenciais fornecidas/validadas neste projeto.


## Páginas institucionais públicas

Páginas criadas no CMS usam uma rota pública de primeiro nível no formato `https://www.fixxarestudios.com.br/{slug}`. O front-end consulta `GET /api/public/page?slug={slug}`; a API retorna apenas registros com status `published`. Conteúdo em rascunho ou inexistente retorna HTTP 404. A lista de páginas publicadas é incluída em `GET /api/public/content` para alimentar a navegação principal.

Slugs são limitados a letras minúsculas, números e hífens (2–100 caracteres). Os caminhos reservados `api`, `painel`, `assets`, `storage` e `server` não podem ser usados. O roteamento da hospedagem deve manter a regra SPA do `public/.htaccess` depois da regra de encaminhamento da API.

## Backup administrativo

A ação **Exportar backup** gera um JSON versionado (`fixxar-group-backup`, schema 2) com o estado editável da página inicial, páginas institucionais, universos, mensagens, configurações e índice de mídia. O arquivo não contém os binários das imagens, contas administrativas, hashes de senha nem tokens de sessão. Portanto, ele é um backup estruturado de conteúdo, não substitui o backup completo do banco de dados e da pasta `server/storage/media`. Armazene-o em local protegido, pois pode conter dados pessoais enviados pelo formulário de contato.
