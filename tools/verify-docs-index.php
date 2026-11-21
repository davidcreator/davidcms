<?php declare(strict_types=1);
/**
 * ADR 0126 — o índice de ADRs e os caminhos citados nos documentos.
 *
 * Duas coisas quebram em silêncio num repositório que cresce por entrega:
 *
 *   1. a ADR é escrita, entra no git e **não entra no índice** — quem procura
 *      pelo índice não acha, e a decisão passa a existir só para quem já sabia;
 *   2. o documento manda rodar `tools/alguma-coisa.php` ou abrir
 *      `dev/install/sql/algum.sql` que **não existe** — o pior tipo de erro de
 *      documentação, porque a instrução parece precisa.
 *
 * Este verificador checa as DUAS tanto no estado atual quanto no que o `git`
 * deixou registrado: ADR nova sem linha no índice reprova antes do commit.
 *
 * ADR 0127 — QUAIS documentos entram na checagem 2 não é uma lista escrita à
 * mão. A classificação vem de `tools/verify-docs-index.js`, derivada das pastas
 * (`agents-ia/decisoes/`, `docs/historico/`, `docs/fases/` e os relatórios
 * datados da raiz são histórico; o resto é operacional). A lista anterior
 * ficou incompleta em duas semanas — `docs/PROJECT-HANDOFF.md` e vinte guias de
 * tema nunca foram verificados. Documento novo entra sozinho.
 *
 * Uso:
 *   php tools/verify-docs-index.php            # checa e imprime
 *   php tools/verify-docs-index.php --json
 *   php tools/verify-docs-index.php --strict   # caminho citado inexistente vira erro
 *
 * Códigos de saída:
 *   0  tudo certo
 *   1  há ADR fora do índice (sempre erro) ou caminho citado inexistente (só com --strict)
 *   2  erro de uso
 *
 * Leitura apenas: não escreve, não comita, não acessa rede nem banco.
 */

const DIR_ADR = 'agents-ia/decisoes';
const INDICE = 'agents-ia/decisoes/README.md';

/**
 * Caminhos que o Git NÃO versiona e que só existem depois de instalar
 * dependências ou gerar documentação. Citá-los é correto: são exatamente os
 * lugares para onde `composer install` e o gerador escrevem.
 *
 * Sem esta lista, o verificador reprova num clone limpo o que ele aprova na
 * máquina de quem já instalou — falso positivo que apareceu na prova em clone
 * limpo deste próprio commit.
 */
const CAMINHOS_GERADOS = [
    'dev/system/storage/vendor/',
    'dev/system/storage/node_modules/',
    'dev/system/storage/cache/',
    'dev/system/storage/logs/',
    'docs/api/',
    'prod/',
];

function ehCaminhoGerado(string $ref): bool
{
    foreach (CAMINHOS_GERADOS as $prefixo) {
        if (str_starts_with($ref, $prefixo) || rtrim($ref, '/') === rtrim($prefixo, '/')) {
            return true;
        }
    }

    return false;
}

/** @return list<string> */
function adrsNoDisco(): array
{
    $arqs = glob(DIR_ADR . '/[0-9][0-9][0-9][0-9]-*.md') ?: [];
    sort($arqs);

    return $arqs;
}

/** @return list<string> */
function adrsForaDoIndice(string $caminhoDoIndice = INDICE): array
{
    $indice = is_file($caminhoDoIndice) ? (string)file_get_contents($caminhoDoIndice) : '';
    $fora = [];

    foreach (adrsNoDisco() as $arquivo) {
        $numero = substr(basename($arquivo), 0, 4);

        if (preg_match('/\[' . $numero . '\]\(/', $indice) !== 1) {
            $fora[] = $numero;
        }
    }

    return $fora;
}

/** Linhas do índice que apontam para arquivo que não existe. @return list<string> */
function entradasQuebradas(string $caminhoDoIndice = INDICE): array
{
    $indice = is_file($caminhoDoIndice) ? (string)file_get_contents($caminhoDoIndice) : '';
    $quebradas = [];

    if (preg_match_all('/\[(\d{4})\]\(([^)]+)\)/', $indice, $m, PREG_SET_ORDER) === false) {
        return [];
    }

    foreach ($m as $achado) {
        if (!is_file(DIR_ADR . '/' . $achado[2])) {
            $quebradas[] = $achado[1] . ' → ' . $achado[2];
        }
    }

    return $quebradas;
}

