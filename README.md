# FIXXAR GROUP — Portal Institucional

Portal institucional de entrada para o ecossistema Fixxar. Apresenta o grupo e encaminha visitantes aos universos de negócio, sem substituir os sites individuais nem assumir funções de sistema operacional.

**Headline:** Ideias que ganham forma. Marcas que deixam marcas.

## Identidade e direção

- Visual híbrido: preto profundo, branco editorial e laranja Fixxar.
- Linguagem cinematográfica com profundidade e movimento moderado.
- Estrutura de ecossistema: apresentar o grupo e direcionar aos negócios.
- Pilares: Criatividade, Comunicação, Conhecimento e Inovação.

## Universos

- Fixxar Agência — https://agencia.fixxarestudios.com.br
- Fixxar Fotografia — https://thiago.fixxarestudios.com.br
- Entre o Céu e Papel — endereço ainda não confirmado.
- Tio Moisés — endereço ainda não confirmado.
- Fixxar Brindes — endereço ainda não confirmado.

Os três universos sem URL confirmada são apresentados como em desenvolvimento. Nenhum endereço ou contato foi inventado.

## Tecnologia e custo

React 19, TypeScript, Vite, Framer Motion e Lucide React. Requer Node.js 20 ou superior para desenvolvimento e geração do build. O resultado é HTML/CSS/JS estático: não exige servidor Node, banco de dados, PHP, serviço pago ou assinatura adicional na DreamHost.

## Desenvolvimento

```bash
npm install
npm run dev
npm run verify
npm run build
```

A versão publicável é gerada em `dist/`. Consulte [DEPLOY-DREAMHOST.md](./DEPLOY-DREAMHOST.md) para instruções completas de hospedagem compartilhada Apache.

## Governança

O repositório `thiagosantosvr/fixxar-group-portal` e a branch `main` são a fonte oficial da verdade. Lovable é destinado somente à visualização e validação, nunca à edição principal do código. Não conectar, importar ou reutilizar ambientes de outros projetos Fixxar.

Não publicar nem alterar domínio, DNS, SSL ou FTP sem autorização expressa.

## Situação

A implementação do portal e a preparação para hospedagem estática estão no repositório. A validação de build em ambiente com dependências, a conferência no navegador e a publicação no servidor DreamHost ainda precisam ser realizadas. Os SVGs da marca são estudos editáveis e dependem de aprovação tipográfica final.
