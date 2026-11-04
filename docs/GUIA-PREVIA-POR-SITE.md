# Prévia comparativa por site (Frente M)

A frente J compara hosts allowlistados; a K usa isso para o idioma; a L criou a
dimensão `site` nas condições. Faltava alinhar a **prévia comparativa** da
frente C com essa dimensão: hoje o usuário digita um **host** na sonda do card
**Sites e domínios** e a prévia resolve o site por ele em cada painel.

Isso tem um efeito colateral real: se o **rascunho mudou o host** do site
`shop`, o mesmo host da sonda casa com `shop` no publicado e com `primary` no
rascunho. A comparação passa a ser "shop × primary" sem o usuário perceber —
as cores/condições divergem por um motivo que a fita de contexto não mostra.

## O que a frente M entrega

- O card **Comparar publicado × rascunho** ganha o `<select name="preview_site">`
  com os 8 IDs allowlistados de `ThemeSites::IDS` (`primary`…`staging`) e a
  opção padrão **Simular host**. Nenhuma chave de tradução nova: reutiliza
  `text_sites`, `text_sites_probe` e `text_sites_probe_help`.
- Com um ID escolhido, `compare()` usa o **host da própria política** de cada
  painel para chamar `ThemeSites::match()`: o publicado usa o host que ele
  configurou para aquele ID, e o rascunho usa o dele. Resultado: compara-se
  **`shop` publicado × `shop` rascunho**, mesmo com hosts diferentes. A J não
  é alterada — nenhum método, chave ou formato novo.
- A sonda de host continua **soberana** quando o select está vazio (padrão).
  Compatibilidade total com A–L: sem `preview_site`, o comportamento é
  byte a byte o mesmo da frente L.
- A **fita de contexto** da prévia passa a exibir o site allowlistado usado
  (guard para chamadores antigos sem a chave; tudo escapado com `htmlspecialchars`).
- O `theme-condition-builder.js` sincroniza o select da prévia com o campo
  **Site** do simulador (frente L). Escolher `shop` na prévia faz o simulador
  avaliar as condições como `shop`. Sem JavaScript, nada muda: o formulário da
  prévia e o `context[site]` continuam validados no servidor.

## Segurança e limites

`preview_site` só é aceito se estiver em `ThemeSites::IDS`. Um ID sem host
configurado naquela política, ou desabilitado, **cai de volta para a sonda**
(fail-closed via `ThemeSites::match()`); nunca é fabricado um site a partir de
texto livre. O host do request continua sendo **apenas comparado**, nunca
refletido em HTML, global Twig, CSS ou log. A comparação segue sem escrita:
nenhum `save`, `UPDATE`, sessão, cookie, cron ou arquivo.

Nenhuma query, migration, coluna, tabela, schema, chave de tradução (418 ×3
intactas), CSS, manifesto, global Twig, `|raw`, `<script>` inline, `fetch`/
`eval`/`innerHTML` novo, upload, GLB, WebGL, BDREditor, `custom_js`, vhost/DNS
ou escrita remota. Os 14 `CHECKSUMS*.txt` permanecem intactos.

**Resolvido pela frente Q:** o select escolhe o site por **ID allowlistado**
e `ThemeSites::byId()` resolve cada painel diretamente, sem voltar a
`match(host)`. Mesmo que dois sites compartilhem o mesmo host, ou que o
rascunho tenha mudado o host, a comparação é sempre **`shop` × `shop`** —
o ID escolhido é a fonte de verdade, como já acontece na sonda de rollout
(O) e na sessão de idioma (P). Se o ID estiver ausente ou desabilitado em
qualquer painel, a prévia cai para a sonda de host (fail-closed), sem
fabricar site a partir de texto livre. A sonda de host continua soberana
quando o select está vazio e continua simulando fielmente o `match()` do
request real (onde só existe o `HTTP_HOST`).

## Exemplo

Publicado: `shop.host = loja.exemplo.com` → cor `#2563eb`.
Rascunho: `shop.host = nova.exemplo.com` → cor `#b45309`.
Selecionar **`shop`** na prévia mostra os dois painéis como `shop`, com a fita
`... · Sites <code>shop</code>` e o diff real das cores — sem digitar host.

Aplicar os 5 arquivos completos deste pacote (o guia é novo) e rodar
`bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