/**
 * A tabela do índice é `| ADR | Resumo | Status | Data |`. Uma linha com outra
 * contagem de colunas existe: `0106` foi escrita sem status e sem data, e a
 * `0124` tem `pending|requires_action|authorized` no texto — o pipe dentro do
 * célula quebra a linha em seis colunas e o Markdown renderiza a data no lugar
 * do resumo. Ninguem lê uma tabela quebrada, e a ADR passa a existir só para
 * quem souber o número do arquivo (ADR 0129).
 *
 * @return list<string>
 */
function linhasMalformadas(string $caminhoDoIndice = INDICE): array
{
    $indice = is_file($caminhoDoIndice) ? (string)file_get_contents($caminhoDoIndice) : '';
    $malformadas = [];

    foreach (explode("\n", $indice) as $linha) {
        if (preg_match('/^\|\s*\[(\d{4})\]\(/', $linha, $m) !== 1) {
            continue;
        }

        // `\|` dentro da celula e texto (barra em `pending\|requires_action`);
        // so separa celula o pipe nao escapado. Contar todo pipe reprovava
        // linha correta e incentivava encurtar o resumo em vez de escapar.
        $celulas = array_map('trim', preg_split('/(?<!\\\\)\|/', trim($linha, '|')));

        if (count($celulas) !== 4) {
            $malformadas[] = $m[1] . ' → ' . count($celulas) . ' colunas (a tabela tem 4: ADR, Resumo, Status, Data)';
            continue;
        }

        foreach (['Status' => 2, 'Data' => 3] as $nome => $i) {
            if ($celulas[$i] === '') {
                $malformadas[] = $m[1] . ' → célula de ' . $nome . ' vazia';
            }
        }
    }

    return $malformadas;
}

/**
 * Escopo derivado, nunca enumerado: a tabela vem de `tools/verify-docs-index.js`,
 * para o guarda e os testes unitários concordarem por construção (ADR 0127).
 *
 * @return array{operacionais: list<string>, historicos: list<string>, caminhos_gerados: list<string>}
 */
function escopoDocumentos(): array
{
    $script = __DIR__ . '/verify-docs-index.js';
    $cmd = 'node ' . escapeshellarg($script) . ' --json 2>/dev/null';
    $saida = @shell_exec($cmd);

    if (!is_string($saida) || trim($saida) === '') {
        fwrite(STDERR, "erro: não foi possível classificar os documentos (node tools/verify-docs-index.js).\n");
        fwrite(STDERR, "      Node é necessário: a classificação operacional/histórico é derivada, não escrita à mão.\n");
        exit(2);
    }

    $dados = json_decode($saida, true);

    if (!is_array($dados) || !isset($dados['operacionais'], $dados['historicos'])) {
        fwrite(STDERR, "erro: saída inválida de tools/verify-docs-index.js --json.\n");
        exit(2);
    }

    return $dados;
}

/**
 * Caminhos do repositório citados entre crases nos documentos operacionais que
 * não existem no disco.
 *
 * Só entram caminhos que começam por `tools/`, `tests/`, `dev/`, `docs/` ou
 * `agents-ia/` e que tenham extensão ou barra final: isso descarta nome de
 * classe, constante e rota, que aparecem entre crases com o mesmo formato.
 *
 * Linha de CITAÇÃO (começa com `>`) é registro, não instrução: ali o documento
 * está transcrevendo — uma retificação que reproduz a frase errada, um trecho
 * de depoimento. Cobrar que a citação aponte para arquivo existente obrigaria a
 * falsificar o original. ADR 0127.
 *
 * @return list<array{doc: string, ref: string}>
 */
function caminhosQuebrados(array $documentos): array
{
    $quebrados = [];

    foreach ($documentos as $doc) {
        $conteudo = (string)file_get_contents($doc);

        // Citação não é instrução: `>` transcreve o que se disse, inclusive o erro.
        $conteudo = implode("\n", array_filter(
            explode("\n", $conteudo),
            static fn (string $linha): bool => !str_starts_with(ltrim($linha), '>')
        ));

        if (preg_match_all('/`((?:tools|tests|dev|docs|agents-ia)\/[A-Za-z0-9_.\/-]+)`/', $conteudo, $m) === false) {
            continue;
        }

        foreach (array_unique($m[1]) as $ref) {
            if (str_contains($ref, '…') || str_contains($ref, '...')) {
                continue; // elipse é padrão, não caminho
            }

            if (ehCaminhoGerado($ref)) {
                continue; // não versionado: existe só depois de instalar/gerar
            }

            $temExtensao = preg_match('/\.[a-z0-9]{2,5}$/i', $ref) === 1;
            $ehDiretorio = str_ends_with($ref, '/');

            if (!$temExtensao && !$ehDiretorio) {
                continue;
            }

            if (!file_exists($ref)) {
                $quebrados[] = ['doc' => $doc, 'ref' => $ref];
            }
        }
    }

    return $quebrados;
}

