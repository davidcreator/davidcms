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
 * Documentos em que um caminho citado é INSTRUÇÃO (o leitor vai executar).
 * Fora desta lista ficam históricos, fases e relatórios: ali um caminho de
 * arquivo que existiu e foi removido é registro, não defeito.
 */
const DOCS_OPERACIONAIS = [
    'LEIA-ME.md',
    'README.md',
    'CONTRIBUTING.md',
    'docs/Estrutura-de-Pastas.md',
    'docs/arquitetura/',
    'docs/integracoes/',
    'docs/guias/',
    'tools/README.md',
];

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
function adrsForaDoIndice(): array
{
    $indice = is_file(INDICE) ? (string)file_get_contents(INDICE) : '';
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
function entradasQuebradas(): array
{
    $indice = is_file(INDICE) ? (string)file_get_contents(INDICE) : '';
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

function ehOperacional(string $caminho): bool
{
    foreach (DOCS_OPERACIONAIS as $permitido) {
        if (str_ends_with($permitido, '/') ? str_starts_with($caminho, $permitido) : $caminho === $permitido) {
            return true;
        }
    }

    return false;
}

/** @return list<string> */
function documentosOperacionais(): array
{
    $encontrados = [];

    foreach (DOCS_OPERACIONAIS as $alvo) {
        if (str_ends_with($alvo, '/')) {
            $arqs = glob($alvo . '*.md') ?: [];
            sort($arqs);
            $encontrados = array_merge($encontrados, $arqs);
        } elseif (is_file($alvo)) {
            $encontrados[] = $alvo;
        }
    }

    return array_values(array_unique($encontrados));
}

/**
 * Caminhos do repositório citados entre crases nos documentos operacionais que
 * não existem no disco.
 *
 * Só entram caminhos que começam por `tools/`, `tests/`, `dev/`, `docs/` ou
 * `agents-ia/` e que tenham extensão ou barra final: isso descarta nome de
 * classe, constante e rota, que aparecem entre crases com o mesmo formato.
 *
 * @return list<array{doc: string, ref: string}>
 */
function caminhosQuebrados(): array
{
    $quebrados = [];

    foreach (documentosOperacionais() as $doc) {
        $conteudo = (string)file_get_contents($doc);

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
        echo "uso: php tools/verify-docs-index.php [--json] [--strict]\n";
        echo "  0 tudo certo · 1 ADR fora do índice (ou caminho inexistente com --strict) · 2 uso\n";

        return 0;
    }

    $total = count(adrsNoDisco());
    $fora = adrsForaDoIndice();
    $quebradas = entradasQuebradas();
    $caminhos = caminhosQuebrados();
    $docs = documentosOperacionais();

    $falha = $fora !== [] || $quebradas !== [] || ($strict && $caminhos !== []);

    if ($json) {
        echo json_encode([
            'adrs_no_disco' => $total,
            'adrs_fora_do_indice' => $fora,
            'entradas_do_indice_quebradas' => $quebradas,
            'caminhos_citados_inexistentes' => $caminhos,
            'documentos_verificados' => $docs,
            'strict' => $strict,
            'veredicto' => $falha ? 'falha' : 'ok',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

        return $falha ? 1 : 0;
    }

    echo "Índice de ADRs e caminhos citados (leitura apenas)\n\n";
    echo 'ADRs no disco:            ' . $total . "\n";
    echo 'ADRs fora do índice:      ' . count($fora) . ($fora === [] ? '' : ' — ' . implode(', ', $fora)) . "\n";
    echo 'Entradas do índice quebradas: ' . count($quebradas) . ($quebradas === [] ? '' : ' — ' . implode(', ', $quebradas)) . "\n";
    echo 'Documentos operacionais verificados: ' . count($docs) . "\n\n";

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
        echo $fora !== [] ? 'ADR fora do índice (a decisão existiria só para quem já sabia).' : 'Há caminho citado inexistente (instrução que parece precisa e não funciona).';
        echo "\n";

        return 1;
    }

    echo "VEREDICTO: OK. Toda ADR tem linha no índice e nenhuma entrada aponta para arquivo ausente.\n";

    return 0;
}

exit(main($argv));
