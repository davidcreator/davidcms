# ADR 0115 — KAZEditor-Theme: a fonte do tema é guardada com quebra de linha `\n` (salvar sem mexer não muda um byte; o limite de tamanho vale para o que é guardado)

| | |
| --- | --- |
| **Status** | ✅ aceita (KZ-22, 2026-11-16) |
| **Data** | 2026-11-16 |
| **Contexto** | O navegador envia cada quebra de linha de um `<textarea>` como `CR LF`, qualquer que seja o sistema de quem edita. O formulário do tema guardava cada fonte como chegava. Resultado, visto na prova que salva da KZ-21 (ADR 0114): salvar um tema importado, sem mexer em nada, regravava as 25 fontes com um byte a mais por linha. No "David Universe 3D — Cena Real" são 617 linhas: o rascunho passava de 55.184 para 55.801 bytes de fonte e o pacote exportado de 89.758 para 90.992 bytes (no arquivo, cada `CR` é escrito como `\r`, dois caracteres), com outro checksum. O mesmo tema dava dois pacotes diferentes conforme tivesse sido importado ou salvo, e a comparação entre eles acusava as 25 fontes. Na bancada, sete dos doze temas já estavam assim. O limite de 131.072 bytes por fonte contava esse byte (ADR 0099): uma fonte de muitas linhas chegava ao limite antes da hora, e um pacote aceito na importação podia ser recusado ao salvar pelo formulário, sem nenhuma mudança. |
| **Relacionadas** | ADR 0099 (limite de tamanho medido em bytes), ADR 0087 (política do sandbox ao salvar), ADR 0114 (onde o defeito foi registrado), ADR 0112 (textos do tema por idioma) |

## Decisão

A fonte de um tema tem um só fim de linha: `\n`. `CR LF` e `CR` solto viram `\n` em todo caminho que guarda ou entrega uma fonte, e o tamanho é medido depois disso.

### Onde a fonte é normalizada

1. **No formulário do tema** (`buildDraftConfig()`), logo depois de saber que a fonte é texto e antes de qualquer conferência. O método serve aos três caminhos do formulário: salvar o rascunho, a prévia do canvas e a prévia do site. Usa `ThemeSourceMap::normalize()`, que já existia e já era aplicada ao desenhar o canvas.
2. **Ao importar um pacote** (`ThemePackage::decode()`), depois de conferido o checksum e antes da validação. O checksum continua valendo para o arquivo como foi escrito; um pacote exportado por uma versão anterior, com `CR LF` nas fontes, é aceito e guardado com `\n`.
3. **Ao exportar** (`ThemePackage::sanitizeConfig()`), antes de calcular o checksum do pacote. Um tema que ainda está no banco com `CR LF` já sai limpo, sem precisar ser salvo de novo.
4. **Na fonte devolvida ao formulário depois de um erro**, que passa a usar a mesma medida.

### O limite de tamanho

5. **Os 131.072 bytes valem para a fonte como ela é guardada.** Cada quebra de linha conta um byte. Antes contava dois.
6. **O limite não mudou de número e nenhuma fonte que cabia deixa de caber.** A fonte normalizada nunca é maior que a enviada. O que muda: uma fonte com muitas linhas ganha de volta um byte por linha.
7. **Uma só regra nos três lugares que medem:** o formulário, a importação do pacote e a conta do editor (`KazElementAttrs.tooLong()`), que avisa antes de escrever. O que um aceita, os outros aceitam.
8. **O script do editor conta `CR LF` como uma quebra**, `CR` solto como uma e `LF` como uma. No campo do navegador só existe `LF`; os outros dois casos estão cobertos para a conta não depender disso.

### O que não muda

9. **O conteúdo das fontes.** Só o fim de cada linha. O número de linhas é o mesmo, e o site renderiza o mesmo HTML.
10. **Os pacotes do repositório.** Nenhum dos sete tem `CR` nas fontes; exportar cada um de novo dá o checksum que o arquivo já traz. Um teste confere.
11. **O formato do pacote e o banco.** Nenhuma chave, coluna, rota ou texto de idioma novo.
12. **O campo dos textos do tema (`strings_json`).** O limite de 262.144 bytes dele continua medido como o formulário envia (ADR 0114, item 8). O campo é um JSON que o servidor lê e guarda como mapa; as quebras de linha entre as chaves não são guardadas, então salvar não o faz crescer.
13. **`ThemeSandbox::blocked()` e `check()`.** Continuam medindo o texto que recebem. Quem chama é que entrega a fonte normalizada.

## Tema salvo antes desta etapa

Um tema que já foi salvo pelo formulário tem `CR LF` nas fontes do rascunho, e na versão publicada se foi publicado depois. Nada precisa ser feito:

- O site continua mostrando a mesma página; para o HTML, os dois fins de linha valem o mesmo.
- **Exportar já entrega o pacote limpo.**
- **O primeiro "Salvar rascunho" limpa o rascunho.** A versão publicada fica limpa na publicação seguinte.

