# F7-03 — Como aplicar o mapa 301 (`wp-301-map.txt`)

**ADR 0044 §301.** Durante o `run`, o importador grava o mapa em
`system/storage/cache/wp-301-map.txt` no formato:

```
<slug-antigo> <url-nova-absoluta>
# ex.: novo-post-um https://example.com/catalog/novo-post-um
```

*(à esquerda: slug do WP legado; à direita: URL pública do blog novo,
baseada em `public_url` do formulário).*

O blog interno resolve `/<slug>/` via seo_url (ADR 0042). Se os slugs
forem preservados (são — o importador não renomeia; só sufixa quando há
colisão cross-entidade), **as URLs antigas e as novas coincidem**: o
crawler/humanos já chegam ao post com o mesmo endereço. O mapa cobre
os casos em que a URL precisa MUDAR de fato.

## Cenário A — MESMO domínio (o WP sai e o ReamurCMS assume)

As URLs antigas (`/<slug>/`) colidem com keywords do app novo; o router
rewrite resolve por keyword e o post abre 200 — **nenhuma regra 301
necessária**. Publique o novo site, mantenha `wp-301-map.txt` como
auditoria (garante que todo slug legado existe de fato no app novo —
valide depois com um crawler ou `curl -I` em loop).

> Exceções cenário A: slugs sufixados por colisão (`foo` virou
> `foo-2`). Só para ESSAS linhas, adicione regras no `.htaccess` da raiz
> do app novo (antes do bloco do router), geradas a partir do mapa:
>
> ```
> # geradas a partir das linhas sufixadas de wp-301-map.txt
> Redirect 301 /foo/ https://example.com/catalog/foo-2
> Redirect 301 /cookies/ https://example.com/catalog/cookies-2
> ```
>
> A query do mapa já entrega o par exato: `awk '!/^#/ && NF==2 {printf "Redirect 301 /%s/ %s\n", $1, $2}' system/storage/cache/wp-301-map.txt`

## Cenário B — Domínios DISTINTOS (WP antigo segue vivo por um tempo)

No servidor do WP ANTIJO, cole redirects 301 permanentes apontando ao
domínio novo. Gere a partir do mapa:

```
awk '!/^#/ && NF==2 {printf "Redirect 301 /%s/ %s\n", $1, $2}' system/storage/cache/wp-301-map.txt >> .htaccess
```

Ou, com mod_rewrite (equivalente):

```
RewriteEngine On
# ...uma linha por slug (gerada do mapa):
RewriteRule ^novo-post-um/?$ https://novo.example.com/novo-post-um [R=301,L]
```

## Verificação pós-corte

```
awk '!/^#/ && NF==2 {print $1}' system/storage/cache/wp-301-map.txt | \
  while read s; do code=$(curl -s -o /dev/null -w "%{http_code}" "https://novo.example.com/$s"); \
  [ "$code" = "200" ] || echo "REVISAR $s → $code"; done
```

Zero linhas `REVISAR` = migração 100% resolvida. Submeta o
sitemap.xml do app novo no Search Console em seguida.
