# Publicação do FIXXAR GROUP na DreamHost

O portal possui dois modos de operação:

- **Portal estático:** a interface pública pode ser servida como arquivos estáticos, sem Node.js ou banco no servidor. Nesse modo, o conteúdo de contingência é exibido, mas o formulário de contato não grava mensagens e o painel `/painel` fica em demonstração.
- **Portal integrado:** para ativar formulário de contato, CMS, autenticação e painel administrativo reais, são necessários PHP 8.1+, MySQL/MariaDB e a API em `server/`.

Não é necessário contratar serviços pagos adicionais. Use a hospedagem DreamHost já existente, se ela atender aos requisitos técnicos.

## Requisitos

- Conta de hospedagem DreamHost já existente e domínio associado.
- Node.js 20+ e npm somente na máquina que gera o build.
- Para o modo integrado: PHP 8.1+ com PDO MySQL e mbstring, MySQL/MariaDB, HTTPS e acesso ao gerenciador de banco; SSH/CLI para criar o primeiro administrador.
- Cliente SFTP ou gerenciador de arquivos da hospedagem.

## Gerar e validar a versão

Na raiz do repositório:

```bash
npm install
npm run verify
npm run build
```

O Vite gera o site em `dist/`. O arquivo `public/.htaccess` é copiado para `dist/.htaccess`. O build e as verificações foram aprovados no GitHub Actions; isso não substitui os testes no servidor real.

## Instalação no Document Root

1. No painel DreamHost, confirme que `www.fixxarestudios.com.br` está associado à hospedagem existente.
2. Localize o Document Root informado no painel. Não presuma o caminho.
3. Faça backup integral do conteúdo atual antes de substituir qualquer arquivo.
4. Envie o **conteúdo** de `dist/` para o Document Root, preservando `index.html`, `.htaccess` e `assets/`.
5. Para operar apenas o portal estático, teste a navegação e os recursos visuais. O formulário e o painel não estarão integrados sem a API.

## Ativar o modo integrado (PHP/MySQL)

1. Confirme que o plano oferece PHP 8.1+, PDO MySQL, mbstring, MySQL/MariaDB e SSH/CLI.
2. Crie um banco exclusivo para FIXXAR GROUP e importe `server/database/schema.sql`.
3. Execute também `server/database/upgrade-yad.sql` no mesmo banco. Esse script cadastra a YAD Sistemas Integrados sem duplicar registros nem alterar as demais empresas; execute-o tanto em instalações novas quanto em bancos já existentes.
3. Copie `server/config.example.php` para `server/config.php` **somente no servidor**. Preencha credenciais reais, origem HTTPS canônica e uma `security_key` aleatória de pelo menos 32 bytes. Nunca envie esse arquivo ao Git.
4. Envie a pasta `server/` para o Document Root, preservando `server/api/`, `server/bin/`, `server/database/` e `server/.htaccess`. Confirme que o bloqueio HTTP de arquivos privados está ativo.
5. Configure o encaminhamento de `/api/*` para `server/api/index.php`. A regra da API deve ter prioridade sobre o fallback SPA de `public/.htaccess`.
6. Execute via SSH: `php server/bin/create-admin.php` e crie a primeira conta proprietária com senha forte.
7. Garanta escrita PHP em `server/storage/media` e configure `upload_max_filesize` e `post_max_size` para pelo menos 12 MB.
8. Confirme `GET /api/health`, autenticação, encerramento de sessão, CSRF, formulário de contato, upload, CMS e permissões de cada papel.
9. Só então considere o painel e o formulário operacionais.

Consulte também `server/README.md` para rotas, requisitos e controles de segurança.

## Homologação de domínio e HTTPS

- Confirme no painel o Document Root correto.
- Confirme SSL válido para o domínio raiz e para `www`.
- Escolha uma única versão canônica (www) e configure redirecionamento somente após validar DNS e certificados.
- Teste desktop e celular, navegação, filtros, modais, links externos, `robots.txt` e `sitemap.xml`.
- No modo integrado, teste também `/api/health` e todos os fluxos descritos acima.

Este repositório não altera DNS, SSL, domínio, FTP, arquivos existentes nem configurações da conta. Publicação e mudanças na hospedagem dependem de autorização expressa. Não apague arquivos desconhecidos.

## Atualizações e backup

Para atualizar, gere novamente `dist/`, faça backup da versão publicada e envie os novos arquivos mantendo a pasta `assets/` correspondente ao mesmo build. No modo integrado, faça também backup do banco e de `server/storage/media`. O backup JSON do painel é apenas um backup estruturado de conteúdo; não substitui esses backups completos.

## Limitações e dados oficiais

Não invente e-mails, telefones, endereços ou links para preencher lacunas. O portal não deve ser considerado operacional em produção até concluir a homologação do ambiente real e a publicação autorizada.