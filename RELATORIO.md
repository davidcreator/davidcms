# Hotfix — `ScenePresetRegistry` não encontrado (framework.php:48)

Corrige o `Fatal error: Uncaught Error: Class
"Reamur\System\Library\Three\ScenePresetRegistry" not found` relatado após
aplicar a frente **E**. Aplicável sobre o `main` 4835384bf (E aplicada) e também
compatível com a frente **F**.

## Diagnóstico (causa raiz)

`dev/system/engine/autoloader.php` converte cada segmento do namespace em
`snake_case`. Para `Reamur\System\Library\Three\ScenePresetRegistry` ele
procura **obrigatoriamente**:

```
{DIR_SYSTEM}library/three/scene_preset_registry.php
```

A frente **D** entregou a classe em `library/three/scene_preset.php`. O
`framework.php` (linha 48 do HEAD E) faz
`new \Reamur\System\Library\Three\ScenePresetRegistry()` e o autoloader
nunca encontra o arquivo → fatal em **qualquer** requisição que carregue o
framework. A frente **E não tocou em `dev/system/library/three/`**; ela apenas
reemitiu o `framework.php`, e foi aí que o erro ficou visível.

Todas as outras classes das frentes A–F já obedecem ao contrato:
`TokenRegistry`→`token_registry.php`, `ThemeSnapshot`→`theme_snapshot.php`,
`ThemeCompareView`→`theme_compare_view.php`, `ThemePackage`→`theme_package.php`,
`SectionRegistry`→`section_registry.php`. **Apenas a D divergia.**

**Falha de cobertura assumida:** os testes A–F carregavam as bibliotecas com
`require` explícito no bootstrap, ignorando o autoloader. Por isso não viram o
erro. O novo teste usa o `Autoloader` real, sem nenhum `require` de biblioteca.

## Correção (5 arquivos)

- **`dev/system/library/three/scene_preset_registry.php`** (novo): a classe
  canônica, **byte-idêntica** à do HEAD. Nome correto → o autoloader resolve.
- **`dev/system/library/three/scene_preset.php`**: passa a ser apenas um alias
  de compatibilidade com `require_once __DIR__ . '/scene_preset_registry.php'`.
  Nenhuma regra, preset ou allowlist é duplicada; quem ainda fizer `require`
  direto do caminho antigo continua funcionando, sem declaração dupla.
- **`docs/GUIA-EDITOR-CENA-PRESETS.md`**: caminho atualizado.

**Não há mudança** em `framework.php`, `autoloader.php`, `schema.sql`,
manifestações `CHECKSUMS*.txt`, workflow, checkers, contratos congelados,
`ConditionEngine`, `TokenRegistry`, `SceneConfig`, runtime Three.js, BDREditor
ou `release_approved`. Sem migration, sem Composer, sem npm, sem escrita remota.

## Como aplicar (WAMP / Windows)

1. Copie **os dois** arquivos `.php` deste ZIP para
   `E:\wamp64\www\davidcms\dev\system\library\three\`
   (substitua `scene_preset.php` e **adicione** `scene_preset_registry.php`).
2. Opcional: substitua `docs/GUIA-EDITOR-CENA-PRESETS.md`.
3. Reinicie o Apache no WAMP (ou limpe o OPcache) para descartar bytecode
   antigo e recarregue a página.

Não é preciso rodar migration, `composer`, `npm`, reemitir manifestos ou
reaplicar D/E/F. Se o erro persistir, confirme que
`scene_preset_registry.php` está exatamente nessa pasta e que o OPcache foi
limpo.

## Validação executada

| Verificação | Resultado |
|---|---|
| `php -l` PHP 8.5 nos 2 arquivos PHP | 2 OK / 0 FAIL |
| **Cenário A** — HEAD E sem hotfix, via autoloader real | 10 PASS / 0 FAIL (falha reproduzida: classe ausente) |
| **Cenário B** — HEAD E + hotfix | 36 PASS / 0 FAIL |
| **Cenário C** — HEAD E + hotfix + frente F | 55 PASS / 0 FAIL |
| `verify-checksums` (antes e depois) | 14 manifestos / 142 arquivos / 0 divergentes |
| `repo-health` (antes e depois) | 6 PASS / 0 FAIL / 0 SKIP |
| `verify-ci-workflow` | 31 PASS / 0 FAIL |
| `verify-contract-freeze` | exit 0 |

**Total: 101 asserções, 0 falhas.** O teste B verifica que a classe existe
**pelo autoloader**, instancia as 6 classes do `framework.php`, checa os 6
presets, `svg()`, `dataUri()`, `apply('nebula')`, `ThemePackage`,
`SectionRegistry` e que o shim não declara a classe duas vezes. 9 arquivos
congelados conferidos por hash antes/depois; 14 manifestos idênticos.

## Limitações

PHP 8.5 via WASM com o `Autoloader` real do repositório, sem servidor WAMP,
MySQL, sessão autenticada, browser, WebGL ou deploy. O erro de runtime foi
reproduzido e a resolução da classe verificada; a aplicação no servidor local
deve ser confirmada pelo usuário. F9-27 segue `BLOCKED_NOT_RUN`; BDREditor
pausado; P2 do F9-18 não reaberto. **CI verde ≠ release aprovada.**
