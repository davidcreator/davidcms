# ADR 0073 — Runtime Three.js canônico no System com entrega allowlisted pelo Catalog

| | |
| --- | --- |
| **Status** | proposta aprovada para implementação incremental |
| **Data** | 2026-10-26 |
| **Agente proponente** | orquestrador → system → catalog/admin |
| **Contexto** | O System já centraliza a política declarativa, mas vendor, runner de cenas e runtime de thumbs estavam espalhados em `catalog/view/js/`. O usuário determinou uma única fonte Three.js no System, mantendo a estrutura MVCL e evitando cópias e scripts órfãos. `system/.htaccess` proíbe exposição web direta do núcleo. |

## Decisão

1. Manter vendor Three.js, módulos de ciclo de vida, cenas nativas e runtime de thumbs em `dev/system/library/three/runtime/`, junto da configuração e manifesto.
2. Expor somente arquivos listados em `AssetManifest` pela rota `/catalog/three-runtime/{asset}`; o controller Catalog não aceita paths livres. O rewrite deve preceder o 404 para assets inexistentes.
3. Usar a mesma base pública e os mesmos módulos para Hero/404 e thumbs de Blog/CMS; manter no Catalog apenas markup, cenas/estilos específicos de apresentação e o adaptador de entrega.
4. Preservar fallback, carregamento condicional, cache HTTP e sandbox opaco para custom_js. Sem CDN, migration, cópia paralela dos módulos ou leitura arbitrária de filesystem.

## Alternativas consideradas

1. **Deixar o runtime em Catalog** — rejeitada pelo requisito explícito de fonte única no System.
2. **Servir diretamente `system/`** — rejeitada porque `dev/system/.htaccess` nega acesso web.
3. **Duplicar os módulos em System e Catalog** — rejeitada por risco de divergência e manutenção em duplicidade.
4. **Rota Catalog allowlisted e cacheável** — escolhida; respeita o limite HTTP do núcleo e preserva URLs relativas dos módulos ES.

## Consequências

- ✅ Um único vendor/runtime versionado e uma única fonte de configuração/manifesto.
- ✅ `system/` permanece inacessível diretamente; apenas os nomes explicitamente autorizados são publicados.
- ✅ Admin, home, 404, Blog e CMS reutilizam o mesmo runtime.
- ⚠️ Primeira requisição dos módulos passa pelo front controller PHP; ETag/cache reduzem transferências subsequentes.
- ⚠️ Alterações afetam System, Catalog, Admin e documentação; devem ser serializadas na ordem System → Catalog → Admin.
- ⚠️ PHP CLI indisponível nesta sessão; lint PHP é uma pendência antes de considerar a implementação concluída.

## Documentos atualizados

- `docs/Implementacao-ThreeJS.md`
- `docs/Estrutura-de-Pastas.md`
- `docs/THREEJS_NATIVE_SYSTEM.md`
- `docs/CHANGELOG.md`
