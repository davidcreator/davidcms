# BO-r4 — Instalação manual (FTP / cPanel / Gerenciador de Arquivos)

Use isto se você **não tem terminal, não tem Python e não quer rodar script nenhum**.
É só copiar 10 arquivos da pasta extraída do ZIP para a pasta do site, **mantendo os mesmos caminhos**.

**Extraia o ZIP → abra `patch/arquivos/` → copie cada arquivo para o destino abaixo.**
No Windows, troque `/` por `\`. Se a pasta de destino não existir, crie-a.

| # | Origem (no ZIP, `patch/arquivos/`) | Destino (na raiz do davidcms) | Bytes |
|---|---|---|---|
| 1 | `dev/system/library/three/runtime/three/main.js` | `dev/system/library/three/runtime/three/main.js` | 689 |
| 2 | `dev/system/library/three/asset_manifest.php` | `dev/system/library/three/asset_manifest.php` | 3.332 |
| 3 | `dev/system/library/theme/theme_package.php` | `dev/system/library/theme/theme_package.php` | 17.331 |
| 4 | `dev/system/library/theme/condition_engine.php` | `dev/system/library/theme/condition_engine.php` | 24.070 |
| 5 | `dev/system/library/theme/theme_snapshot.php` | `dev/system/library/theme/theme_snapshot.php` | 11.838 |
| 6 | `dev/system/library/three/scene_preset_registry.php` | `dev/system/library/three/scene_preset_registry.php` | 11.123 |
| 7 | `dev/catalog/controller/startup/theme.php` | `dev/catalog/controller/startup/theme.php` | 13.311 |
| 8 | `dev/catalog/controller/common/home.php` | `dev/catalog/controller/common/home.php` | 19.092 |
| 9 | `dev/admin/controller/design/theme.php` | `dev/admin/controller/design/theme.php` | 79.142 |
| 10 | `dev/system/library/three/runtime/three/scenes/david-universe.js` | `dev/system/library/three/runtime/three/scenes/david-universe.js` **(NOVO — este é o que costuma faltar)** | 33.395 |

> **Atenção ao item 10.** Ele é `new file mode` no `PATCH-BO.diff` — ou seja, **não existe no repositório original**.
> Se você aplicou só os 9 arquivos modificados, `main.js` fica importando um caminho que devolve **404**,
> e um único import com 404 **aborta o módulo ES inteiro**. Nenhuma cena aparece, nem as 4 antigas.
> É a causa mais provável do "3D não aparece".

## Depois de copiar

1. Limpe o cache de opcode/Twig do CMS e recarregue com `Ctrl+F5`.
2. No Admin, importe `tema/david_universe_3d_bo.reamurtheme.json` e **ATIVE** o tema (não basta salvar como rascunho).
3. Copie `verificar-bo.html` para a **raiz do site** e abra `https://seusite/verificar-bo.html`.
   Ele mostra, sem Console, se `[data-scene]`, o importmap e as 6 URLs do runtime estão verdes.

## Conferência rápida pelo navegador

Abra diretamente esta URL (troque o domínio):

```
https://seusite/three-runtime/three/scenes/david-universe.js
```

Se aparecer **404** → o item 10 não foi copiado (ou `asset_manifest.php` não foi atualizado).
Se aparecer o código JavaScript → o arquivo está lá; o problema está em outro elo e o `verificar-bo.html` aponta qual.

---
Nenhum destes passos altera SQL, contratos congelados, `CHECKSUMS.txt`, catálogo público ou escreve no GitHub.
Você aplica; o agente não executa migration, deploy, CI nem runtime autenticado.
