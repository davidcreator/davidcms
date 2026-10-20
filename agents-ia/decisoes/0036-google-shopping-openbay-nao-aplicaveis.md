# ADR 0036 — Google Shopping e OpenBay: não aplicáveis ao modelo do projeto (sem código)

- **Status:** Aceita — 2026-10-20 (F5 Tarefa 04 — avaliação de aplicabilidade)
- **Contexto:** O plano da Fase 5 pedia explicitamente a **avaliação** de
  Google Shopping/OpenBay ("avaliar aplicabilidade"), não implementação
  cega. A pergunta objetiva: "algum desses dois trilhos serve ao ReamurCMS
  de hoje?".
- **Leitura real (prova, HEAD `4f041ab`):**
  - **OpenBay existe só como biblioteca órfã da Fase 0**:
    `system/library/openbay/{amazon,amazonus,ebay,etsy,fba}.php` +
    `system/library/openbay.php`. `grep -rln openbay` fora da pasta =
    **0** — nenhum controller/model o carrega, nenhuma rota o usa. É
    esqueleto OpenCart-derived, na mesma situação do `system/webhook/`
    declarado morto no ADR 0033.
  - **Google Shopping/Merchant Center: nada existe** — zero controllers
    `feed/` no catálogo, zero menções a merchant/shopping no código.
  - **Domínio:** não há catálogo de produtos com preço. `rms_order_product`
    é item de orçamento com `price = 0.0000` (Dicionário de Dados §6); o
    `total` da `rms_order` é atribuído pelo admin depois da análise. O
    site vende **serviços sob consulta**, não mercadorias.

- **Decisão: NÃO APLICÁVEL — nenhuma linha de código é escrita.**

  1. **OpenBay (eBay/Amazon/Etsy/FBA):** conectores de marketplaces de
     **varejo físico** — pressupõem SKU, estoque, precificação fixa por
     produto e fulfillment. Nada disso existe no modelo; a função de
     "vender em marketplace" já foi resolvida dentro do projeto pelos
     ADRs 0034/0035 (Merchant próprio = Mercado Pago Marketplace).
     **Decisão:** manter a biblioteca intacta no repositório, declarada
     *superseded* por esta ADR, com remoção adiada para uma tarefa de
     limpeza da Fase 6 (mesma política do legado `system/webhook` —
     preserva-se o histórico Fase-0; excisão cirúrgica não é escopo de
     feature).

  2. **Google Shopping:** rejeitado por **política do Merchant Center**,
     não por falta de tempo: ofertas elegíveis ao Shopping exigem **preço
     declarado na página**, URL de produto com carrinho/direct-checkout,
     identificadores (GTIN/MPN/marca), imagem e política de devolução de
     mercadoria física. Orçamento sob consulta ("preço: solicitar") é
     **reprovado** na revisão do feed — e forçar preço simbólico seria
     fraude de catálogo, não feature. Nenhum pré-requisito estrutural
     existe nem faria sentido criar.

  3. **O que REALMENTE atende à intenção original** (visibilidade/
     aquisição orgânica) já está no projeto ou é dívida registrada da
     Fase 6: `sitemap.xml` dinâmico (F4-07, ADR 0031), SEO on-page por
     idioma (F4-02) e, quando desejado, **schema.org `Service`/`Offer`
     com `priceSpecification` sob-consulta NÃO é o trilho errado** — o
     certo é `Service` + `areaServed` declarado no template institucional
     (F6, sem nada a ver com feed de Shopping).

- **Consequências:**
  - **Fase 5 encerra 3/3** (F5-01 webhooks ✅ · F5-02 OAuth marketplace ✅
    · F5-03 split/checkout+notify ✅ · F5-04 avaliação ✅).
  - Backlog Fase 6 acumulado por esta cadeia: remoção do legado morto
    (`system/webhook/` ADR 0033, `system/library/openbay*/` esta ADR),
    keyring/cifragem de tokens do MP em repouso (ADR 0034 §6), schema.org
    Service (esta), validação sandbox real do MP quando o usuário
    provisionar credenciais de teste (ADRs 0034/0035).
  - Nenhum zip de código: entrega documental (esta ADR) aplicável em
    cadeia sobre `4f041ab`.

- **Alternativas avaliadas e rejeitadas:** gerar feed Google Shopping
  com preços fictícios (fraude de catálogo); implementar conectores
  OpenBay "porque já existem" (domínio inexistente: 0 produtos com
  SKU/preço/estoque); remover legado agora dentro da F5-04 (mistura
  cleanup com avaliação — vira tarefa própria da Fase 6).
