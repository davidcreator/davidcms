# Relatório Final — Frente BE (CRUD de temas + Importar tema)

**Data:** 2026-11-06 · **HEAD auditado:** `7646c98405207cedf36a1fb86685046bd5e6f33c`
**CI remoto:** ✅ 7/7 success · **Gatilho:** `image-11.png` + pedido do usuário.

## 1. O que a imagem mostrou

A lista "Temas do Catálog" tinha apenas **"Novo tema"** e **"Editar"**. Faltavam
**ativar/desativar**, **excluir** e a opção **"Importar tema"** ao lado de
"Novo tema".

## 2. Levantamento: o que JÁ existia

| Item | Situação |
|---|---|
| `theme.activate` + `Model::activate()` | ✅ já existia — publica o rascunho e mantém **um único tema ativo** |
| Botão "Ativar" no Twig | ✅ já existia, mas **condicionado a `not theme.status`** — invisível na imagem porque `testetema` é o ativo |
| `theme.import` com `id=0` | ✅ já **cria um tema novo** (`save(0,…)`), mas só era alcançável de dentro do editor de um tema existente |
| `theme-package.js` (BC) | ✅ já lia `.json` com `FileReader`, prévia e validação no cliente |
| `deactivate` / `delete` | ❌ **não existiam** nem no controller nem no model |

**Fallback verificado:** `Catalog\Model\Common\Theme::getEffectiveTheme()` devolve
`['published'=>[],'draft'=>[],'slug'=>'']` quando nenhum tema está ativo — o
Catálogo passa a usar os padrões. **Desativar não quebra o catálogo.**

## 3. O que foi implementado

**6 arquivos** (nenhum em `CHECKSUMS*.txt`).

### Lista — `dev/admin/view/template/design/theme_list.twig`

- **"Importar tema"** ao lado de "Novo tema", ancorado no card de import;
- **card de import** na própria lista: textarea + escolha/arraste de `.json`,
  prévia (nome, slug, formato, pai, arquivo, tamanho), `role="status"
  aria-live="polite"`, CSRF, e `<details>` para recolher. Reaproveita o
  `theme-package.js` da BC **sem alterá-lo**;
- coluna **"Ações"** com, por linha:
  - **Editar** (inalterado);
  - tema **ativo** → **Desativar** + **Excluir desabilitado** com dica
    "Desative o tema antes de excluí-lo.";
  - tema **inativo** → **Ativar** + **Excluir** com **confirmação em duas
    etapas** via `<details>` (sem JS, sem `confirm()`);
- `notice`/`error` no topo para feedback de cada ação.

### Controller — `dev/admin/controller/design/theme.php`

- `index()`: injeta 43 chaves de idioma, `import_url`, `package_format`/
  `package_version`, URLs `deactivate`/`delete` por linha, feedback por query
  string com **allowlist** de códigos, e carrega `theme-package.js`;
- **`deactivate()`** e **`delete()`**: **POST**, ACL `modify`, CSRF, redirect
  para a lista com `ok=…`/`error=delete`;
- `import()` com **`id=0` passa a voltar para a lista** (`ok=import` /
  `error=import&code=…`). Para **`id>0` o comportamento é idêntico** ao
  anterior (`import_ok=1` / `import_error=…` no editor);
- `activate()` ganhou apenas o feedback `ok=activate`; a lógica de publicação
  não mudou.

### Model — `dev/admin/model/design/theme.php`

- **`deactivate(int $id, int $userId)`**: zera `status` e registra revisão.
  **Preserva `config`/`published_config`** — desativar não apaga conteúdo;
- **`delete(int $id)`**: remove o tema **e suas revisões**, mas **recusa
  excluir tema ativo** e tema inexistente (fail-closed). Sem `ALTER`/`CREATE`/
  `DROP` — **nenhuma migration, nenhuma coluna nova**.

### Idiomas — 3 arquivos

14 chaves novas por idioma (pt-br, en-us, es-eu). `button_activate` passou de
"Publicar"/"Publish"/"Publicar" para **"Ativar"/"Activate"/"Activar"**,
atendendo ao vocabulário do pedido; a dica `text_activate_help` deixa claro
que ativar **publica o rascunho**.

## 4. Decisões de segurança (explícitas no código)

| Decisão | Motivo |
|---|---|
| **Não excluir tema ativo** | impede deixar o Catálogo sem config publicada por acidente; exige desativar antes |
| Exclusão em **2 etapas** | evita clique único destrutivo, sem JS inline |
| Exclusão remove revisões | não deixa registros órfãos; a ação é **definitiva** e está escrita no aviso |
| POST + ACL `modify` + CSRF em **todas** as mutações | mesmo padrão de `activate()` |
| `import()` sempre como **rascunho** | nenhum pacote publica automaticamente |
| Sem `\|raw` novo, sem `<script>`, sem `onclick`, sem JS inline | só os 2 `\|raw` do chrome (`header`/`footer`) |
| Sem migration / `ALTER` / `schema.sql` | usa UPDATE/DELETE nas tabelas existentes |
| Backend de import intacto | `ThemePackage::decode()` com `hash_equals`, allowlists e 512 KiB continua a autoridade |

## 5. Validação

| Verificação | Resultado |
|---|---|
| `php -l` (**PHP 8.5.12** WASM): controller, model, 3 idiomas | ✅ **5/5** |
| **Harness PHP do model com DB falso** | ✅ **20 OK / 0 FALHAS** — desativar ativo/inativo/inexistente, excluir ativo **recusado**, excluir inativo (2 DELETE na ordem), payload preservado, sem ALTER, métodos originais intactos |
| Twig `if/for` balanceados · 2 `\|raw` · 4 forms POST · 0 `<script>`/JS inline | ✅ |
| `tools/verify-theme-crud.cjs` | ✅ 0 FALHAS |
| `verify-checksums.sh` / `repo-health.sh` na réplica fiel | ✅ 142 arquivos / 0 divergentes · **6 PASS / 0 FAIL / COERENTE** |
| `verify-contract-freeze.py` | ✅ COERENTE |
| CI remoto do HEAD | ✅ 7/7 success (lido, não disparado) |

## 6. Limites

Nenhuma escrita do agente no GitHub, nenhuma migration, contrato congelado,
hash, manifesto, `schema.sql`, workflow, `TEMPLATE_KEYS` ou `ThemePackage`
alterado. Nenhuma conversão de `PENDING`/`NOT_RUN`/`BLOCKED_NOT_RUN` em
`PASS`, nenhuma decisão das **10 `PENDING_DECISION`**, nenhuma mudança em
`PROPOSAL_PENDING` / `DESIGN_GATE_PENDING` (F9-25), nenhuma declaração de
release.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false`. CI verde, PHP WASM, DB falso e guardas estáticos **não
significam release aprovada** e não substituem o aceite no Admin real.

**Ainda pendentes:** JSON do **health** do Admin real (guia aplicado, não
colado), aceite visual da seção Ferramentas internas / nomes novos, e aceite
desta tela de temas.

## 7. Próximos passos (dependem de você)

1. **Aceite visual** desta lista no Admin real (`image-11.png` atualizada).
2. **Recorte B** — ampliar `TEMPLATE_KEYS` (diga quais templates).
3. **Recorte C** — ZIP com `.twig`/`.js` reais: exige decisão explícita de
   segurança, sandbox Twig, ACL dedicada e revisão de migration (hoje vetado).
4. Passos do roteiro: health (2), D01–D10 com **AR** (3), F918 com **AS** (4).
