#!/usr/bin/env bash
# ══════════════════════════════════════════════════════════════════
# e2e_f305.sh — "Latest News" do blog externo na home (ADR 0023)
#
#   PRÉ-CONDIÇÃO: instalação concluída (rode e2e_f301.sh antes).
#   Não requer seed novo (seção importa feed ao vivo com cache).
#
# Checks:
#   1 home 200 → seção #news presente com kicker real ×1 idioma
#   2 conteúdo REAL: card extraído do feed ao vivo bate coma a home
#   3 metadados: data d/m/Y + fonte davidcreator.com + CTA externo ↗
#   4 layout: classes do tema (news-card/grid-3), zero inline na seção
#   5 RESILIÊNCIA: cache file criado; segundo hit NÃO re-pede o feed
#     (mtime da página estável) e continua 200
#   6 RESILIÊNCIA: feed quebrado → home continua 200, seção se oculta
#     (cache de erro cobre o TTL — resultado negativo NÃO lança)
#   7 i18n ×3: kicker traduzido em pt/es, títulos do feed intocados
#   8 (negativo) nada escapa para o banco (nenhum INSERT/UPDATE de news no storage)
#   9 não pesa: payload da home cresce < 15 KB vs. sem seção
#  10 cabeçalho da home sem erros de PHP (logs limpos)
# ══════════════════════════════════════════════════════════════════
set -u
B="${BASE:-http://127.0.0.1:8090/davidcms/dev}"
C="$B/catalog"
FUI="/tmp/fui/dev"
CACHE_FILE="$FUI/system/storage/cache/cache.news_feed"
PASS=0; FAIL=0
ck() { if [ "$2" -eq 0 ]; then PASS=$((PASS+1)); echo "$1 pass — $3"; else FAIL=$((FAIL+1)); echo "$1 FAIL — $3"; fi; }
hdr() { grep -i "^HTTP" "$1" | tail -1 | grep -o "[0-9][0-9][0-9]"; }

sudo rm -f "$CACHE_FILE"

echo '══ 1 — home 200 + seção #news + kicker real ══'
curl -s -D /tmp/f305_a.hdr -o /tmp/f305_a.html "$C/"
curl -s "$C/?language=en-us" -o /tmp/f305_a.html > /dev/null 2>&1 # garante en como cookie default? não — GET ignora. refresh:
curl -s "$C/" -o /tmp/f305_a.html
OK=0
[ "$(hdr /tmp/f305_a.hdr)" = "200" ] || OK=1
grep -q 'id="news"' /tmp/f305_a.html || OK=1
grep -q 'class="kicker"' /tmp/f305_a.html || OK=1
grep -q 'news-card' /tmp/f305_a.html || OK=1
ck 1 $OK "news-presente"

