# Checklist de homologação e entrega

## Produto
- [x] Portal institucional com identidade FIXXAR GROUP.
- [x] Cinco universos apresentados, com links apenas para os dois endereços confirmados.
- [x] Filtros por universo e detalhes em modal.
- [x] Navegação responsiva e acessível por teclado.
- [x] Respeito à preferência de movimento reduzido.
- [x] Metadados SEO, Open Graph, Twitter Card, JSON-LD, robots.txt e sitemap.
- [x] Configuração Apache para hospedagem estática na DreamHost.
- [x] Guia de publicação sem contratação de serviços adicionais.

## Validação técnica
- [x] Auditoria estática do código e dos metadados adicionada ao projeto.
- [x] Verificação estrutural dos cinco universos e links confirmados adicionada.
- [x] Executar `npm run verify` no GitHub Actions (Node.js 22).
- [x] Executar `npm run build` no GitHub Actions (artefato de produção gerado).
- [x] Integração frontend/API, autenticação, CMS, papéis e contingência documentados e verificados estaticamente.
- [ ] Executar testes funcionais com PHP/MySQL no ambiente DreamHost real.
- [ ] Aprovar a tipografia final dos SVGs da marca.
- [ ] Conferir o resultado visual em navegador desktop e celular.
- [ ] Confirmar headers e regras Apache no plano/servidor DreamHost real.

## Publicação
- [ ] Confirmar Document Root no painel DreamHost.
- [ ] Confirmar SSL para domínio raiz e www.
- [ ] Fazer backup do conteúdo atual do Document Root.
- [ ] Publicar o conteúdo de `dist/` após autorização expressa.
- [ ] Validar domínio, sitemap e robots após publicação.

## Pendências externas
- Acesso à conta DreamHost e confirmação do Document Root não estão disponíveis nesta etapa.
- A publicação, alterações DNS/SSL e substituição de arquivos de hospedagem não são executadas sem autorização expressa.
- Não há contato público cadastrado porque os dados oficiais não foram fornecidos.
