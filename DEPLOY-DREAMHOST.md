# Publicação do FIXXAR GROUP na DreamHost

Este portal foi preparado como aplicação estática. A hospedagem não precisa de Node.js, banco de dados, PHP, Supabase, serviços externos pagos ou processo permanente no servidor.

## Requisitos gratuitos

- Conta de hospedagem DreamHost já existente, com o domínio configurado nela.
- Node.js 20 ou superior apenas na máquina usada para gerar a versão de produção.
- npm incluído com o Node.js.
- Cliente SFTP (por exemplo, Cyberduck ou FileZilla) ou gerenciador de arquivos da hospedagem.

Não contrate plano, plugin, serviço, banco de dados ou integração adicional para publicar este portal.

## Gerar a versão de produção

Na raiz do repositório:

```bash
npm install
npm run verify
npm run build
```

O Vite gera o site pronto para hospedagem na pasta `dist/`. O arquivo `public/.htaccess` é copiado para `dist/.htaccess`.

## Enviar para a DreamHost

1. No painel DreamHost, confirme que `www.fixxarestudios.com.br` está associado ao domínio de hospedagem já contratado.
2. Localize o diretório Document Root informado para esse domínio. Em instalações comuns ele pode ser `~/www.fixxarestudios.com.br/`, mas use o caminho exibido no painel, não presuma o nome.
3. Faça backup dos arquivos que já estiverem nesse diretório antes de substituir qualquer conteúdo.
4. Envie **o conteúdo de `dist/`**, e não a pasta `dist` inteira, para o Document Root.
5. Confirme que `index.html`, `.htaccess` e a pasta `assets/` estão diretamente no Document Root.\n6. Para ativar a API administrativa, envie também a pasta `server/` para o Document Root, preservando `server/api/` e `server/.htaccess`. Mantenha `server/config.php` preenchido apenas no servidor; o `.htaccess` da pasta `server/` bloqueia acesso HTTP direto a esse arquivo.\n7. Importe `server/database/schema.sql` em um banco dedicado e configure `server/config.php` com as credenciais reais. Crie o primeiro usuário via SSH com `php server/bin/create-admin.php`.\n8. Confirme `GET /api/health` antes de conectar o painel. Não ative a API sem configurar banco, HTTPS, usuário administrador e backups.
6. Abra o domínio em janela anônima e teste desktop, celular, navegação, filtros, modais e links externos.
7. Verifique `https://www.fixxarestudios.com.br/robots.txt` e `https://www.fixxarestudios.com.br/sitemap.xml`.

## HTTPS, domínio e DNS

O portal usa URLs canônicas HTTPS. Antes de divulgar, confirme no painel DreamHost que o certificado SSL gratuito Let's Encrypt está ativo para o domínio raiz e para o subdomínio www, e que ambos apontam para o Document Root correto. Configure o redirecionamento para uma única versão canônica (www) somente depois de validar o certificado e a resolução DNS.

Este repositório **não altera** DNS, SSL, domínio, FTP, arquivos existentes nem configurações da conta. Essas ações ficam pendentes até autorização e acesso do responsável pela hospedagem. Não apague arquivos desconhecidos do Document Root.

## Atualizações

Para cada atualização, gere novamente a pasta `dist/`, faça backup da versão publicada e envie os novos arquivos. Os nomes dos assets do Vite são versionados, então mantenha a pasta `assets/` completa correspondente ao mesmo build.

## Limitações intencionais

O portal público ainda não possui formulário de contato conectado. A API administrativa já tem base de autenticação, controle de papéis, CSRF, auditoria e operações de conteúdo, mas o frontend do painel continua em modo de demonstração até ser integrado e validado no servidor. Não invente e-mails, telefones, endereços ou links para preencher lacunas.
