# BDREditor — Reabertura de Páginas + edição imersiva

Incremento preparado sobre o `main` consultado em `3604235c27e858d4ab97cdb71e86714fc74274aa`.

## Correção da reabertura (`node_fields_invalid`)

O serializer PHP decodificava o JSON em arrays associativos. Um objeto vazio de atributos (`{}`) virava um array PHP vazio e era serializado novamente como `[]`. O validador do navegador exige atributos como objeto, por isso o documento salvo era bloqueado ao reabrir.

A correção serializa `attributes` como objeto JSON. O controller de CMS > Páginas também canonicaliza envelopes v2 existentes em memória antes de montar o editor. Assim, documentos já afetados podem ser abertos; quando salvos novamente, a forma canônica é persistida no fluxo normal da página. Não há migração nem escrita automática ao abrir.

## Edição em tela cheia

O botão **Abrir em tela cheia** transforma o editor num espaço de edição que ocupa a janela. O botão **Voltar ao formulário** e `Esc` encerram o modo; o foco volta ao acionador, Tab permanece dentro do editor enquanto o diálogo está aberto e a rolagem do formulário de fundo fica bloqueada. O modo é responsivo e os rótulos usam português, espanhol ou inglês conforme `lang` no documento.

A tela cheia não salva automaticamente: é preciso voltar ao formulário e usar o botão Salvar existente. Não muda o schema ou o comportamento de persistência.

## Aplicar

Copie os quatro arquivos do diretório `dev/` para os mesmos caminhos no checkout atualizado do projeto. O pacote não contém a aplicação completa; é um overlay incremental. Revise o diff antes de aplicar.

## Verificação

- `tests/check_reopen_and_immersive.js`: 11 verificações PASS, incluindo reprodução da forma quebrada e validação pelo cliente.
- 19 checkers/suítes E2E estáticas executados contra os arquivos atualizados: PASS.
- `node --check` no editor: PASS.
- PHP não está instalado neste ambiente: não foi possível executar `php -l`, nem uma requisição real do controller ou round-trip no banco.
- GitHub foi consultado somente para leitura; sem commits, push, banco, migrations, staging ou produção.
