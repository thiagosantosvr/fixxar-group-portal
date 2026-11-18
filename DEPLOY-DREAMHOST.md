# Publicação do FIXXAR GROUP na DreamHost

O portal público é compilado como aplicação estática (React/Vite) e pode ser hospedado sem processo Node.js permanente. O painel administrativo e o CMS utilizam uma API PHP própria e MySQL/MariaDB, que são opcionais para servir apenas a versão estática de contingência, mas necessários para habilitar autenticação, persistência de conteúdo e biblioteca de mídia.

## Requisitos

### Portal público estático
- Conta DreamHost existente, com o domínio configurado.
- Node.js 20 ou superior apenas na máquina usada para gerar o build.
- npm incluído com o Node.js.
- Cliente SFTP (por exemplo, Cyberduck ou FileZilla) ou gerenciador de arquivos da hospedagem.

### CMS e painel administrativo
- PHP 8.1 ou superior com PDO MySQL, mbstring e sessões habilitados.
- MySQL 8 ou MariaDB compatível com colunas JSON.
- HTTPS ativo no domínio.
- Acesso ao gerenciador de banco e, para criar o primeiro administrador, SSH/CLI.

Não contrate plano, plugin, serviço ou integração adicional para este portal. Utilize os recursos já disponíveis na hospedagem.

## Gerar a versão de produção

Na raiz do repositório:

```bash
npm install
npm run verify
npm run build
```

O Vite gera o site pronto para hospedagem na pasta `dist/`. O arquivo `public/.htaccess` é copiado para `dist/.htaccess`.

## Publicar o portal estático

1. No painel DreamHost, confirme que o domínio desejado está associado à hospedagem.
2. Localize o Document Root informado no painel. Não presuma o caminho: utilize exatamente o diretório exibido pela DreamHost.
3. Faça backup dos arquivos existentes nesse diretório antes de qualquer substituição.
4. Envie o **conteúdo de `dist/`**, e não a pasta `dist` inteira, para o Document Root.
5. Confirme que `index.html`, `.htaccess` e a pasta `assets/` estão diretamente no Document Root.
6. Abra o domínio em janela anônima e valide navegação, links, layout desktop e celular.

## Ativar o painel administrativo e o CMS

A ativação da API é uma etapa separada. Não envie arquivos de configuração com credenciais ao GitHub.

1. Crie um banco MySQL exclusivo para FIXXAR GROUP no painel da hospedagem.
2. Importe `server/database/schema.sql` nesse banco vazio.
3. Copie `server/config.example.php` para `server/config.php` e preencha host, nome, usuário e senha do banco, além da origem HTTPS canônica.
4. Mantenha `server/config.php` protegido contra acesso HTTP direto. A configuração deve permanecer somente no servidor.
5. Envie a pasta `server/` para o local previsto pela configuração de publicação, preservando os arquivos de proteção `.htaccess`.
6. Configure o encaminhamento de `/api/*` para `server/api/index.php`, conforme a raiz de publicação adotada.
7. Garanta permissão de escrita PHP em `server/storage/media`; não conceda permissão pública de execução.
8. Ajuste `upload_max_filesize` e `post_max_size` do PHP para pelo menos 12 MB. A aplicação mantém o limite de upload em 10 MB.
9. Crie o primeiro administrador via SSH/CLI com `php server/bin/create-admin.php`. Utilize uma senha forte de pelo menos 14 caracteres.
10. Teste `GET /api/health` e, em seguida, os fluxos de login, sessão, CSRF, edição, publicação e biblioteca de mídia.
11. Confirme que o portal público consome somente conteúdo publicado e que a versão estática de contingência continua funcional quando a API não estiver configurada.

Consulte também `server/README.md` para a lista de rotas, permissões e controles de segurança. Antes de produção, valide backups e retenção, permissões de armazenamento, limites de autenticação, CORS e segurança no ambiente real.

## HTTPS, domínio e DNS

O portal utiliza URLs canônicas HTTPS. Antes de divulgar, confirme no painel DreamHost que o certificado SSL gratuito Let's Encrypt está ativo para os nomes de domínio efetivamente utilizados. Configure o redirecionamento para uma única versão canônica somente depois de validar certificado e resolução DNS.

Este repositório não altera DNS, SSL, domínio, FTP, arquivos existentes nem configurações da conta. Essas ações dependem de autorização expressa e acesso do responsável pela hospedagem. Não apague arquivos desconhecidos do Document Root.

## Atualizações

Para cada atualização:

1. Gere novamente a pasta `dist/` e confirme que `npm run verify` e `npm run build` foram concluídos sem erros.
2. Faça backup da versão publicada.
3. Envie os novos arquivos de `dist/`, mantendo a pasta `assets/` completa correspondente ao mesmo build.
4. Se houver alteração na API ou no banco, revise as instruções e migrações correspondentes antes de atualizar o servidor.
5. Valide o portal e o painel após a atualização.

## Limitações e estado de publicação

A implementação do portal, painel e API está versionada no repositório. Isso não significa que o CMS esteja ativo em produção: a publicação na DreamHost, a configuração do banco, as credenciais, as permissões de armazenamento e a validação de segurança no servidor ainda precisam ser realizadas.

Não publique nem altere domínio, DNS, SSL, FTP ou arquivos da hospedagem sem autorização expressa.