function main(array $argv): int
{
    $json = in_array('--json', $argv, true);
    $strict = in_array('--strict', $argv, true);

    if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
        echo "uso: php tools/verify-docs-index.php [--json] [--strict] [caminho-do-indice]\n";
        echo "  0 tudo certo · 1 ADR fora do índice (ou caminho inexistente com --strict) · 2 uso\n";

        return 0;
    }

    // Caminho opcional do indice candidato — mesmo convenio do auditor do
    // workflow (tools/verify-ci-workflow.sh [caminho-do-workflow]): permite
    // auditar um README proposto sem tocar no do repositorio.
    $indiceArg = INDICE;
    foreach (array_slice($argv, 1) as $arg) {
        if (strncmp($arg, '--', 2) !== 0) {
            $indiceArg = $arg;
        }
    }

    $escopo = escopoDocumentos();
    $total = count(adrsNoDisco());
    $fora = adrsForaDoIndice($indiceArg);
    $quebradas = entradasQuebradas($indiceArg);
    $malformadas = linhasMalformadas($indiceArg);
    $docs = $escopo['operacionais'];
    $caminhos = caminhosQuebrados($docs);

    $falha = $fora !== [] || $quebradas !== [] || $malformadas !== []
        || ($strict && $caminhos !== []);

    if ($json) {
        echo json_encode([
            'adrs_no_disco' => $total,
            'adrs_fora_do_indice' => $fora,
            'entradas_do_indice_quebradas' => $quebradas,
            'entradas_do_indice_malformadas' => $malformadas,
            'caminhos_citados_inexistentes' => $caminhos,
            'documentos_verificados' => $docs,
            'documentos_historicos' => $escopo['historicos'],
            'caminhos_gerados' => $escopo['caminhos_gerados'],
            'strict' => $strict,
            'veredicto' => $falha ? 'falha' : 'ok',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

        return $falha ? 1 : 0;
    }

    echo "Índice de ADRs e caminhos citados (leitura apenas)\n\n";
    echo 'ADRs no disco:            ' . $total . "\n";
    echo 'ADRs fora do índice:      ' . count($fora) . ($fora === [] ? '' : ' — ' . implode(', ', $fora)) . "\n";
    echo 'Entradas do índice quebradas: ' . count($quebradas) . ($quebradas === [] ? '' : ' — ' . implode(', ', $quebradas)) . "\n";
    echo 'Entradas do índice malformadas: ' . count($malformadas) . ($malformadas === [] ? '' : ' — ' . implode('; ', $malformadas)) . "\n";
    echo 'Documentos operacionais verificados: ' . count($docs)
        . ' (históricos ignorados: ' . count($escopo['historicos']) . ")\n\n";

    if ($caminhos === []) {
        echo "Caminhos citados nos documentos operacionais: todos existem.\n\n";
    } else {
        echo 'Caminhos citados que NÃO existem: ' . count($caminhos) . "\n";

        foreach ($caminhos as $achado) {
            echo '  ' . $achado['doc'] . ' → ' . $achado['ref'] . "\n";
        }

        echo "\n" . ($strict
            ? "(--strict) caminho inexistente é erro neste modo.\n\n"
            : "Isto é aviso, não erro: documento antigo pode citar arquivo que existiu.\nUse --strict para tratar como erro.\n\n");
    }

    if ($falha) {
        echo "VEREDICTO: FALHA. ";
        echo $fora !== []
            ? 'ADR fora do índice (a decisão existiria só para quem já sabia).'
            : ($malformadas !== []
                ? 'Linha do índice fora do formato da tabela (Status e Data não aparecem).'
                : 'Há caminho citado inexistente (instrução que parece precisa e não funciona).');
        echo "\n";

        return 1;
    }

    echo "VEREDICTO: OK. Toda ADR tem linha no índice, toda linha tem as 4 colunas da tabela e nenhuma entrada aponta para arquivo ausente.\n";

    return 0;
}

exit(main($argv));
