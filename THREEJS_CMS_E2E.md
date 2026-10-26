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
4. No Inspetor, confirme os controles **Formas na cena**, **Adicionar forma**, **Iluminação**, **Adicionar luz**, rótulo e os campos avançados.
5. Adicione uma forma e uma luz. Troque o tipo/cor da forma e animação; veja os valores refletidos na lista. Salve pelo botão normal do formulário.

**Esperado:** controles visíveis e utilizáveis, sem erro JavaScript; o formulário continua sendo o único caminho de salvamento.

## Caso B — script isolado persiste e executa na página pública

1. No bloco do Caso A, preencha **JavaScript personalizado (sandbox)** com:

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

## Resultado a registrar

Devolva somente uma tabela curta com `PASS`/`FAIL` para A, B e C, navegador/versão e mensagens de erro sanitizadas (sem tokens, credenciais, URLs privadas ou dados de usuários). Em caso de falha, informe o passo, o que era esperado e o que ocorreu; não envie cookies, credenciais, dumps ou logs runtime completos.

## O que esta sessão confirmou

A branch `main` (`bf38248a69bb3561d022b151197a5a262cb5131d`) foi consultada somente para leitura. Os 21 arquivos de código, idioma, documentação e testes alterados desde o pacote anterior coincidiram byte a byte com a entrega. Checkers estáticos e a importação local do Three.js foram executados; isso **não substitui** os casos acima em um runtime CMS autenticado.
