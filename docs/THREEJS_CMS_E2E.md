# Roteiro E2E — BDREditor Three.js no CMS

**Escopo:** verificação funcional em ambiente CMS de teste já provisionado. Não executar em produção; não fazer migrations/deploy. Use uma conta de teste autorizada, sem compartilhar credenciais. Os passos abaixo não apagam conteúdo nem arquivos.

## Pré-condições

- Arquivos do pacote `BDREditor-Blog-Audio-Video-ThreeJS-Custom-JS-Sandbox` instalados no ambiente de teste, incluindo `three.module.js` e `three.core.js`.
- Cache dos assets renovado (os URLs do editor/runtime já usam versionamento específico); fazer hard reload uma vez após instalar.
- Conta com `modify cms/page` para validar CMS > Páginas. Para Blog, conta com `modify blog/blog`.
- DevTools opcional aberto em Console e Network; não compartilhar logs que possam conter dados privados, tokens ou URLs internas.

## Caso A — editor realmente aparece em CMS > Páginas

1. Entre no CMS e abra **CMS > Páginas**. Crie uma página de teste ou abra uma página descartável.
2. Confirme que o BDREditor está **na própria tela do formulário** (não em rota/tela separada).
3. No Inserir, adicione **Thumb 3D (Three.js)** e selecione o bloco.
4. No próprio cartão do bloco, abra **Editar thumb e script**. Confirme que aparecem os controles **Formas na cena**, **Adicionar forma**, **Iluminação**, **Adicionar luz** e o campo de script. O Inspetor lateral também pode editar os mesmos atributos se estiver aberto.
5. Adicione uma forma e uma luz. Troque o tipo/cor da forma e animação; veja os valores refletidos na lista. Salve pelo botão normal do formulário.

**Esperado:** controles visíveis e utilizáveis, sem erro JavaScript; o formulário continua sendo o único caminho de salvamento.

## Caso B — script isolado persiste e executa na página pública

1. No bloco do Caso A, preencha **JavaScript personalizado (sandbox)** no editor aberto dentro do cartão com:

   ```js
   const mesh = new THREE.Mesh(
     new THREE.BoxGeometry(0.8, 0.8, 0.8),
     new THREE.MeshStandardMaterial({ color: 0xff33aa })
   );
   mesh.position.set(1, 0, 0);
   scene.add(mesh);
   onFrame(({ delta }) => {
     mesh.rotation.x += delta;
     mesh.rotation.y += delta * 0.6;
   });
   ```

2. Salve a página pelo formulário. Feche/saia e reabra a mesma página no editor.
3. Confirme que a forma, luz e código continuam nos campos. Não usar uma gravação manual do inspetor ou endpoint separado.
4. Abra a página pública e aguarde a thumb entrar na área visível.
5. Na aba Elements, confirme que a thumb usa um `iframe` com sandbox **somente** `allow-scripts` (sem `allow-same-origin`). Confirme o cubo rosa girando junto à cena declarativa.
6. Na aba Network, confirme que os assets Three.js vêm do próprio site e que o iframe não carrega recursos externos. O runtime precisa buscar os módulos locais; isso é diferente de uma requisição externa.

**Esperado:** o texto é preservado no envelope v2 existente; a página pública mostra o cubo animado. O código não pode acessar o DOM/cookies do CMS e a CSP do frame bloqueia rede externa.

## Caso C — regressão da cena declarativa e validação

1. Crie outra thumb sem `custom_js`, acrescente uma forma/luz e salve/reabra.
2. Abra a página pública: a cena sem script ainda deve renderizar, sem iframe customizado obrigatório.
3. Opcional: use um script sintaticamente inválido em um bloco descartável. Salvar deve continuar possível; no público, a falha deve permanecer contida no frame e o resto da página deve continuar operável.
4. Opcional: tente inserir mais de 12 formas ou mais de 4 luzes. O Inspetor deve desabilitar a inclusão no limite; deve manter pelo menos uma forma e uma luz.

## Automação Playwright opcional

`tools/e2e/threejs_cms_playwright.cjs` automatiza os casos de inserção, salvamento, reabertura e execução de `onFrame` na thumb pública. Ele grava conteúdo na página selecionada; execute somente em uma página **descartável** num ambiente de teste isolado.

1. Instale Playwright apenas no clone/ambiente de teste, sem alterar dependências do produto: `npm install --no-save playwright` e `npx playwright install chromium`.
2. Faça login manualmente e salve o estado local do navegador: `npx playwright codegen --save-storage="$HOME/.cache/reamur-cms-test-state.json" "$CMS_EDIT_URL"`. Mantenha o arquivo fora do repositório; nunca o compartilhe nem commite.
3. Defina `CMS_EDIT_URL`, `PUBLIC_PAGE_URL`, `PLAYWRIGHT_STORAGE_STATE` e `E2E_CONFIRM_DISPOSABLE=1`. O editor e a URL pública devem apontar para a mesma página de teste.
4. Execute `node tools/e2e/threejs_cms_playwright.cjs`. Use `HEADLESS=0` para observar o navegador. O runner não lê nem imprime senhas/tokens.

A automação **não** testa as novas configurações administrativas do Hero/404: valide essas telas manualmente em ambiente de teste, alternando os controles, salvando, recarregando e confirmando que home/404 mantêm o fallback quando desativadas.

## Resultado a registrar

Devolva somente uma tabela curta com `PASS`/`FAIL` para A, B e C, navegador/versão e mensagens de erro sanitizadas (sem tokens, credenciais, URLs privadas ou dados de usuários). Em caso de falha, informe o passo, o que era esperado e o que ocorreu; não envie cookies, credenciais, dumps ou logs runtime completos.

## O que esta auditoria confirmou

A branch `main` foi verificada em `df624f746b5cbcbd009cd01a38aa76c9a6dea1b9`. Os 22 arquivos do pacote `ThreeJS-Native-System-Admin-Catalog` coincidem byte a byte com esse HEAD. O checker estático detectou dois itens de QA ausentes/desatualizados no HEAD: o runner Playwright referido pelo checker não estava no tree e o checker de cache esperava `threejs-4`, enquanto os controllers já servem `threejs-5`. Esta correção inclui o runner e atualiza a expectativa. A execução funcional ainda requer um runtime CMS autenticado; não executar em produção.
