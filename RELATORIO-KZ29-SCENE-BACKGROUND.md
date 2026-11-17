# RELATORIO KZ-29 — Cena david-universe com fundo opaco (fim do vazamento do fallback)

**Data:** 2026-11-17 · **Base:** HEAD `6396355` + KZ-28 · **Pacote:** `ReamurCMS-Frente-KZ29-Scene-Background.zip`

## Sintoma (screenshot)
Depois do reparo do SVG, a "poluição" (grade de pontos + círculo escuro) continuava — isso é o
`.theme-scene__fallback` (CSS) vazando através do canvas WebGL.

## Causa raiz
O `scene-runner.js` cria o renderer com `alpha:true` (linha 126) e só seta `scene.background`
se vier `config.background` (linha 140). A cena `david-universe.js` definia apenas
`scene.fog = FogExp2(BG, ...)` — nunca `scene.background`. Canvas transparente → fallback CSS
visível por cima → aparência de "ruído".

## Correção
`dev/system/library/three/runtime/three/scenes/david-universe.js` agora define
`scene.background = new THREE.Color(BG)` (#121516) — mesmo fundo escuro da referência
https://davidalmeida.xyz/threejs/?lang=pt. O arquivo da cena não está no CHECKSUMS.txt
(verificado), então só o CHANGELOG entrou no manifesto recalculado.

## Aplicar
```bash
git commit -m "fix(three): cena david-universe com scene.background opaco (KZ-29)"
```
Só código do repo — **não precisa mexer no banco**. Recarregue com Ctrl+F5.
