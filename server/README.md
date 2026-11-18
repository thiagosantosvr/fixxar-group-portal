# FIXXAR GROUP — API administrativa

Backend próprio para hospedagem PHP 8.1+ com MySQL/MariaDB. Foi separado em `server/` para manter o frontend estático e a API isolada. Nenhuma credencial é armazenada no Git.

## Pré-requisitos no servidor

- PHP 8.1 ou superior com PDO MySQL, mbstring e sessões habilitados.
- MySQL 8 ou MariaDB compatível com colunas JSON.
- HTTPS ativo no domínio.
- Acesso ao gerenciador de banco e, para criar o primeiro administrador, SSH/CLI.

## Instalação controlada

1. Crie um banco MySQL exclusivo para FIXXAR GROUP no painel da hospedagem.
2. Importe `server/database/schema.sql` nesse banco vazio.
3. Copie `server/config.example.php` para `server/config.php`.
4. Preencha host, nome, usuário e senha do banco. Configure a origem HTTPS canônica.
5. Garanta que `server/config.php` não seja acessível pela web (o arquivo fica acima da pasta pública da API).
6. Execute via SSH: `php server/bin/create-admin.php`. A senha deve ter pelo menos 14 caracteres; é armazenada com Argon2id.
7. Configure o servidor para encaminhar `/api/*` para `server/api/index.php`, conforme a raiz de publicação adotada.
8. Teste `GET /api/health` e depois o fluxo de sessão/autenticação.

Não envie `config.php`, senhas, dumps de produção ou credenciais para o repositório.

## Rotas implementadas

| Método | Rota | Acesso | Função |
|---|---|---|---|
| GET | /api/health | Público | Verifica API e conexão MySQL |
| POST | /api/contact | Público | Valida e registra uma mensagem de contato |
| POST | /api/auth/login | Público | Autenticação por e-mail e senha |
| GET | /api/auth/session | Sessão | Consulta usuário e token CSRF |
| POST | /api/auth/logout | Admin + CSRF | Encerra sessão |
| GET | /api/admin/bootstrap | Admin | Carrega páginas, marcas, mensagens e configurações |
| PATCH | /api/admin/pages/home | Editor + CSRF | Salva rascunho da página inicial |
| PUT | /api/admin/universes/{slug} | Editor + CSRF | Cria/atualiza universo |
| PATCH | /api/admin/messages/{id} | Equipe + CSRF | Atualiza status de mensagem |
| PATCH | /api/admin/settings | Owner/Admin + CSRF | Atualiza configurações |

## Segurança aplicada

- PDO com prepared statements e exceções.
- Sessão HttpOnly, SameSite Strict e Secure configurável.
- Regeneração do identificador de sessão no login.
- Token CSRF para operações de escrita.
- Controle de papéis (owner, administrator, editor, support).
- Validação de URLs HTTPS para links externos.
- Respostas sem cache e cabeçalhos básicos de segurança.
- Auditoria de login, logout e alterações administrativas.
- Erros internos registrados no log do servidor, sem expor detalhes ao visitante.

## Integração pendente

O frontend administrativo atual ainda opera em modo de demonstração e grava rascunhos no navegador. A API está preparada, mas não conectada ao painel. Antes de produção, implementar o cliente de sessão/CSRF, trocar dados de demonstração por respostas reais, revisar CORS e limites de login, configurar backup/retention e executar testes de segurança no ambiente real.

A publicação no DreamHost não foi realizada. Não há configuração de hospedagem, banco ou credenciais fornecidas/validadas neste projeto.