echo '══ 2 — conteúdo REAL do feed aparece (amostra feita de XML → home) ══'
T0=$(curl -s -m 12 https://davidcreator.com/feed/ | grep -oPm1 '(?<=<title>)[^<]+')
T1=$(curl -s -m 12 https://davidcreator.com/feed/ | sed -n '/<item>/,/<\/item>/p' | grep -oPm1 '(?<=<title>)[^<]+' | sed 's/[[:space:]]\+/ /g;s/^\s\+//;s/\s\+$//')
OK=0
[ -n "$T1" ] || OK=1
# o primeiro título do feed precisa aparecer (normalizado p/ espaço single)
grep -qiF "$(printf '%s' "$T1" | sed 's/&/\\&/g')" /tmp/f305_a.html || OK=1
ck 2 $OK "feed-real-bate"

echo '══ 3 — metadados: data d/m/Y, fonte, CTA externo rel=noopener ══'
OK=0
grep -qE '[0-9]{2}/[0-9]{2}/[0-9]{4} · <b?davidcreator\.com|davidcreator\.com</p>?' /tmp/f305_a.html \
  || ( grep -qE 'news-meta">[0-9]{2}/[0-9]{2}/[0-9]{4} · davidcreator\.com' /tmp/f305_a.html ) || OK=1
grep -q 'target="_blank" rel="noopener">' /tmp/f305_a.html || OK=1
grep -q 'Read on davidcreator.com ↗' /tmp/f305_a.html || grep -q 'news-cta"' /tmp/f305_a.html || OK=1
ck 3 $OK "metadados-ok"

echo '══ 4 — layout do tema: grid-3 + news-card + zero style= na seção ══'
OK=0
# seção completa existe e sem inline dentro do bloco
python3 - <<'PY' > /tmp/f305_sec.txt
import re
h=open('/tmp/f305_a.html',encoding='utf-8',errors='ignore').read()
m=re.search(r'<section[^>]+id="news".*?</section>', h, re.S)
print(m.group(0) if m else '')
PY
grep -q 'news-card' /tmp/f305_sec.txt || OK=1
grep -q 'grid-3' /tmp/f305_sec.txt || OK=1
grep -q 'style=' /tmp/f305_sec.txt && OK=1
ck 4 $OK "sem-inline"

echo '══ 5 — cache criado + 2º hit sem re-crawl (mtime estável, 200) ══'
OK=0
[ -f "$CACHE_FILE" ] || OK=1
T1M=$(stat -c %Y "$CACHE_FILE" 2>/dev/null || echo 0)
curl -s -D /tmp/f305_b.hdr -o /tmp/f305_b.html "$C/"
T2M=$(stat -c %Y "$CACHE_FILE" 2>/dev/null || echo 0)
[ "$T1M" = "$T2M" ] || OK=1   # cache servido: arquivo não regravado
[ "$(hdr /tmp/f305_b.hdr)" = "200" ] || OK=1
diff -q <(grep -c 'news-card' /tmp/f305_a.html) <(grep -c 'news-card' /tmp/f305_b.html) > /dev/null || OK=1
ck 5 $OK "cache-ativo"

echo '══ 6 — degradação silenciosa: cache NEGATIVO → home ainda 200, seção se oculta ══'
OK=0
FE=$(sudo date +%s); FE=$((FE+3600))
printf '{"expire":%d,"data":{"items":[]}}' "$FE" | sudo tee "$CACHE_FILE" > /dev/null
curl -s -D /tmp/f305_c.hdr -o /tmp/f305_c.html "$C/"
[ "$(hdr /tmp/f305_c.hdr)" = "200" ] || OK=1
! grep -q 'id="news"' /tmp/f305_c.html || OK=1   # seção oculta quando feed vazio
grep -q 'id="portfolio"' /tmp/f305_c.html || OK=1  # resto da home íntegro
sudo rm -f "$CACHE_FILE"
ck 6 $OK "negativo-ok"

echo '══ 7 — i18n ×3: kicker traduzido; títulos do feed intocados ══'
curl -s "$C/?language=pt-br" -o /tmp/f305_pt.html
curl -s "$C/?language=es-eu" -o /tmp/f305_es.html
curl -s "$C/?language=en-us" -o /tmp/f305_en.html
OK=0
grep -q "Últimas notícias" /tmp/f305_pt.html || OK=1
grep -q "Últimas noticias" /tmp/f305_es.html || OK=1
grep -q "Latest news" /tmp/f305_en.html || OK=1
grep -q "Ler no davidcreator.com" /tmp/f305_pt.html || OK=1
# títulos vêm do feed em pt → mesmos nos 3 idiomas (escopo: só o 1º card da seção #news)
news_h3() {
  python3 - "$1" <<'PY'
import re,sys
h=open(sys.argv[1],encoding='utf-8',errors='ignore').read()
m=re.search(r'<section[^>]+id="news".*?</section>', h, re.S)
print(re.search(r'<h3>(.*?)</h3>', m.group(0), re.S).group(1).strip() if m else '')
PY
}
T0P=$(news_h3 /tmp/f305_pt.html)
T0E=$(news_h3 /tmp/f305_en.html)
[ -n "$T0P" ] && [ "$T0P" = "$T0E" ] || OK=1
sudo rm -f "$CACHE_FILE"; sudo rm -f "$CACHE_FILE"
ck 7 $OK "i18n-news"

echo '══ 8 — sem side-effects: storage sem arquivos de feed fora do cache; banco intocado ══'
OK=0
# model só pode escrever em cache.news_feed — nada com nome 'feed'/'rss' em outros diretórios
sudo find /tmp/fui/dev/system/storage -type f \( -name "*rss*" -o -name "*feed*" \) 2>/dev/null | grep -v "cache.news_feed" | grep -q . && OK=1
# banco de conteúdo idêntico ao pré-seção (model news não toca tabelas)
CNT=$(sudo mysql reamur_test -N -e "SELECT (SELECT COUNT(*) FROM rms_project) + (SELECT COUNT(*) FROM rms_tool) + (SELECT COUNT(*) FROM rms_testimonial)" 2>/dev/null)
[ "$CNT" = "17" ] || OK=1   # 10 projetos + 4 tools + 3 depoimentos (F3-01 seed)
ck 8 $OK "sem-side-effects"

echo '══ 9 — payload razoável: home com news < 15 KB a mais que home sem news ══'
curl -s "$C/" -o > /dev/null 2>&1
SZ_FULL=$(wc -c < /tmp/f305_a.html | tr -d ' ')
printf '{"expire":%d,"data":{"items":[]}}' $(($(sudo date +%s)+3600)) | sudo tee "$CACHE_FILE" > /dev/null
curl -s "$C/" -o /tmp/f305_c.html
SZ_BARE=$(wc -c < /tmp/f305_c.html | tr -d ' ')
DELTA=$((SZ_FULL - SZ_BARE))
OK=0
[ "$DELTA" -lt 15360 ] || OK=1
[ "$DELTA" -gt 500 ] || OK=1   # sanity — news realmente ocupa algo
sudo rm -f "$CACHE_FILE"
ck 9 $OK "payload-ok ($DELTA bytes)"

echo '══ 10 — error.log não cresceu de warnings/news nos GETs acima ══'
LOG="/tmp/fui/dev/system/storage/logs/error.log"
OK=0
if sudo test -f "$LOG"; then
  sudo grep -iE "news|feed|simplexml|curl|News|Feed" "$LOG" | grep -qiE "ERROR|Exception|Warning|Fatal" && OK=1
fi
# reidrata o cache real (caminho feliz) p/ o próximo run
curl -s "$C/" -o /dev/null 2>&1
ck 10 $OK "log-limpo"

echo "═══ RESUMO: $PASS pass, $FAIL fail ═══"
[ $FAIL -eq 0 ]
