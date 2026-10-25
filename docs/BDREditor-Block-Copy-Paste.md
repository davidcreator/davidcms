# Copiar/colar blocos — contrato e critérios

## Modelo de clipboard

A instância `Editor` contém um único `blockClipboard` efêmero. `copyBlock(id)` clona o nó selecionado em memória sem alterar o state persistido. A operação não chama a API do clipboard do navegador nem escreve no DOM como HTML.

`cloneBlockWithNewIds(source, makeId)` clona recursivamente e solicita um ID por nó. Se a árvore não tiver `children` em formato de lista, o gerador produzir IDs inválidos/duplicados ou a estrutura não puder ser clonada, retorna `null`.

## Inserção

`pasteBlock()` insere o clone depois do bloco selecionado, no mesmo array de irmãos; sem alvo encontrado, usa o fim de `blocks`. Isso permite duplicar a estrutura inteira sem transformar o bloco escolhido num container de destino.

Antes de commit:

1. IDs novos e únicos são gerados para todo o subdocumento copiado.
2. O candidato é inserido em uma cópia do state atual.
3. `validateDocument()` verifica schema v2, relações pai/filho, limites de quantidade, profundidade e demais regras existentes.
4. `utf8Length(JSON.stringify(next))` deve continuar ≤ 4 MiB.
5. Somente então `commit(next, copy.id)` registra uma única entrada de undo e atualiza o editor.

Se qualquer verificação falhar, o state não é alterado; a UI informa que o bloco não pode ser colado naquele ponto/limite.

## Semântica de uso

- **Copiar bloco**: clona o bloco acionado, seleciona-o e anuncia o estado.
- **Colar bloco**: usa o buffer da mesma instância e permanece disponível para novas colagens válidas.
- O botão fica indisponível sem buffer ou quando a simulação integral do candidato falha.
- Copiar não copia nem modifica o clipboard do sistema.
- Ctrl+C/Ctrl+V em texto continuam sendo controlados pelo navegador/editor; colagem textual segue `text/plain`.

## Compatibilidade

Não altera formato JSON, serialização, armazenamento, controllers de persistência, renderer ou tabelas. Arquivos de áudio/download mantêm as referências locais existentes; as verificações server-side já aplicáveis continuam sendo executadas no salvamento. O buffer é volátil e não acompanha reload, post ou outra aba.

## Limitações do checker

`e2e_bdre_block_copy_static.cjs` roda o helper de cópia JS numa VM Node sem DOM e inspeciona o fluxo de paste por texto. Não comprova o acionamento real de botões, leitores de tela, undo em browser ou integração PHP. Checkers cumulativos dos incrementos anteriores mantêm cobertura estática de atalhos, áudio e downloads.
