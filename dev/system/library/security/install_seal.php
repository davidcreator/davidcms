<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 */
namespace Reamur\System\Library\Security;

/**
 * Trava do instalador (ADR 0091, S12).
 *
 * O instalador grava o config.php do site e cria o primeiro administrador:
 * aberto em um site já instalado, entrega o site a quem chegar primeiro. Por
 * isso "instalado" não depende só do arquivo install.lock (que pode faltar:
 * assistente interrompido, pasta sem permissão de escrita, config.php criado
 * à mão). Vale o estado real: existe a trava OU existe um config.php do site.
 *
 * Classe pura, sem dependências: install/index.php a carrega com require
 * direto e decide ANTES de subir o núcleo.
 */
final class InstallSeal {
    public const LOCK = 'install.lock';

    /** Aplicações cujo config.php o instalador gera. */
    private const APPS = ['catalog', 'admin'];

    /**
     * Motivo pelo qual o instalador deve se recusar; '' = pode instalar.
     *
     * @param string $storageDir Pasta system/storage/ (onde mora a trava)
     * @param string $devDir     Pasta que contém catalog/, admin/ e install/
     *
     * @return ''|'lock'|'config'
     */
    public static function reason(string $storageDir, string $devDir): string {
        if (is_file(self::dir($storageDir) . self::LOCK)) {
            return 'lock';
        }

        foreach (self::configFiles($devDir) as $file) {
            // Arquivo vazio não é instalação: catalog/ e admin/ também o
            // tratam como "instalação pendente" e mandam para o assistente.
            if (is_file($file) && (int)@filesize($file) > 0) {
                return 'config';
            }
        }

        return '';
    }

    public static function isSealed(string $storageDir, string $devDir): bool {
        return self::reason($storageDir, $devDir) !== '';
    }

    /** Grava a trava. Devolve se ela existe ao final (falha não passa em silêncio). */
    public static function seal(string $storageDir): bool {
        $file = self::dir($storageDir) . self::LOCK;

        @file_put_contents($file, 'installed ' . date('c') . "\n");
        clearstatcache(true, $file);

        return is_file($file);
    }

    /** Remove a trava (instalação que falhou no meio e foi desfeita). */
    public static function unseal(string $storageDir): void {
        $file = self::dir($storageDir) . self::LOCK;

        if (is_file($file)) {
            @unlink($file);
            clearstatcache(true, $file);
        }
    }

    /**
     * @return list<string> Caminhos de catalog/config.php e admin/config.php
     */
    public static function configFiles(string $devDir): array {
        $files = [];

        foreach (self::APPS as $app) {
            $files[] = self::dir($devDir) . $app . '/config.php';
        }

        return $files;
    }

    /** A pasta install/ ainda está no servidor? (aviso do painel) */
    public static function installerPresent(string $devDir): bool {
        return is_file(self::dir($devDir) . 'install/index.php');
    }

    /** Página estática da recusa (403). Sem dados do servidor. */
    public static function lockedPage(): string {
        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="robots" content="noindex"><title>ReamurCMS — Install locked</title></head>'
            . '<body style="font-family:sans-serif;max-width:640px;margin:64px auto;text-align:center;">'
            . '<h1>Installation locked</h1>'
            . '<p>This ReamurCMS is already installed and the installer is sealed.</p>'
            . '<p>Delete the <code>install</code> folder from the server: it has no use after installation.</p>'
            . '<p>To reinstall, delete <code>system/storage/install.lock</code> and the generated <code>catalog/config.php</code> and <code>admin/config.php</code>.</p>'
            . '</body></html>';
    }

    private static function dir(string $dir): string {
        return rtrim(str_replace('\\', '/', $dir), '/') . '/';
    }
}
