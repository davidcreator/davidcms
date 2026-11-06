# Plano — Frente AX (Ferramentas internas + rename)

**Data:** 2026-11-06 · **HEAD:** `481b690209ec1a1caf4d9f334cef6012d4597653`
**CI:** 7/7 success (Frente AV aplicada) · **Status:** plano, **nada aplicado**.

## 1. Decisões do usuário

| Item | Decisão |
|---|---|
| Nomes finais | **`BDREditor-Content`** e **`KAZEditor-Theme`** (grafia corrigida de "Thene") |
| Alcance do rename | **R2 — global** |
| Versão / atualizações | **V2 — JSON de metadados versionado**, sem migration |
| Local | **P2 — seção "Ferramentas internas" somente no Admin** |

O escopo "editar somente o BDREditor" (AN/AP) foi **encerrado pelo veredito
APROVADO**; esta é uma frente nova e explicitamente autorizada.

## 2. Parte A — Seção "Ferramentas internas" (V2 + P2)

### Arquivos propostos

| Arquivo | Ação | Observação |
|---|---|---|
| `dev/admin/controller/tool/tool.php` | alterar | injetar `$data['internal_tools']` lido do JSON, com `try/catch` e fail-closed |
| `dev/admin/view/template/tool/tool_list.twig` | alterar | nova `<section>` após a lista de ferramentas conectadas |
| `dev/admin/language/{en-us,pt-br,es-eu}/tool/tool.php` | alterar | 6–8 rótulos novos por idioma |
| `tools/internal-tools.json` | **novo** | fonte única de versão, data e notas |
| `dev/admin/view/css/admin.css` | alterar | só adendo `.internal-tools-*`, prefixo anterior preservado |

**Nenhum** arquivo de `dev/install/sql/`, nenhuma migration, nenhuma tabela,
nenhum `|raw` novo, nenhuma fonte/CDN, nenhuma chamada de rede, nenhuma rota
nova, nenhuma alteração de ACL/CSRF, nenhuma mudança no catálogo público.

### Estrutura do JSON (`tools/internal-tools.json`)

```json
{
  "schema": 1,
  "tools": [
    {
      "id": "bdreditor-content",
      "name": "BDREditor-Content",
      "legacy_name": "BDREditor",
      "vendor": "ReamurCMS",
      "version": "PENDENTE_USUARIO",
      "updated_at": null,
      "notes": [],
      "href": null
    },
    {
      "id": "kazeditor-theme",
      "name": "KAZEditor-Theme",
      "legacy_name": "KAZTheme-Editor",
      "vendor": "ReamurCMS",
      "version": "PENDENTE_USUARIO",
      "updated_at": null,
      "notes": [],
      "href": null
    }
  ]
}
```

- `legacy_name` documenta a equivalência nome novo ↔ identificador antigo
  enquanto o R2 não é concluído.
- `version` e `updated_at` **não foram inventados**: ficam com `PENDENTE_USUARIO`
  / `null`, e a UI exibe "Versão não informada" de forma honesta (fail-closed).
- Controller valida `schema`, `id` único, `name` não vazio e tipos; JSON
  inválido/ausente → seção omitida com log, **sem quebrar a página**.
- `notes[]` = até N linhas de atualizações, renderizadas com escape automático
  do Twig (sem `|raw`).

### Comportamento visual

Seção separada, com o **tema ReamurCMS claro e acento azul** intactos (o
veredito APROVADO da AP continua vigente). Cards/badges reaproveitam classes
existentes; nada de identidade KAZ fora do BDREditor.

## 3. Parte B — Rename global (R2)

Inventário completo em `Inventario-Rename-Global.md`:
**179 arquivos / 762 ocorrências / 13 de 14 manifestos a regenerar / 0 de 3
contratos congelados afetados**.

### Fases propostas

| Fase | Conteúdo | Risco |
|---|---|---|
| **B1** | Rótulos de UI + traduções + textos de `docs/` | Baixo, mas exige regenerar 13 manifestos e conferir `docs-links` |
| **B2** | Renomear arquivos de `docs/` que têm o termo no path e atualizar todos os links (`docs/README.md`, índices, ADRs) | Médio — foi aqui que o CI quebrou na AV |
| **B3** | Identificadores de código: `blog-block-editor-kaz.js`, `kaz-theme-editor.js`, `kaz_theme_editor.twig`, classes, variáveis, rotas, probes e smoke tests | **Alto** — `<script src>`, asserções de string literal e 61 checkers e2e |
| **B4** | Regenerar os 14 `CHECKSUMS*.txt`, rodar os 6 guardas + `docs-links` localmente, conferir CI remoto | Obrigatório em todas as fases |

### Ponto que ainda depende de você

"R2 global" pode significar **B1+B2** (texto, rótulos, documentação e nomes de
arquivos de doc) ou **B1+B2+B3** (também identificadores internos de código).
A diferença é grande: B3 renomearia arquivos JS/PHP carregados por controllers
e exigiria atualizar `tools/kaz-bdre-visual-probe.php` (18 ocorrências),
`tools/kaz-bdre-total-probe.php` (22), os `.cjs` de verificação e os checkers
e2e — além de reauditoria completa do CI. **Não vou assumir B3 por inferência.**

## 4. O que falta para eu gerar o pacote de código

1. **Versões iniciais** (e datas/notas, se quiser) de `BDREditor-Content` e
   `KAZEditor-Theme`. Sem elas, o JSON fica com `PENDENTE_USUARIO`.
2. **Confirmação do alcance do R2**: B1+B2 ou B1+B2+B3.
3. Opcional: captura da tela onde as externas mostram versão/atualizações, se
   quiser que eu reproduza aquele padrão em vez do proposto aqui.

## 5. Limites

Nenhuma escrita no GitHub, nenhum rename aplicado, nenhuma migration, nenhuma
alteração de contrato congelado, manifesto, hash, `schema.sql`, DOM, JS de
~600 KB, renderer ou catálogo. Nenhuma conversão de `PENDING`/`NOT_RUN`/
`BLOCKED_NOT_RUN` em `PASS`, nenhuma decisão das 10 `PENDING_DECISION`, nenhuma
declaração de release.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · CI 7/7 (lido, não disparado) · veredito **APROVADO**
da AP mantido. AR e AS seguem prontos e sem conflito.
