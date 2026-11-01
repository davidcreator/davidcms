# F9-99 — Exportar e importar presets de estilo

## Objetivo e fluxo

Permitir fazer backup da biblioteca F9-98 ou transferir estilos entre navegadores/instalações sem incluir documentos, conteúdo ou dados do usuário.

- **Exportar JSON** baixa um arquivo `bdre-style-presets-AAAA-MM-DD.json` no formato `{format:"bdre-style-presets", version:1, presets:[{name,style}]}`.
- O arquivo não contém IDs locais, IDs de usuário, nomes de Pages/Posts, conteúdo, links ou mídia.
- **Importar JSON** aceita apenas `.json` de até 512 KiB; verifica o formato versão 1, nomes, tipos e atributos visuais permitidos.
- A prévia mostra quais presets estão prontos, repetidos, inválidos ou excedem o limite de 50. Nomes repetidos (sem distinção entre maiúsculas/minúsculas) e entradas inválidas são ignorados; arquivos de formato inválido são recusados por inteiro.
- O usuário confirma explicitamente. O merge é recalculado contra a biblioteca atual, limitado a 50 itens e gravado somente no `localStorage` do contexto atual. Cancelar/`Esc` não grava nada.
- A importação nunca aplica estilos ao documento automaticamente; após importar, a biblioteca fica disponível para aplicar com a prévia/confirm da F9-97.

## Segurança e escopo

- O parser rejeita chaves extras, versão incompatível, arquivos acima do limite, tipos não reconhecidos, valores de atributos inválidos e nomes com controles.
- Somente `name`, `sourceType`, `appearance` validado e atributos de estilo do allowlist são importados. Texto, URLs, mídias, IDs de blocos, bloqueios e hierarquia não existem no esquema de transferência.
- A biblioteca de destino permanece separada por usuário e entre Pages e Blog. O arquivo JSON pode ser transportado entre contextos, mas o merge só altera a biblioteca local aberta pelo usuário.
- Nenhuma API remota, banco de dados ou migração é usada. Asset cache key: `threejs-45-style-transfer`.

## Validação disponível

- `node --check dev/admin/view/js/blog-block-editor.js`
- `node tools/f9-99-style-preset-transfer-smoke.js` testa serialização/parser/planejamento em Node e verifica integrações estáticas; não simula seletor de arquivos ou downloads de navegador.
- Smokes F9-61, F9-66 e F9-76–F9-98.
- `python3 tools/f9-57-static-battery.py` e parsing CSS com `tinycss2`.
- PHP CLI e navegador real não estão disponíveis neste ambiente.
