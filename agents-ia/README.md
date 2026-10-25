# Instruções para agentes — BDREditor table increment

- O produto é ReamurCMS; o editor autoral é BDREditor.
- Não adicionar ou depender do WordPress Gutenberg; não copiar código/assets.
- Tratar o contrato em `docs/BDREditor-Table-Contract.json` como especificação desta entrega.
- Server validation/renderer são autoridade; client validation é somente UX.
- Preservar v1; tabela somente em v2. Não adicionar HTML/CSS/JS arbitrários.
- Não alterar fluxo de persistência, não criar autosave, endpoint, SQL, migration ou banco.
- Não enviar credenciais/dados reais em fixtures. Usar apenas material sintético.
- Testes runtime e staging estão fora da autorização desta entrega. Não executar PHP/browser/app/DB.
- Não escrever no GitHub nem publicar/deployar este pacote.