Não há migração no banco. Regravar todos os temas de uma instalação para tirar um byte por linha não compensa o risco.

## Alternativas descartadas

- **Normalizar só ao guardar e continuar medindo como chega.** Menos mudança (o script e os testes da ADR 0099 ficariam como estavam), mas mantinha a diferença entre importar e salvar: um pacote dentro do limite seria recusado pelo formulário sem que ninguém tivesse mexido nele.
- **Normalizar no navegador antes de enviar.** O navegador não deixa: a conversão para `CR LF` acontece no envio do formulário. E o servidor não pode depender do que o cliente manda.
- **Normalizar ao ler do banco.** Esconderia o problema sem resolvê-lo; cada leitura pagaria a conta, e o que está guardado continuaria diferente do que é exportado.
- **Guardar com `CR LF` em todos os caminhos.** Também daria um resultado só, mas os pacotes do repositório usam `\n`, o Git também, e todos teriam de ser reescritos.

## Limites conhecidos

- **A versão publicada de um tema antigo só fica limpa quando for publicada de novo.** Ver "Tema salvo antes desta etapa".
- **Um pacote exportado antes e outro depois desta etapa, do mesmo tema salvo pelo formulário, têm checksums diferentes.** O conteúdo é o mesmo; o de depois é igual ao do tema importado.
- **Só as fontes.** Outros campos de texto do tema que aceitam mais de uma linha não foram examinados nesta etapa; nenhum deles apareceu com `CR` nos doze temas da bancada.
- **`CR` solto dentro de uma fonte vira quebra de linha.** Era o que o canvas já fazia ao desenhar.

## Verificação

- PHPUnit em PHP 8.2 e 8.3: 1725 testes, 45475 conferências (eram 1720 e 44905). PHPStan sem erro. Os onze verificadores em Node passam; `check_kaz_element_style.js` foi de 425 para 550 conferências.
- `tests/fixtures/kaz_source_size.json`: 20 casos de tamanho montados por receita, rodados sobre o controller e o pacote (PHP) e sobre a conta do script (Node). Onze cabem, nove não; seis dos que cabem eram recusados pela regra antiga só por causa do `CR`.
- Onze estragos de propósito no código (`mutacoes.py`: o formulário deixa de normalizar, mede antes de normalizar, o pacote não normaliza ao exportar ou ao importar, esquece o `CR` solto, o script volta a contar dois bytes, e outros), um por vez: os onze foram acusados pelos testes.
- Bancada, Chromium, salvando de verdade (`prova.sh`, 28 conferências, tema "David Universe 3D — Cena Real"). O navegador enviou as quebras como `CR LF` e o rascunho ficou com as 25 fontes byte a byte iguais (55.184 bytes, nenhum `CR`). Salvar de novo: o pacote exportado não mudou um byte (89.758). Tema antigo, montado com `CR LF` no banco (617): o pacote exportado já saiu limpo e igual, o editor abriu normalmente e um salvar devolveu os bytes de antes. Fonte com 14.563 linhas e exatamente 131.072 bytes guardados (145.635 como enviada, que a regra antiga recusava): salva e reaberta inteira; com um byte a mais, o script avisou, o servidor recusou com a mensagem da fonte e nada foi gravado. Pacote como a versão anterior exportava (fontes com `CR LF`, checksum sobre elas, 90.992 bytes): importado, guardado sem `CR`, e exportado de novo igual ao pacote limpo. Ao fim, a tabela de temas voltou a ser igual à de antes (soma conferida).
- Antes da correção, o mesmo salvar sem mexer deixou 617 `CR` no rascunho (55.801 bytes de fonte) e um pacote de 90.992 bytes, byte a byte igual ao "pacote antigo" usado na prova acima.
- Bancada, regressão: os 79 roteiros de navegador das etapas KZ-05 a KZ-19 rodaram com o código novo; 76 passaram na rodada em lote. Os três que pararam (dois da KZ-06 e um da KZ-05, todos ao procurar um elemento que ainda não tinha sido desenhado) passaram em seguida, rodados sozinhos, duas ou três vezes cada. Os roteiros da KZ-06 e da KZ-07 que enchiam uma fonte até o limite passaram a medir como o servidor mede agora. Depois da rodada, a única diferença na tabela de temas era a esperada: os sete temas que tinham `CR LF` no rascunho (3.920 ao todo) ficaram sem nenhum, com o mesmo conteúdo.
- Bancada, provas da KZ-21 (`prova.cjs`, 175 conferências; `salvar.sh`, 20, com o site publicado nos três idiomas) e `editor.cjs` da KZ-20 (39 em cada pacote do David Universe 3D): passam.
- HTML da tela do editor, nos três idiomas, em dois temas: só mudam a versão dos três arquivos do canvas e as linhas que mudam a cada pedido.
- Guardas do repositório (`verify-checksums`, `verify-ci-workflow`, `verify-deps-layout`, `verify-build-prod`) e links dos `.md` sem erro.
