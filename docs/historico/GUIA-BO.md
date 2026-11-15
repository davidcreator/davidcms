# Guia de aplicação — Frente BO-r3

Ordem recomendada. **Você aplica**; o agente não escreve no GitHub.

## 0. Caminho mais curto (se o 3D já falhou uma vez)

Escolha **uma** das três formas; fazem a mesma coisa (copiam os **10** arquivos
e verificam que `scenes/david-universe.js` ficou legível):

**A · Windows, sem Python — duplo clique.** Extraia o ZIP e dê duplo clique em
`INSTALAR.bat`. Ele pergunta a pasta raiz do davidcms (a que contém `dev`) e
chama o PowerShell com `Bypass`. Se apareceu
`Python was not found; ... Microsoft Store`, **é esta a opção**.

**B · PowerShell / terminal.**

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File instalar-bo.ps1 -Repo "C:\xampp\htdocs\davidcms" -Apply
```
```bash
# macOS / Linux, se tiver Python:
python3 instalar-bo.py --repo /caminho/para/davidcms --apply
```

**C · Sem script nenhum.** Siga `COPIAR-MANUALMENTE.md` (10 pares
origem → destino, para FTP/cPanel/Gerenciador de Arquivos).

Depois de qualquer uma: limpe o cache de opcode/Twig, **ative** o tema
`david_universe_3d_bo` no Admin e abra `verificar-bo.html` (copie para a raiz
do site) — ele mostra qual dos 5 elos quebrou, sem precisar abrir o Console.

> **Se os objetos 3D não aparecerem**, pare aqui e abra `DIAGNOSTICO-3D.md`.
> Na maioria dos casos é o `PATCH-BO.diff` aplicado pela metade: `main.js` foi
> atualizado, mas `dev/system/library/three/runtime/three/scenes/david-universe.js`
> não existe no servidor ou não é publicado pelo `asset_manifest.php`. Um import
> com 404 derruba o módulo ES inteiro. Use `diagnostico/console.js` para confirmar
> em 10 segundos. Os erros `<path> attribute d` do console **não** são a causa do
> 3D — são selos SVG decorativos, já corrigidos nesta revisão r2.

## 1. Aplicar o patch do runtime

No checkout do `davidcms` em `ad7643de6ca2` (ou compatível):

```bash
git apply --check patch/PATCH-BO.diff     # deve sair sem erro
git apply        patch/PATCH-BO.diff
git status --short                        # 9 modificados + 1 novo
```

O patch **não** mexe em SQL, migration, CI, deploy, `CHECKSUMS.txt`, `theme-scene.twig`, `scene-runner.js`, manifestos nem contratos congelados.

Alternativa sem `git`: copie os arquivos de `patch/arquivos/` para os mesmos caminhos no repositório.

> `dev/system/library/three/runtime/three.module.js` e `three.core.js` **já existem** no servidor (r186). Os que vão em `previa/` são apenas para teste local — não copie por cima.

## 2. Conferir (opcional, estático)

```bash
bash tools/verify-checksums.sh             # 14 manifestos, sem divergência
node tools/verify-bo.cjs .                 # a partir da raiz deste pacote
```

## 3. Importar o tema

1. Admin → **Temas** → **Importar tema**.
2. Envie `tema/david_universe_3d_bo.reamurtheme.json` (90.215 bytes).
3. Confira `validateConfig=[]` e `decode ok=true`.
4. **Ative** o tema. O slug é novo (`david_universe_3d_bo`), então não sobrescreve o BN. Se quiser substituir, desative/exclua o `david_universe_3d` antes.
5. No editor de temas, a cena **`David Universe (nave + orbes)`** aparece no dropdown e o preset **`universe`** mostra a miniatura da nave.

O HUD do tema expõe `data-du-orbes`, `data-du-fase`, `data-du-distancia`, `data-du-progresso`, `data-du-toast` e `data-du-hint-distancia`. A cena procura esses ganchos e cai para seletores de classe se eles não existirem.

## 4. Testar a prévia sem o CMS (opcional)

```bash
cd previa
python3 -m http.server 8123
# abrir http://localhost:8123/  (precisa de servidor HTTP: sao modulos ES)
```

- Mova o mouse: a nave segue o ponteiro.
- Role: a câmera voa de `z=8` a `z=−312`, o HUD mostra a distância até 384.400 km e a barra enche.
- Passe pelos orbes: `ORBES nn/14` + toast.
- `prefers-reduced-motion: reduce`: o WebGL não monta e o fallback declarativo permanece.

A prévia usa `data-debug="1"` para expor `window.__duScene` (somente leitura + ganchos de diagnóstico). O `theme-scene.twig` de produção **não emite** esse atributo, então o gancho fica desligado no site.

## 5. Verificar no navegador do site

Depois de aplicar e ativar:

- Deve existir **1** `<div class="theme-scene theme-scene--hero" data-scene="david-universe">` e **1** `<canvas>` dentro dele.
- O canvas deve estar `position:fixed` e cobrir a viewport.
- `ORBES`, `FASE`, `DISTÂNCIA` e a barra devem mudar ao rolar.
- Sem erros no console.

Se o canvas não aparecer, verifique `has_3d` no `common/home.php` (o patch adiciona `'david-universe' => 'three_hero_enabled'`) e se o Admin não desligou `three_hero_enabled`.

## 6. Rollback

```bash
git apply -R patch/PATCH-BO.diff
```

E desative o tema `david_universe_3d_bo` no Admin. O BN anterior continua disponível e intacto.

## 7. Limites

Não execute migration, não rode deploy, não dispare CI como parte deste pacote. Não declare release. `release_approved:false`, F9-27 `BLOCKED_NOT_RUN`, F9-18 `release_ready:false` e os 10 `PENDING_DECISION` permanecem. D01–D10 não foram decididos.

Aceite visual do BO ainda **não** foi dado.
