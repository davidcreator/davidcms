<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * MediaUpload — componente compartilhado de upload de imagens (F2-11, ADR 0011).
 *
 * Extraído pela "regra dos três": o fluxo estava byte-idêntico nos controllers
 * catalog/project e catalog/category (duplicação consciente dos ADRs 0008/0009);
 * a chegada do terceiro módulo com imagem (marketing/testimonial) autorizou
 * a extração. O componente NÃO sabe de HTTP/idioma: devolve códigos simbólicos
 * e cada controller traduz para as suas mensagens (comportamento de saída
 * preservado — baterias f28/f29 seguem verdes).
 *
 * Uso: new MediaUpload($catalogRoot) — $catalogRoot = dirname(DIR_APPLICATION).'/catalog/'
 */
namespace Reamur\System\Library;

final class MediaUpload {

    /** MIMEs aceitos (jpeg, png, webp, gif) — mesma lista dos módulos F2-8/F2-9 */
    public const MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /** Teto de 2 MB por arquivo */
    public const MAX = 2097152;

    public function __construct(
        private string $catalogRoot
    ) {}

    /**
     * Persiste um arquivo enviado (entrada de $_FILES) em $targetDir
     * (relativo ao catalog, ex.: 'view/image/testimonial/').
     *
     * @param array<string, mixed> $file      Entrada de $_FILES ('tmp_name','size','error')
     * @param string               $targetDir Subdiretório de destino (relativo, com barra final)
     * @return array{ok: bool, path: string, error: string} path relativo ao catalog;
     *         error ∈ {'no_file','size','type','move'} (controller traduz p/ idioma)
     */
    public function store(array $file, string $targetDir): array {
        if (str_ends_with($targetDir, '/') === false || str_contains($targetDir, '..')) {
            throw new \InvalidArgumentException('MediaUpload: targetDir inválido');
        }

        $tmp  = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);

        if ($tmp === '' || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'path' => '', 'error' => 'no_file'];
        }

        if ($size <= 0 || $size > self::MAX) {
            return ['ok' => false, 'path' => '', 'error' => 'size'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo ? (string)(finfo_file($finfo, $tmp) ?: '') : '';

        if ($finfo) {
            finfo_close($finfo);
        }

        if (!in_array($mime, self::MIME, true)) {
            return ['ok' => false, 'path' => '', 'error' => 'type'];
        }

        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default      => 'gif',
        };

        $dir = $this->catalogRoot . $targetDir;

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $filename = date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;

        if (@move_uploaded_file($tmp, $dir . $filename)) {
            return ['ok' => true, 'path' => $targetDir . $filename, 'error' => ''];
        }

        return ['ok' => false, 'path' => '', 'error' => 'move'];
    }

    /**
     * Só vale path relativo dentro de $targetDir (sem '..') e arquivo existente.
     * Retorna o path saneado ou '' — usado em validação/payload normalizado.
     */
    public function guard(string $path, string $targetDir): string {
        $path = str_replace('\\', '/', trim($path));

        if ($path === '' || !str_starts_with($path, $targetDir) || str_contains($path, '..')) {
            return '';
        }

        return $this->absolute($path) !== '' ? $path : '';
    }

    /** Resolve o path físico no catalog; '' se não existir */
    public function absolute(string $relative): string {
        $path = $this->catalogRoot . $relative;

        return is_file($path) ? $path : '';
    }

    /** Remoção física best-effort (delete de entidade / troca de imagem) */
    public function delete(string $relative): void {
        $path = $this->absolute($relative);

        if ($path !== '') {
            @unlink($path);
        }
    }
}
