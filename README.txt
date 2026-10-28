# Inventário local de scripts e componentes aprovados

Baseline HEAD consultado antes desta etapa: `a4e3043a6920f83733cf130912bf8ea37459538f`.

Mudanças somente documentais: `Guia-CMS.md` lista os pontos de entrada ativos, controles Admin e propriedade por módulo; `Guia-ThreeJS.md` atualiza exemplos para o runtime/SceneRunner compartilhado e remove recomendação de `json_encode|raw` e script inline em templates Themes.

Não houve alteração de código, schema, migration, deploy ou GitHub write. A revisão das rotas encontrou os quatro módulos de cena nativos allowlisted (Hero, 404, Portfolio, Tools), configurados por Settings/SceneConfig; a seleção Themes só escolhe uma cena allowlisted. O runner `threejs-thumb.js` continua acionado por conteúdo Blog e isolado, fora dos fluxos Pages/Themes. Nas views/controllers Catalog, o único `Document::addScript()` encontrado é o runner de thumb do Blog; `common/header.twig` carrega o runtime Three condicionalmente.

Patch criado contra os arquivos do HEAD consultado.
