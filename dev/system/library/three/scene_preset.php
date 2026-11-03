<?php declare(strict_types=1);
/**
 * F13-D / HOTFIX de autoload — caminho historico de compatibilidade.
 *
 * O Autoloader (dev/system/engine/autoloader.php) converte cada segmento do
 * namespace para snake_case: ScenePresetRegistry -> scene_preset_registry.php.
 * A classe canonica passa a viver em scene_preset_registry.php, que e o unico
 * arquivo que o autoloader consegue resolver. Este arquivo permanece apenas
 * para codigo que ainda faca require direto do caminho antigo; require_once
 * evita declaracao duplicada da classe quando os dois sao carregados.
 *
 * Nenhuma regra, preset ou allowlist e duplicada aqui.
 */
require_once __DIR__ . '/scene_preset_registry.php';
