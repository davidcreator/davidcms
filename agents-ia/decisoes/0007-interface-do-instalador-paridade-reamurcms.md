# ADR 0007 — Interface do instalador em paridade com o ReamurCMS público

**Status:** Aceita — 2026-10-17 · **Escopo:** `dev/install` (visual + fluxo de
passos). **Contexto decisivo:** o mantenedor definiu que davidalmeida.xyz usará
o *stack original* ReamurCMS como referência de experiência, mas **apenas a
interface do instalador** agora; conteúdo exclusivo do produto (módulos
blog/chat/mooc/landpage, loja de extensões, promotion, app de upgrade) **fica
fora** deste projeto neste momento, entrando só sob demanda.

## Decisão

1. **Paridade visual via port de assets GPL do próprio projeto**: Bootstrap 5,
   `reamur.css`, FontAwesome, jQuery, `bootstrap.bundle`, `common.js` e o logo
   vêm byte a byte do repositório público (mesma licença GPL-3.0 do projeto).
2. **Fluxo de 4 passos** (era 5): Licença → Pré-requisitos → **Configuração
   unificada (banco + administração + URL do site)** → Concluído. A mesclagem
   do antigo s4 (admin) dentro do s3 espelha o gesto do instalador público; a
   rota `install/step_5` vira redirect 301 para `step_4` por uma versão.
3. **Riqueza de UX do público portada**: medidor de força de senha, mostrar/
   ocultar senha, gerador client-side (crypto do navegador), confirmação ao
   vivo, botão **Testar conexão** (endpoint AJAX `install/step_3.test` —
   leitura pura, nunca grava) e overlay cosmético de progresso no submit.
4. **Deltas honestos mantidos** (não copiamos divergências do público):
   - **Site URL explícita** no passo 3 — o público deriva `HTTP_CATALOG` de
     `SCRIPT_NAME`, a armadilha de SAPI corrigida aqui na F2-5; mantemos o
     campo digitado e validado;
   - **Primeiro nome + sobrenome** do admin permanecem exigidos (nosso schema
     tem as colunas NOT NULL e o CRUD do painel — F2-6 — espelha essas regras);
   - **Seed F2-6** do grupo Administrator (grants mínimos do painel) segue no
     `createAdministrator` — o banco continua nascendo utilizável pelo painel;
   - **Schema próprio de 28 tabelas** (`dev/install/sql/schema.sql`) intacto.
   - Sem o `column_left` legado do público (os passos do instalador dele
     também não o renderizam; é resíduo) e sem `bootstrap-icons.css` (as
     fontes de ícone faltam até no repositório público — referência morta).
   - Bandeiras `en-us.png`/`es-eu.png` geradas localmente (o público só tem
     `en-gb.png`/`pt-br.png`); rótulos dos idiomas em nomes nativos fixos.
5. **Strings JS através de language keys** com escape `|e('js')` — o público
   tem textos PT-BR cravados no JavaScript; aqui nenhum texto vive fora dos
   arquivos de idioma (ADR 0001), nos 3 idiomas com paridade validada.

## Consequências

- ✅ O gesto de instalação é o mesmo do ReamurCMS público: mesmo shell
  (navbar/logo/footer), mesmos cartões, mesma ordem de telas;
- ✅ Wizard E2E passa a ser **mais curto e mais testável**: um POST no s3
  instala tudo sem depender de sessão efêmera entre passos;
- ⚠️ Dívidas assumidas: fluxo s3/s4 antigo não funciona mais com POSTs
  separados (rota s5 redireciona); `dev/install/view/css/wizard.css` e
  `view/template/install/step_5.twig` foram removidos do espelho (exigem
  `git rm` no pacote);
- ⚠️ O endpoint `install/step_3.test` existe apenas no app de install e morre
  junto com a pasta ao fim da instalação — sem superfície pós-deploy.

## Alternativas consideradas

1. **Convergência downstream total** (adotar `system/install/admin/catalog`
   do público por inteiro) — **rejeitada pelo mantenedor**: o projeto atual
   continua sendo a base; partes do ReamurCMS entram sob demanda;
2. **Manter 5 passos com novo visual** — rejeitada: deixaria a interface
   parcialmente diferente do público justamente no ponto mais visível (a
   tela de configuração dividida);
3. **Skins CSS sobre o markup antigo** — rejeitada: o markup antigo era
   standalone por passo (sem chrome comum); a paridade honesta exigia o shell.

## Adendo — Site URL sem atrito em localhost (hotfix)

- Site URL vem **pré-preenchida por tentativa** do pedido do wizard (esquema +
  host + caminho-pai de `…/install/`); é guess de formulário — a autoridade
  final continua sendo o que o usuário digita (regra F2-5 intocada);
- Digitação **sem esquema** (`localhost:8000`) é normalizada para
  `http://localhost:8000` antes da validação e volta normalizada ao POST/à
  repopulação/ao config; valor realmente inválido rejeita com erro por campo
  no idioma ativo;
- Dica contextual `text_http_server_hint` sob o campo nos 3 idiomas.

## Adendo 2 — Layout de pastas irmãs (XAMPP/subpasta) como alvo padrão

- **Modelo de deploy assumido**: `catalog/` e `admin/` são irmãs sob a pasta do
  projeto (ex. XAMPP: `http://localhost/davidcms/dev/`). A LOJA responde em
  `{projeto}/catalog/` e o painel em `{projeto}/admin/`.
- **Site URL = URL pública da LOJA.** A tentativa do passo 3 sobe um nível a
  partir de `…/install/` e aponta `…/catalog/`; vazio no POST assume a
  tentativa. O PAINEL é derivado por regra única: site terminando em
  `catalog/` → troca para `admin/`; demais layouts (docroot dedicado,
  convenção admin-sob-a-loja) → `{site}admin/`. A mesma regra alimenta os
  config.php E o link do passo 4 (sessão `install_admin`).
- **`catalog/index.php`**: redirect de bootstrap corrigido para
  `../install/index.php` (irmão) — antes apontava `install/index.php` e quebrava
  sob subpasta/docroot dedicado.
- **.htaccess padrão front-controller** entregues em `admin/`, `catalog/`
  (URLs limpas somem sem mod_rewrite — No XAMPP padrão, mod_rewrite +
  AllowOverride All estão ativos), `system/` (Require all denied) e raiz `dev/`
  (`DirectoryIndex index.php` + `index.php` atalho → `catalog/index.php`).
  PHP CLI server ignora .htaccess e resolve por conta própria — a resolução de
  rota pelo REQUEST_URI (resolve() do router) cobre os dois mundos. Em Apache
  com AllowOverride None as URLs limpas degradam: usar os links `index.php?route=`.
