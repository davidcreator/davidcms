# Three.js nativo — integração System, Admin e Catalog

**Base auditada:** `davidcreator/davidcms`, `main` em `0d201f55b73d16bbe8383c8e01786c58cf2e3903` (somente leitura).

## Auditoria

O projeto já contém o runtime Three.js self-hosted no Catalog: hero de partículas na home, cena da página 404, import map condicional `has_3d`, `SceneRunner` com lazy-init/visibilidade/dispose, `prefers-reduced-motion` e fallback. O Admin também já tem bloco de thumb 3D no BDREditor de Blog/CMS, com editor de formas/luzes e script opcional em iframe isolado.

Antes desta ampliação faltavam gestão do Admin para as cenas nativas do site, centralização dos limites no `system`, uso de settings persistidos em vez de valores hard-coded nas rotas e unificação do módulo Three carregado pela import map. Os controllers admin também solicitavam CSS/JS de thumb na raiz `/view/`; os arquivos pertencem a `/catalog/view/`, causando os 404 reportados.

## Arquitetura implementada

- `dev/system/library/three/scene_config.php`: defaults, normalização e validação estrita de settings. Só aceita valores declarativos; não executa JavaScript.
- `dev/admin/controller/setting/setting.php` e `dev/admin/view/template/setting/setting.twig`: controles das cenas hero e 404 dentro da tela operacional existente, respeitando ACL `modify setting/setting`, CSRF e o salvamento do model de settings existente.
- Persistência usa as chaves `three_hero_enabled`, `three_hero_particle_count`, `three_hero_color`, `three_hero_speed` e `three_not_found_enabled` em `rms_setting` / `code=config`. Não adiciona tabelas nem migration.
- Valores permitidos: partículas 50–800, cor `#RRGGBB`, velocidade 0,01–0,20 em passos de 0,01; toggles estritos 0/1. Valores legados/corrompidos caem individualmente nos defaults.
- Home e 404 recebem a configuração normalizada via biblioteca `System`; desligar o WebGL mantém conteúdo e fallback HTML/CSS.
- O import map do Catalog usa o Three.js self-hosted existente em `catalog/view/js/three.module.js`, alinhado ao runtime da thumb, sem CDN.
- Admin Blog/CMS carrega o runtime e CSS da base Catalog correta, removendo os 404.
- Textos administrativos incluídos em pt-br, en-us e es-eu.

## Segurança / limites

Não há JavaScript arbitrário para as cenas nativas do site. As formas e movimentos continuam em módulos allowlisted no Catalog; o script opcional do bloco thumb permanece sob o mecanismo sandbox existente, sem acesso ao DOM/cookies da página e sem rede.

## Verificação

Execute `node tools/e2e/check_threejs_native_admin.cjs` e os checkers de BDREditor/Three.js em `tools/e2e/`. Eles são contratos estáticos; não substituem a validação visual da home, da 404 e do Admin num runtime CMS autenticado. Nesta sessão o PHP CLI e uma instância CMS não estão disponíveis, portanto o lint PHP e o E2E real precisam ser executados no ambiente do projeto.
