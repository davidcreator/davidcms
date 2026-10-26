# Ajuda localizada do Three.js Preview

O caminho de execução customizado já usa o runtime self-hosted e seu iframe `sandbox="allow-scripts"` de origem opaca; a CSP bloqueia conexões de rede. O patch apenas alinha a cópia em pt-br/en-us/es-eu com esse comportamento: Preview completo e thumb pública podem executar o script dentro do sandbox; o cartão do editor não o executa.

Base de comparação: HEAD `d1c8fcba10c593142d8dfc9a495273805bf6787e`.
