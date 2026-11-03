<?php declare(strict_types=1);
/**
 * F13-F — section builder declarativo.
 *
 * NAO cria uma segunda allowlist: os identificadores e a validacao de ordem
 * sao delegados ao contrato existente \Reamur\System\Library\Cms\PageLayout
 * ('home'). Aqui ficam apenas METADADOS de apresentacao (rotulo, grupo,
 * partial, altura/tom da fita de previa) e a projecao usada pelo Admin e
 * pelo Catalog. Nenhum HTML, Twig, IO, eval, regex de usuario ou SQL.
 *
 * `sections` e uma chave opcional do JSON do config do tema:
 *   - ausente  => layout legado do CMS (cms/slot.sections), sem mudanca;
 *   - presente => lista ORDENADA de ids allowlistados ([] = nenhuma secao).
 */
namespace Reamur\System\Library\Theme;

use Reamur\System\Library\Cms\PageLayout;

final class SectionRegistry {
    public const ROUTE = 'home';

    /** Metadados estaveis; a lista de ids validos vem SOMENTE de PageLayout. */
    private const META = [
        'hero'         => ['group'=>'opening',  'partial'=>'partial/hero-3d.twig',    'kind'=>'static',   'height'=>46, 'tone'=>'#2563eb'],
        'about'        => ['group'=>'content',  'partial'=>'partial/about.twig',      'kind'=>'static',   'height'=>30, 'tone'=>'#7c3aed'],
        'methodology'  => ['group'=>'content',  'partial'=>'partial/methodology.twig','kind'=>'static',   'height'=>34, 'tone'=>'#7c3aed'],
        'skills'       => ['group'=>'content',  'partial'=>'partial/skills.twig',     'kind'=>'static',   'height'=>30, 'tone'=>'#7c3aed'],
        'services'     => ['group'=>'content',  'partial'=>'partial/services.twig',   'kind'=>'static',   'height'=>34, 'tone'=>'#7c3aed'],
        'editorial'    => ['group'=>'content',  'partial'=>'(cms/slot.render)',       'kind'=>'editorial','height'=>28, 'tone'=>'#0f766e'],
        'testimonials' => ['group'=>'proof',    'partial'=>'partial/testimonials.twig','kind'=>'dynamic','height'=>30, 'tone'=>'#0f766e'],
        'portfolio'    => ['group'=>'proof',    'partial'=>'partial/portfolio.twig',  'kind'=>'dynamic',  'height'=>40, 'tone'=>'#0f766e'],
        'tools'        => ['group'=>'catalog',  'partial'=>'partial/tools.twig',      'kind'=>'dynamic',  'height'=>34, 'tone'=>'#b45309'],
        'news'         => ['group'=>'catalog',  'partial'=>'partial/news.twig',       'kind'=>'dynamic',  'height'=>30, 'tone'=>'#b45309'],
        'clients'      => ['group'=>'proof',    'partial'=>'partial/clients.twig',    'kind'=>'dynamic',  'height'=>22, 'tone'=>'#0f766e'],
        'contact'      => ['group'=>'closing',  'partial'=>'partial/contact.twig',    'kind'=>'static',   'height'=>30, 'tone'=>'#475569'],
    ];

    private PageLayout $layout;

    public function __construct(?PageLayout $layout = null) {
        $this->layout = $layout ?? new PageLayout();
    }

    /** @return list<string> Allowlist unica (PageLayout). */
    public function ids(): array {
        return $this->layout->options(self::ROUTE);
    }

    /** @return list<string> */
    public function defaults(): array {
        return $this->layout->defaults(self::ROUTE, false);
    }

    /** Fail-closed: null = lista invalida; [] = override explicito vazio. */
    public function normalize(mixed $sections): ?array {
        return $this->layout->normalizeSections(self::ROUTE, $sections);
    }

    /** @return array<string,mixed> */
    public function meta(string $id): array {
        $m = self::META[$id] ?? ['group'=>'content','partial'=>'','kind'=>'static','height'=>24,'tone'=>'#475569'];
        return [
            'id'        => $id,
            'label_key' => 'section_label_' . str_replace('-', '_', $id),
            'group'     => $m['group'],
            'group_key' => 'text_section_group_' . $m['group'],
            'partial'   => $m['partial'],
            'kind'      => $m['kind'],
            'kind_key'  => 'text_section_kind_' . $m['kind'],
            'height'    => (int)$m['height'],
            'tone'      => (string)$m['tone'],
        ];
    }

    /**
     * Linhas do builder: ativas primeiro, NA ordem salva; inativas depois na
     * ordem do contrato. `$selected = null` => nenhuma ativa (tema legado).
     * @return list<array<string,mixed>>
     */
    public function view(?array $selected): array {
        $active = [];
        if (is_array($selected)) {
            $normalized = $this->normalize($selected);
            if (is_array($normalized)) $active = $normalized;
        }
        $rows = [];
        foreach ($active as $id) $rows[] = $this->meta((string)$id) + ['enabled'=>true];
        foreach ($this->ids() as $id) {
            if (!in_array($id, $active, true)) $rows[] = $this->meta($id) + ['enabled'=>false];
        }
        return $rows;
    }

    /** Lista efetiva para o Catalog (nunca ids fora da allowlist). */
    public function plan(array $selected): array {
        $normalized = $this->normalize($selected);
        return $normalized ?? [];
    }

    /** Fita deterministica para a previa C. Sem texto livre do usuario. */
    public function bars(?array $selected): array {
        if (!is_array($selected)) return [];
        $out = [];
        foreach ($this->plan($selected) as $id) {
            $m = $this->meta((string)$id);
            $out[] = [
                'id'        => $m['id'],
                'label_key' => $m['label_key'],
                'height'    => $m['height'],
                'tone'      => $m['tone'],
                'group'     => $m['group'],
            ];
        }
        return $out;
    }

    /** Normaliza a ordem vinda do formulario e completa ids ausentes. */
    public function formOrder(string $csv): array {
        $ids = $this->ids();
        $seen = [];
        $out = [];
        foreach (explode(',', $csv) as $piece) {
            $id = strtolower(trim((string)$piece));
            if ($id === '' || !in_array($id, $ids, true) || isset($seen[$id])) continue;
            $seen[$id] = true;
            $out[] = $id;
        }
        foreach ($ids as $id) if (!isset($seen[$id])) $out[] = $id;
        return $out;
    }
}
