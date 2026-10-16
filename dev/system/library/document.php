<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Metadados do documento HTML (título, descrição, assets).
 */
namespace Reamur\System\Library;

final class Document {
    private string $title       = '';
    private string $description = '';
    private string $keywords    = '';

    /** @var array<string, string> */
    private array $links   = [];
    /** @var array<string, string> */
    private array $styles  = [];
    /** @var array<string, string> */
    private array $scripts = [];

    public function setTitle(string $title): void {
        $this->title = $title;
    }

    public function getTitle(): string {
        return $this->title;
    }

    public function setDescription(string $description): void {
        $this->description = $description;
    }

    public function getDescription(): string {
        return $this->description;
    }

    public function setKeywords(string $keywords): void {
        $this->keywords = $keywords;
    }

    public function getKeywords(): string {
        return $this->keywords;
    }

    public function addLink(string $href, string $rel): void {
        $this->links[$href] = $rel;
    }

    public function addStyle(string $href, string $rel = 'stylesheet'): void {
        $this->styles[$href] = $rel;
    }

    public function addScript(string $href): void {
        $this->scripts[$href] = $href;
    }

    /** @return array<string, string> */
    public function getLinks(): array {
        return $this->links;
    }

    /** @return array<string, string> */
    public function getStyles(): array {
        return $this->styles;
    }

    /** @return array<string, string> */
    public function getScripts(): array {
        return $this->scripts;
    }
}
