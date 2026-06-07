<?php
declare(strict_types=1);

/**
 * SetParams - Р С—РЎР‚Р С•РЎРѓРЎвЂљР С• DTO (Р С”Р В»Р В°РЎРѓРЎРѓ-Р СР В°РЎРѓРЎРѓР С‘Р Р†). Р СњР С‘Р С”Р В°Р С”Р С‘РЎвЂ¦ Р СР ВµРЎвЂљР С•Р Т‘Р С•Р Р†.
 * TableRenderer РЎвЂЎР С‘РЎвЂљР В°Р ВµРЎвЂљ Р С‘Р В· Р Р…Р ВµР С–Р С• Р С‘ Р С–Р С•РЎвЂљР С•Р Р†Р С‘РЎвЂљ Р Р†РЎРѓРЎвЂ Р Р† render().
 */

final class SetParams
{
    use SogerienClassHelp;

    public function __construct(array $params = [])
    {
        foreach ($params as $key => $value) {
            if (!property_exists($this, (string)$key)) {
                continue;
            }

            $this->{$key} = $value;
        }
    }

    /** @var array<int,array<string,mixed>> */
    public array $data = [];

    /** @var array<string,string> */
    public array $headers = [];

    /** @var array<int,string> columns: [] Р С‘Р В»Р С‘ ['*'] - Р В°Р Р†РЎвЂљР С• Р С‘Р В· data */
    /**
     * @var array<int|string,mixed> columns:
     * ['*'] | ['email', 'login'] | [['name' => 'email', 'header' => 'Email', 'width' => '220px', 'nowrap' => true]]
     */
    public array $columns = [];

    /** @var array{before?:string,after?:string} */
    public array $wrap = ['before' => '', 'after' => ''];

    public int $perPage = 100;

    /** @var array<int,string> */
    public array $searchCols = [];

    /** Р СџР С•Р С”Р В°Р В·РЎвЂ№Р Р†Р В°РЎвЂљРЎРЉ Р С—Р С•Р Т‘Р С—Р С‘РЎРѓРЎРЉ "Р СџР С•Р В»РЎРЏ: ..." Р С—Р С•Р Т‘ Р С—Р С•Р С‘РЎРѓР С”Р С•Р С */
    public bool $searchShowFields = false;

    /** Р РЃР С‘РЎР‚Р С‘Р Р…Р В° Р С—Р С•Р В»РЎРЏ Р С—Р С•Р С‘РЎРѓР С”Р В°, Р Р…Р В°Р С—РЎР‚Р С‘Р СР ВµРЎР‚ 130px */
    public string $searchInputWidth = '320px';

    /** Р СџР С•Р С”Р В°Р В·РЎвЂ№Р Р†Р В°РЎвЂљРЎРЉ "Р С™Р С•Р В»Р С•Р Р…Р С”Р С‘" Р С‘ "Р РЋР С”Р С‘Р Р…РЎС“РЎвЂљР С‘ РЎвЂћРЎвЂ“Р В»РЎРЉРЎвЂљРЎР‚Р С‘" Р Р†Р Р…РЎС“РЎвЂљРЎР‚Р С‘ Р С•Р В±РЎвЂ°Р ВµР С–Р С• Р В±Р В»Р С•Р С”Р В° "Р В¤РЎвЂ“Р В»РЎРЉРЎвЂљРЎР‚Р С‘" */
    public bool $filtersIncludeColumnsReset = true;

    public string $gridId = 'grid';

    /** @var array<string,callable> formatter(col)-> fn($v,$row,$col):string */
    public array $formatters = [];

    /** @var array<int,array<string,mixed>> */
    public array $facets = [];

    /** @var array<int,array<string,mixed>> */
    public array $actions = [];

    /** @var array<string,mixed> */
    public array $context = [];

    /** @var array<int,string> query-параметры страницы, которые нужно убрать при reset этой таблицы */
    public array $reset_query_params = [];

    /** @var array<int,string> */
    public array $columnsOrder = [];

    /** @var array<int,string> */
    public array $preferredOrder = [];

    /** @var array<int,string> */
    public array $alwaysKeep = ['id', 'actions'];

    public bool $autoHideEmptyCols = true;

    /**
     * Р СћР С‘Р С—РЎвЂ№ РЎРЏРЎвЂЎР ВµР ВµР С” Р С—Р С• Р С”Р С•Р В»Р С•Р Р…Р С”Р В°Р С: multiselect_search Р С‘ РЎвЂљ.Р Т‘.
     * multiselect_search: type, options, value_key, label_key, row_primary_key, save_param (Р С‘Р СРЎРЏ Р С—Р С•Р В»РЎРЏ Р С—РЎР‚Р С‘ POST, Р Р…Р В°Р С—РЎР‚. users_id_str)
     * @var array<string,array{type:string,options?:array<int,array>,value_key?:string,label_key?:string,row_primary_key?:string,save_param?:string}>
     */
    public array $column_cell_types = [];

    /**
     * Р СњР В°РЎРѓРЎвЂљРЎР‚Р С•Р в„–Р С”Р С‘ Р С•РЎвЂљР С•Р В±РЎР‚Р В°Р В¶Р ВµР Р…Р С‘РЎРЏ Р С”Р С•Р В»Р С•Р Р…Р С•Р С”.
     * width: Р Р…Р В°Р С—РЎР‚Р С‘Р СР ВµРЎР‚ 220px, 30%, 16rem
     * ellipsis: Р С•Р В±РЎР‚Р ВµР В·Р В°РЎвЂљРЎРЉ РЎРѓР С•Р Т‘Р ВµРЎР‚Р В¶Р С‘Р СР С•Р Вµ РЎРЏРЎвЂЎР ВµР в„–Р С”Р С‘ Р С—Р С• РЎв‚¬Р С‘РЎР‚Р С‘Р Р…Р Вµ Р С”Р С•Р В»Р С•Р Р…Р С”Р С‘
     * @var array<string,array{width?:string,ellipsis?:bool|string|int}>
     */
    /** @var array<string,array{width?:string,fixed_width?:string,ellipsis?:bool|string|int,nowrap?:bool|string|int,no_wrap?:bool|string|int,nowrapper?:bool|string|int}> */
    public array $column_view = [];

    /** id РЎРЊР В»Р ВµР СР ВµР Р…РЎвЂљР В° Р Т‘Р В»РЎРЏ Р Р†РЎвЂ№Р Р†Р С•Р Т‘Р В° РЎРѓР С•Р С•Р В±РЎвЂ°Р ВµР Р…Р С‘РЎРЏ Р С—Р С•РЎРѓР В»Р Вµ РЎРѓР С•РЎвЂ¦РЎР‚Р В°Р Р…Р ВµР Р…Р С‘РЎРЏ Р СРЎС“Р В»РЎРЉРЎвЂљР С‘РЎРѓР ВµР В»Р ВµР С”РЎвЂљР В° (Р ВµРЎРѓР В»Р С‘ Р В·Р В°Р Т‘Р В°Р Р… РІР‚вЂќ table_renderer.js РЎв‚¬Р В»РЎвЂРЎвЂљ POST Р С—РЎР‚Р С‘ change) */
    public string $multiselect_save_message_id = '';
}

final class TableRenderer
{
    use SogerienClassHelp;

    public SetParams $set_params;

    /** @var array<int,string> */
    private array $columnsFinal = [];

    /** @var array<string,string> */
    private array $headersFinal = [];

    private bool $prepared = false;

    public function __construct(?SetParams $set_params = null)
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->set_params = $set_params ?? new SetParams();
}

    public static function h(?string $v): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return   Sogerien::Debager()->capture_return(htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), __CLASS__, __FUNCTION__);
}

    private function prepare(): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if ($this->prepared) do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
        $this->prepared = true;

        $p = $this->set_params;

        $data = array_values($p->data);
        $headers = $p->headers;
        $cols = $p->columns;
        [$cols, $headers, $p->column_view] = $this->normalizeColumnsConfig($cols, $headers, $p->column_view);

        $gridId = preg_replace('~[^a-z0-9_\-]~i', '_', $p->gridId) ?: 'grid';
        $p->gridId = $gridId; // Р Р…Р С•РЎР‚Р СР В°Р В»Р С‘Р В·РЎС“Р ВµР С Р С•Р В±РЎР‚Р В°РЎвЂљР Р…Р С•, РЎвЂЎРЎвЂљР С•Р В±РЎвЂ№ РЎРѓР С•Р Р†Р С—Р В°Р В»Р С‘ id Р Р† html

        // Р В°Р Р†РЎвЂљР С•-Р С”Р С•Р В»Р С•Р Р…Р С”Р С‘
        if (empty($cols) || (count($cols) === 1 && ($cols[0] ?? '') === '*')) {
            $cols = $this->collectAllColumns($data);
            $cols = $this->orderColumns($cols, $p->preferredOrder);
        }

        // Р С—Р С•РЎР‚РЎРЏР Т‘Р С•Р С”
        $primary = !empty($p->columnsOrder) ? $p->columnsOrder : $p->preferredOrder;
        $cols = $this->orderColumns($cols, $primary);

        // Р В°Р Р†РЎвЂљР С•-РЎРѓР С”РЎР‚РЎвЂ№РЎвЂљР С‘Р Вµ Р С—РЎС“РЎРѓРЎвЂљРЎвЂ№РЎвЂ¦
        if ($p->autoHideEmptyCols) {
            $cols = array_values(array_filter($cols, function (string $c) use ($p, $data) {
                if (in_array($c, $p->alwaysKeep, true)) return Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
                return Sogerien::Debager()->capture_return($this->columnHasAnyValue($data, $c), __CLASS__, __FUNCTION__);
            }));
        }

        // Р С—Р С•Р Т‘Р С—Р С‘РЎРѓР С‘
        foreach ($cols as $c) {
            if (!isset($headers[$c])) $headers[$c] = $this->prettyHeader($c);
        }

        $this->columnsFinal = $cols;
        $this->headersFinal = $headers;

        // Р Т‘Р В°Р Р…Р Р…РЎвЂ№Р Вµ Р С•Р В±РЎР‚Р В°РЎвЂљР Р…Р С• Р Р…Р Вµ Р С—Р С‘РЎв‚¬РЎС“ - РЎР‚Р ВµР Р…Р Т‘Р ВµРЎР‚ Р В±Р ВµРЎР‚Р ВµРЎвЂљ Р С‘Р В· set_params
    }

    private function collectAllColumns(array $rows): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $set = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            foreach ($row as $k => $_) $set[(string)$k] = true;
        }
        return Sogerien::Debager()->capture_return(array_keys($set), __CLASS__, __FUNCTION__);
    }

    private function orderColumns(array $cols, array $primaryOrder = []): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $cols = array_values(array_unique($cols));

        $pos = array_search('actions', $cols, true);
        $hasActions = $pos !== false;
        if ($hasActions) {
            unset($cols[$pos]);
            $cols = array_values($cols);
        }

        $in = array_flip($cols);
        $seq = [];

        $pushIfExists = function (array $order) use (&$seq, $in) {
            foreach ($order as $k) {
                $k = (string)$k;
                if (isset($in[$k]) && !in_array($k, $seq, true)) $seq[] = $k;
            }
        };

        if (!empty($primaryOrder)) $pushIfExists($primaryOrder);

        $rest = array_values(array_diff($cols, $seq));
        natcasesort($rest);
        $rest = array_values($rest);

        $seq = array_merge($seq, $rest);
        if ($hasActions) $seq[] = 'actions';

        return Sogerien::Debager()->capture_return($seq, __CLASS__, __FUNCTION__);
    }

    private function columnHasAnyValue(array $data, string $col): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } foreach ($data as $row) {
            if (!is_array($row)) continue;
            if (array_key_exists($col, $row) && $this->isMeaningful($row[$col])) return Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
    }

    private function isMeaningful($v): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } if ($v === null) return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        if (is_string($v)) return Sogerien::Debager()->capture_return(trim($v) !== '', __CLASS__, __FUNCTION__);
        if (is_numeric($v)) return Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
        if (is_bool($v)) return Sogerien::Debager()->capture_return($v === true, __CLASS__, __FUNCTION__);

        if (is_array($v)) {
            foreach ($v as $item) if ($this->isMeaningful($item)) return Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
            return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        if (is_object($v)) return Sogerien::Debager()->capture_return($this->isMeaningful((array)$v), __CLASS__, __FUNCTION__);

        return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
    }
    private function prettyHeader(string $key): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $map = [
            'id' => Sogerien::Lang()->get('common.id'),
            'email' => Sogerien::Lang()->get('common.email'),
            'phone' => Sogerien::Lang()->get('common.phone'),
            'phone_number' => Sogerien::Lang()->get('common.phone'),
            'status' => Sogerien::Lang()->get('common.status'),
            'actions' => Sogerien::Lang()->get('common.actions'),
            'login' => Sogerien::Lang()->get('common.login'),
            'name' => Sogerien::Lang()->get('common.name'),
            'role' => Sogerien::Lang()->get('roles.role'),
            'roles' => Sogerien::Lang()->get('roles.role'),
            'country' => Sogerien::Lang()->get('common.country'),
            'type' => Sogerien::Lang()->get('common.type'),
        ];
        if (isset($map[$key])) return Sogerien::Debager()->capture_return($map[$key], __CLASS__, __FUNCTION__);

        $pretty = preg_replace('~[_\-]+~', ' ', $key);
        return Sogerien::Debager()->capture_return(mb_convert_case((string)$pretty, MB_CASE_TITLE, 'UTF-8'), __CLASS__, __FUNCTION__);
    }
    private function toScalarString($v): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } if ($v === null) return Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        if (is_bool($v)) return Sogerien::Debager()->capture_return($v ? '1' : '0', __CLASS__, __FUNCTION__);
        if (is_int($v) || is_float($v) || is_string($v)) return Sogerien::Debager()->capture_return((string)$v, __CLASS__, __FUNCTION__);
        return Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
    }

    private function normalizeCssSize($value): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $v = strtolower(trim($this->toScalarString($value)));
        if ($v === '') return Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        if ($v === 'auto') return Sogerien::Debager()->capture_return($v, __CLASS__, __FUNCTION__);
        if (preg_match('/^\d+(?:\.\d+)?(px|%|rem|em|vw|vh|ch)$/', $v) === 1) return Sogerien::Debager()->capture_return($v, __CLASS__, __FUNCTION__);
        return Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<int|string,mixed> $columns
     * @param array<string,string> $headers
     * @param array<string,array<string,mixed>> $columnView
     * @return array{0:array<int,string>,1:array<string,string>,2:array<string,array<string,mixed>>}
     */
    private function normalizeColumnsConfig(array $columns, array $headers, array $columnView): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $normalized = [];

        foreach ($columns as $key => $item) {
            if (is_string($item)) {
                $normalized[] = $item;
                continue;
            }

            if (!is_array($item)) {
                if (is_string($key)) {
                    $normalized[] = $key;
                }
                continue;
            }

            $col = trim((string)($item['name'] ?? $item['column'] ?? (is_string($key) ? $key : '')));
            if ($col === '') {
                continue;
            }

            $normalized[] = $col;

            $header = $item['header'] ?? $item['title'] ?? $item['label'] ?? null;
            if (is_scalar($header) && trim((string)$header) !== '') {
                $headers[$col] = (string)$header;
            }

            $view = $columnView[$col] ?? [];
            if (!is_array($view)) {
                $view = [];
            }

            $width = $this->normalizeCssSize($item['width'] ?? ($item['fixed_width'] ?? ''));
            if ($width !== '') {
                $view['width'] = $width;
            }

            foreach (['ellipsis', 'nowrap', 'no_wrap', 'nowrapper'] as $flag) {
                if (array_key_exists($flag, $item)) {
                    $view[$flag] = $item[$flag];
                }
            }

            if ($view !== []) {
                $columnView[$col] = $view;
            }
        }

        return Sogerien::Debager()->capture_return([array_values($normalized), $headers, $columnView], __CLASS__, __FUNCTION__);
    }

    private function isTruthyFlag($raw): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        if (is_bool($raw)) return Sogerien::Debager()->capture_return($raw, __CLASS__, __FUNCTION__);
        if (is_int($raw)) return Sogerien::Debager()->capture_return($raw !== 0, __CLASS__, __FUNCTION__);
        if (is_string($raw)) {
            $v = strtolower(trim($raw));
            return Sogerien::Debager()->capture_return(in_array($v, ['1', 'true', 'yes', 'on'], true), __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
    }

    private function columnWidth(string $col): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $cfg = $this->set_params->column_view[$col] ?? null;
        if (!is_array($cfg)) return Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        return Sogerien::Debager()->capture_return($this->normalizeCssSize($cfg['width'] ?? ($cfg['fixed_width'] ?? '')), __CLASS__, __FUNCTION__);
    }

    private function columnEllipsis(string $col): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $cfg = $this->set_params->column_view[$col] ?? null;
        if (!is_array($cfg)) return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        return Sogerien::Debager()->capture_return($this->isTruthyFlag($cfg['ellipsis'] ?? false), __CLASS__, __FUNCTION__);
    }

    private function columnNoWrap(string $col): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $cfg = $this->set_params->column_view[$col] ?? null;
        if (!is_array($cfg)) return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);

        foreach (['nowrap', 'no_wrap', 'nowrapper'] as $flag) {
            if ($this->isTruthyFlag($cfg[$flag] ?? false)) {
                return Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
            }
        }

        return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
    }

    private function columnVisibleByDefault(string $col): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $cfg = $this->set_params->column_view[$col] ?? null;
        if (!is_array($cfg)) return Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);

        if (array_key_exists('visible', $cfg)) {
            return Sogerien::Debager()->capture_return($this->isTruthyFlag($cfg['visible']), __CLASS__, __FUNCTION__);
        }
        foreach (['hidden', 'is_hidden'] as $flag) {
            if (array_key_exists($flag, $cfg) && $this->isTruthyFlag($cfg[$flag])) {
                return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
            }
        }

        return Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
    }

    private function styleAttr(array $styles): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } if ($styles === []) return Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        return Sogerien::Debager()->capture_return(' style="' . self::h(implode(';', $styles)) . '"', __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<int|string,mixed> $value
     */
    private function jsonAttr(array $value): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($json)) {
            $json = '[]';
        }
        return Sogerien::Debager()->capture_return($json, __CLASS__, __FUNCTION__);
    }

    protected function tpl(string $str, array $row): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } if ($str === '' || ($str[0] ?? '') === '#') return Sogerien::Debager()->capture_return($str, __CLASS__, __FUNCTION__);
        if (!preg_match_all('~\{([a-zA-Z0-9_\-.]+)}~u', $str, $m)) return Sogerien::Debager()->capture_return($str, __CLASS__, __FUNCTION__);

        $need = array_unique($m[1]);
        $map = [];
        foreach ($need as $k) {
            $val = array_key_exists($k, $row) ? $row[$k]
                : (array_key_exists($k, $this->set_params->context) ? $this->set_params->context[$k] : null);
            $map['{' . $k . '}'] = $this->toScalarString($val);
        }
        return Sogerien::Debager()->capture_return(strtr($str, $map), __CLASS__, __FUNCTION__);
    }

    protected function renderCell(string $col, array $row): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } if ($col === 'actions') return Sogerien::Debager()->capture_return($this->renderActions($row), __CLASS__, __FUNCTION__);

        $v = $row[$col] ?? '';

        $cellType = $this->set_params->column_cell_types[$col] ?? null;
        if (is_array($cellType) && ($cellType['type'] ?? '') === 'multiselect_search') {
            return   Sogerien::Debager()->capture_return($this->renderCellMultiselectSearch($col, $row, $v, $cellType), __CLASS__, __FUNCTION__);
        }

        if (isset($this->set_params->formatters[$col]) && is_callable($this->set_params->formatters[$col])) {
            try {
                return   Sogerien::Debager()->capture_return((string)call_user_func($this->set_params->formatters[$col], $v, $row, $col), __CLASS__, __FUNCTION__);
            } catch (\Throwable) {
                // fallback Р Р…Р С‘Р В¶Р Вµ
            }
        }

        if (is_array($v) || is_object($v)) {
            $dump = print_r($v, true);
            return   Sogerien::Debager()->capture_return('<pre class="mono">' . self::h($dump) . '</pre>', __CLASS__, __FUNCTION__);
        }

        return   Sogerien::Debager()->capture_return(self::h((string)$v), __CLASS__, __FUNCTION__);
}

    /**
     * Р Р‡РЎвЂЎР ВµР в„–Р С”Р В°: Р СРЎС“Р В»РЎРЉРЎвЂљР С‘РЎРѓР ВµР В»Р ВµР С”РЎвЂљ РЎРѓ Р С—Р С•Р С‘РЎРѓР С”Р С•Р С (Select2).
     *
     * @param array{options?:array<int,array>,value_key?:string,label_key?:string,row_primary_key?:string,save_param?:string} $cellType
     */
    protected function renderCellMultiselectSearch(string $col, array $row, $v, array $cellType): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $options = $cellType['options'] ?? [];
        $valueKey = $cellType['value_key'] ?? 'id';
        $labelKey = $cellType['label_key'] ?? 'name';
        $rowPk = $cellType['row_primary_key'] ?? 'id';
        $saveParam = $cellType['save_param'] ?? 'users_id_str';
        $rowId = (string)($row[$rowPk] ?? '');
        $name = 'tr_' . preg_replace('/[^a-z0-9_]/i', '_', $col) . '_' . preg_replace('/[^a-z0-9_.-]/i', '_', $rowId);

        $selected = [];
        if (is_array($v)) {
            $selected = array_map('strval', array_keys($v));
        } elseif (is_string($v) && $v !== '') {
            $selected = array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/u', $v, -1, PREG_SPLIT_NO_EMPTY))));
        }

        $html = '<select multiple class="tr-select2-multiselect form-select form-select-sm" name="' . self::h($name) . '" '
            . 'data-col="' . self::h($col) . '" data-row-id="' . self::h($rowId) . '" data-value-key="' . self::h($valueKey) . '" data-save-param="' . self::h($saveParam) . '">';
        foreach ($options as $opt) {
            if (!is_array($opt)) {
                continue;
            }
            $optVal = (string)($opt[$valueKey] ?? '');
            $optLabel = (string)($opt[$labelKey] ?? $optVal);
            $sel = in_array($optVal, $selected, true) ? ' selected' : '';
            $html .= '<option value="' . self::h($optVal) . '"' . $sel . '>' . self::h($optLabel) . '</option>';
        }
        $html .= '</select>';
        return Sogerien::Debager()->capture_return($html, __CLASS__, __FUNCTION__);
    }

    protected function renderActions(array $row): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $btns = [];
        foreach ($this->set_params->actions as $act) {
            $enabled = true;
            if (isset($act['enabled_when']) && is_callable($act['enabled_when'])) {
                try { $enabled = (bool)$act['enabled_when']($row); }
                catch (\Throwable) { $enabled = false; }
}
            if (!$enabled) continue;

            $name = (string)($act['name'] ?? 'action');
            $title = (string)($act['title'] ?? ucfirst($name));
            $method = strtoupper((string)($act['method'] ?? 'GET'));
            $kind = (string)($act['kind'] ?? 'normal');

            $kindClass = match ($kind) {
                'primary' => 'btn btn-sm btn-primary',
                'secondary' => 'btn btn-sm btn-outline-secondary',
                'danger' => 'btn btn-sm btn-danger',
                'warning' => 'btn btn-sm btn-warning',
                'success' => 'btn btn-sm btn-success',
                'success_outline' => 'btn btn-sm btn-outline-success',
                default => 'btn btn-sm btn-outline-primary'
            };

            $hasDialog = isset($act['dialog']) && is_array($act['dialog']);
            $attrs = [];

            if ($method === 'GET' && isset($act['href'])) {
                $href = $this->tpl((string)$act['href'], $row);
                if ($hasDialog) {
                    $attrs[] = 'href="javascript:void(0)"';
                    $attrs[] = 'data-action-type="GET"';
                    $attrs[] = 'data-href="' . self::h($href) . '"';
                } else {
                    $attrs[] = 'href="' . self::h($href) . '"';
                }
            } else {
                $endpoint = (string)($act['endpoint'] ?? '');
                $endpoint = $this->tpl($endpoint, $row);

                $attrs[] = 'href="javascript:void(0)"';
                $attrs[] = 'data-action-type="POST"';
                $attrs[] = 'data-endpoint="' . self::h($endpoint) . '"';

                $post = (array)($act['post'] ?? []);
                $postResolved = array_map(fn($v) => $this->tpl((string)$v, $row), $post);
                $attrs[] = "data-post='" . self::h(json_encode($postResolved, JSON_UNESCAPED_UNICODE)) . "'";
            }

            if ($hasDialog) {
                $d = $act['dialog'];
                $dTitle = $this->tpl((string)($d['title'] ?? 'Р СџРЎвЂ“Р Т‘РЎвЂљР Р†Р ВµРЎР‚Р Т‘Р В¶Р ВµР Р…Р Р…РЎРЏ'), $row);
                $dMsg = $this->tpl((string)($d['message'] ?? ''), $row);
                $dButtons = (array)($d['buttons'] ?? []);

                $attrs[] = 'data-has-dialog="1"';
                $attrs[] = 'data-dialog-title="' . self::h($dTitle) . '"';
                $attrs[] = 'data-dialog-msg="' . self::h($dMsg) . '"';
                $attrs[] = 'data-dialog-buttons="' . self::h(json_encode($dButtons, JSON_UNESCAPED_UNICODE)) . '"';
            }

            $attrs[] = 'data-action-name="' . self::h($name) . '"';
            $attrs[] = 'class="' . $kindClass . ' tr-action"';

            $btns[] = '<a ' . implode(' ', $attrs) . '>' . self::h($title) . '</a>';
        }

        return  Sogerien::Debager()->capture_return($btns ? implode(' ', $btns) : '', __CLASS__, __FUNCTION__);
    }

    protected function facetValues(string $col): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $set = [];
        foreach ($this->set_params->data as $row) {
            if (!is_array($row)) continue;
            $v = $this->toScalarString($row[$col] ?? null);
            if ($v !== '') $set[$v] = $v;
        }
        $vals = array_values($set);
        sort($vals, SORT_NATURAL | SORT_FLAG_CASE);
        return  Sogerien::Debager()->capture_return($vals, __CLASS__, __FUNCTION__);
    }

    /**
     * @return array<int,float|int>
     */
    protected function facetNumericValues(string $col): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $set = [];
        foreach ($this->set_params->data as $row) {
            if (!is_array($row)) continue;
            $raw = $row[$col] ?? null;
            if ($raw === null || $raw === '') continue;
            $n = is_numeric((string)$raw) ? (float)$raw : null;
            if ($n !== null) $set[(string)$n] = $n;
        }
        $vals = array_values($set);
        sort($vals, SORT_NUMERIC);
        return Sogerien::Debager()->capture_return(array_values(array_unique($vals)), __CLASS__, __FUNCTION__);
    }

    /**
     * @return array<int,string> ISO Y-m-d
     */
    protected function facetDateValues(string $col): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $set = [];
        foreach ($this->set_params->data as $row) {
            if (!is_array($row)) continue;
            $raw = $row[$col] ?? null;
            if ($raw === null || $raw === '') continue;
            $d = $this->normalizeDateForRange($raw);
            if ($d !== '') $set[$d] = $d;
        }
        $vals = array_values($set);
        sort($vals, SORT_STRING);
        return Sogerien::Debager()->capture_return($vals, __CLASS__, __FUNCTION__);
    }

    private function normalizeDateForRange($raw): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $s = trim((string)$raw);
        if ($s === '') return Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) return Sogerien::Debager()->capture_return(substr($s, 0, 10), __CLASS__, __FUNCTION__);
        $ts = strtotime($s);
        if ($ts !== false) return Sogerien::Debager()->capture_return(date('Y-m-d', $ts), __CLASS__, __FUNCTION__);
        return Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
    }

    protected function normalizeFacetValues(array $values): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $set = [];
        foreach ($values as $value) {
            $v = $this->toScalarString($value);
            if ($v !== '') $set[$v] = $v;
        }
        $vals = array_values($set);
        sort($vals, SORT_NATURAL | SORT_FLAG_CASE);
        return Sogerien::Debager()->capture_return($vals, __CLASS__, __FUNCTION__);
    }

    public function render(): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $this->prepare();

        $p = $this->set_params;

        $gid = self::h($p->gridId);
        $perPage = max(1, (int)$p->perPage);

        $wrapBefore = (string)($p->wrap['before'] ?? '');
        $wrapAfter  = (string)($p->wrap['after'] ?? '');

        if ($wrapBefore !== '') echo $wrapBefore;

        $csrf = (string)($_SESSION['csrf'] ?? '');
        $hasFilters = !empty($p->searchCols) || !empty($p->facets);
        $toolsInsideFilters = $hasFilters && $p->filtersIncludeColumnsReset;
        $resetQueryParams = array_values(array_unique(array_filter(array_map(
            fn($v): string => trim((string)$v),
            $p->reset_query_params
        ), static fn(string $v): bool => $v !== '')));
        $tt = static fn(string $key, string $fallback = ''): string => Sogerien::Lang()->get($key) ?: $fallback;
        $txFilters = self::h($tt('table.filters', 'Filters'));
        $txSearch = self::h($tt('table.search', 'Search'));
        $txSearchPlaceholder = self::h($tt('table.search_placeholder', 'Type text...'));
        $txFields = self::h($tt('table.fields', 'Fields'));
        $txAll = self::h($tt('table.all', 'All'));
        $txClear = self::h($tt('table.clear', 'Clear'));
        $txColumns = self::h($tt('table.columns', 'Columns'));
        $txResetFilters = self::h($tt('table.reset_filters', 'Reset filters'));
        $txModalTitle = self::h($tt('table.modal_title', 'Confirmation'));
        $txClose = self::h($tt('common.close', 'Close'));

        $facetsMain = [];
        $facetsSide = [];
        foreach ($p->facets as $facet) {
            $slot = strtolower((string)($facet['slot'] ?? ($facet['placement'] ?? 'main')));
            if ($slot === 'side') {
                $facetsSide[] = $facet;
            } else {
                $facetsMain[] = $facet;
            }
        }

        $renderRangeNumberDropdown = static function (string $facetId, string $side, array $values) use ($txAll, $txClear): string {
            ob_start();
            ?>
            <div class="dropdown tr-range-dd" data-side="<?= self::h($side) ?>">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle tr-dd-toggle tr-range-dd-toggle"
                        type="button"
                        id="<?= self::h($facetId) ?>__<?= self::h($side) ?>_btn"
                        aria-expanded="false">
                    <span class="tr-range-dd-label"><?= $txAll ?></span>
                </button>

                <div class="dropdown-menu p-2 tr-range-dd-menu"
                     aria-labelledby="<?= self::h($facetId) ?>__<?= self::h($side) ?>_btn"
                     style="min-width:220px; max-height:320px; overflow:auto;">
                    <div class="d-flex gap-2 mb-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary tr-range-all"><?= $txAll ?></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary tr-range-clear"><?= $txClear ?></button>
                    </div>

                    <div class="d-flex flex-column gap-1 tr-range-options">
                        <?php foreach ($values as $v): ?>
                            <?php $value = (string)$v; ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary tr-range-option" data-value="<?= self::h($value) ?>">
                                <?= self::h($value) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php
            return (string)ob_get_clean();
        };

        $renderFacet = function (array $fx) use ($gid, $tt, $txAll, $txClear, $renderRangeNumberDropdown): string {
            $title = (string)($fx['title'] ?? ($fx['column'] ?? ''));
            $col   = (string)($fx['column'] ?? '');
            $type  = strtolower((string)($fx['type'] ?? 'buttons'));
            $match = strtolower((string)($fx['match'] ?? 'exact'));
            $slot = strtolower((string)($fx['slot'] ?? ($fx['placement'] ?? 'main')));
            $default = $fx['default'] ?? ($fx['selected'] ?? null);
            $defaultAttr = is_array($default) || is_scalar($default) ? $this->jsonAttr($default) : '';
            $dropdownSearch = !empty($fx['search']);

            if ($col === '' || !in_array($col, $this->columnsFinal, true)) {
                return '';
            }
            if (!in_array($match, ['exact', 'csv_token', 'contains'], true)) {
                $match = 'exact';
            }
            if (!in_array($slot, ['main', 'side'], true)) {
                $slot = 'main';
            }

            $values = (isset($fx['values']) && is_array($fx['values']))
                ? $this->normalizeFacetValues($fx['values'])
                : $this->facetValues($col);
            $facetId = 'facet-' . $gid . '-' . $col;
            $txFrom = self::h($tt('table.range_from', 'From'));
            $txTo = self::h($tt('table.range_to', 'To'));
            $facetClasses = [
                'tr-facet',
                'tr-facet--' . $type,
                'tr-facet--slot-' . $slot,
                'tr-facet--match-' . $match,
            ];
            if ($type === 'range_number') {
                $facetClasses[] = 'tr-facet--wide';
            }
            $extraClassRaw = trim((string)($fx['class'] ?? ($fx['classes'] ?? '')));
            if ($extraClassRaw !== '') {
                $extraClasses = preg_split('/\s+/', $extraClassRaw) ?: [];
                foreach ($extraClasses as $extraClass) {
                    if ($extraClass !== '' && preg_match('/^[a-z0-9_-]+$/i', $extraClass)) {
                        $facetClasses[] = $extraClass;
                    }
                }
            }
            $facetClassAttr = implode(' ', array_values(array_unique($facetClasses)));

            ob_start();
            ?>
                <div class="<?= self::h($facetClassAttr) ?>"
                     data-col="<?= self::h($col) ?>"
                     data-type="<?= self::h($type) ?>"
                     data-match="<?= self::h($match) ?>"
                     data-default="<?= self::h($defaultAttr) ?>">
                <div class="small text-muted"><?= self::h($title) ?></div>

                <?php if ($type === 'range_number'):
                    $numValues = isset($fx['values']) && is_array($fx['values'])
                        ? array_values(array_filter(array_map(static fn($v) => is_numeric((string)$v) ? (float)$v : null, $fx['values'])))
                        : $this->facetNumericValues($col);
                    if (!empty($numValues)) {
                        sort($numValues, SORT_NUMERIC);
                        $numValues = array_values(array_unique($numValues));
                    }
                    ?>
                    <div class="tr-facet-range-number">
                        <div class="tr-range-group">
                            <label class="tr-range-label"><?= $txFrom ?></label>
                            <?= $renderRangeNumberDropdown($facetId, 'from', $numValues) ?>
                        </div>
                        <div class="tr-range-group">
                            <label class="tr-range-label"><?= $txTo ?></label>
                            <?= $renderRangeNumberDropdown($facetId, 'to', $numValues) ?>
                        </div>
                    </div>
                <?php elseif ($type === 'range_date'):
                    $dateValues = isset($fx['values']) && is_array($fx['values'])
                        ? $this->normalizeFacetValues(array_map(fn($v) => $this->normalizeDateForRange($v), $fx['values']))
                        : $this->facetDateValues($col) ?>
                    <div class="tr-facet-range-date">
                        <div class="tr-range-group">
                            <label class="tr-range-label"><?= $txFrom ?></label>
                            <div class="tr-range-input-wrap">
                                <input type="date" class="form-control form-control-sm tr-range-from" placeholder="<?= $txFrom ?>">
                                <?php if (!empty($dateValues)): ?>
                                <select class="form-select form-select-sm tr-range-from-select" aria-label="<?= $txFrom ?>">
                                    <option value="">-</option>
                                    <?php foreach ($dateValues as $dv): ?>
                                    <option value="<?= self::h($dv) ?>"><?= self::h($dv) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="tr-range-group">
                            <label class="tr-range-label"><?= $txTo ?></label>
                            <div class="tr-range-input-wrap">
                                <input type="date" class="form-control form-control-sm tr-range-to" placeholder="<?= $txTo ?>">
                                <?php if (!empty($dateValues)): ?>
                                <select class="form-select form-select-sm tr-range-to-select" aria-label="<?= $txTo ?>">
                                    <option value="">-</option>
                                    <?php foreach ($dateValues as $dv): ?>
                                    <option value="<?= self::h($dv) ?>"><?= self::h($dv) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php elseif ($type === 'dropdown_multi'): ?>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle tr-dd-toggle"
                                type="button"
                                id="<?= self::h($facetId) ?>__btn"
                                aria-expanded="false">
                            <span class="tr-dd-label"><?= $txAll ?></span>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis tr-dd-count d-none">0</span>
                        </button>

                        <div class="dropdown-menu p-2"
                             aria-labelledby="<?= self::h($facetId) ?>__btn"
                             style="min-width:280px; max-height:320px; overflow:auto;">
                            <div class="d-flex gap-2 mb-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary tr-dd-all"><?= $txAll ?></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary tr-dd-clear"><?= $txClear ?></button>
                            </div>
                            <?php if ($dropdownSearch): ?>
                            <div class="mb-2">
                                <input type="text" class="form-control form-control-sm tr-dd-search" placeholder="<?= self::h($tt('table.search', 'Search')) ?>">
                            </div>
                            <?php endif; ?>

                            <?php foreach ($values as $v):
                                $vid = $facetId . '__' . md5((string)$v);
                                ?>
                                <div class="form-check">
                                    <input class="form-check-input tr-dd-chk"
                                           type="checkbox"
                                           id="<?= self::h($vid) ?>"
                                           value="<?= self::h($v) ?>">
                                    <label class="form-check-label" for="<?= self::h($vid) ?>">
                                        <?= self::h($v) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="btn-group btn-group-sm" role="group" aria-label="facet-<?= self::h($col) ?>">
                        <button type="button" class="btn btn-outline-secondary active" data-value=""><?= $txAll ?></button>
                        <?php foreach ($values as $v): ?>
                            <button type="button" class="btn btn-outline-secondary" data-value="<?= self::h($v) ?>">
                                <?= self::h($v) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php
            return (string)ob_get_clean();
        };
        ?>
        <div class="tr-grid"
             data-tr-gid="<?= $gid ?>"
             data-tr-per-page="<?= $perPage ?>"
             data-tr-csrf="<?= self::h($csrf) ?>"
             data-tr-multiselect-msg="<?= self::h($p->multiselect_save_message_id ?? '') ?>"
             data-tr-reset-query-params="<?= self::h($this->jsonAttr($resetQueryParams)) ?>"></div>

        <div id="<?= $gid ?>__panel" class="tr-panel">
            <div class="tr-panel-row">
                <?php if ($hasFilters): ?>
                    <div class="tr-filters-unified">
                        <div class="form-label mb-1"><?= $txFilters ?></div>
                        <div class="tr-filters-unified-body">
                            <div id="<?= $gid ?>__facets" class="tr-facets">
                            <?php if (!empty($p->searchCols)): ?>
                                <div class="tr-facet tr-facet-search">
                                    <?php
                                    $searchWidth = $this->normalizeCssSize($p->searchInputWidth ?? '');
                                    $searchInputStyles = [];
                                    if ($searchWidth !== '') {
                                        $searchInputStyles[] = 'width:' . $searchWidth;
                                        $searchInputStyles[] = 'max-width:100%';
                                    }
                                    ?>
                                    <div class="tr-search-row">
                                        <label class="tr-search-label" for="<?= $gid ?>__search"><?= $txSearch ?></label>
                                        <div class="input-group input-group-sm tr-search-group"<?= $this->styleAttr($searchInputStyles) ?>>
                                            <span class="input-group-text tr-search-icon" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" width="14" height="14" focusable="false">
                                                    <path d="M10.5 3a7.5 7.5 0 1 0 4.7 13.3l4.2 4.2 1.4-1.4-4.2-4.2A7.5 7.5 0 0 0 10.5 3Zm0 2a5.5 5.5 0 1 1 0 11 5.5 5.5 0 0 1 0-11Z" fill="currentColor"/>
                                                </svg>
                                            </span>
                                            <input type="text" class="form-control" id="<?= $gid ?>__search" placeholder="<?= $txSearchPlaceholder ?>">
                                        </div>
                                    </div>
                                    <?php if ($p->searchShowFields): ?>
                                        <div class="form-text"><?= $txFields ?>: <?= self::h(implode(', ', $p->searchCols)) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($p->facets)): ?>
                                    <?php foreach ($p->facets as $fx):
                                $title = (string)($fx['title'] ?? ($fx['column'] ?? ''));
                                $col   = (string)($fx['column'] ?? '');
                                $type  = strtolower((string)($fx['type'] ?? 'buttons')); // buttons | dropdown_multi | range_number | range_date
                                $match = strtolower((string)($fx['match'] ?? 'exact')); // exact | csv_token | contains
                                $slot = strtolower((string)($fx['slot'] ?? ($fx['placement'] ?? 'main')));
                                $default = $fx['default'] ?? ($fx['selected'] ?? null);
                                $defaultAttr = is_array($default) || is_scalar($default) ? $this->jsonAttr($default) : '';
                                $dropdownSearch = !empty($fx['search']);

                                if ($col === '') continue;
                                if (!in_array($col, $this->columnsFinal, true)) continue;
                                if (!in_array($match, ['exact', 'csv_token', 'contains'], true)) $match = 'exact';
                                if ($slot === 'side') continue;

                                if (isset($fx['values']) && is_array($fx['values'])) {
                                    $values = $this->normalizeFacetValues($fx['values']);
                                } else {
                                    $values = $this->facetValues($col);
                                }
                                $facetId = 'facet-' . $gid . '-' . $col;
                                $txFrom = self::h($tt('table.range_from', 'From'));
                                $txTo = self::h($tt('table.range_to', 'To'));
                                $facetClasses = [
                                    'tr-facet',
                                    'tr-facet--' . $type,
                                    'tr-facet--slot-main',
                                    'tr-facet--match-' . $match,
                                ];
                                if ($type === 'range_number') {
                                    $facetClasses[] = 'tr-facet--wide';
                                }
                                $extraClassRaw = trim((string)($fx['class'] ?? ($fx['classes'] ?? '')));
                                if ($extraClassRaw !== '') {
                                    $extraClasses = preg_split('/\s+/', $extraClassRaw) ?: [];
                                    foreach ($extraClasses as $extraClass) {
                                        if ($extraClass !== '' && preg_match('/^[a-z0-9_-]+$/i', $extraClass)) {
                                            $facetClasses[] = $extraClass;
                                        }
                                    }
                                }
                                $facetClassAttr = implode(' ', array_values(array_unique($facetClasses)));
                                ?>
                                <div class="<?= self::h($facetClassAttr) ?>"
                                     data-col="<?= self::h($col) ?>"
                                     data-type="<?= self::h($type) ?>"
                                     data-match="<?= self::h($match) ?>"
                                     data-default="<?= self::h($defaultAttr) ?>">
                                    <div class="small text-muted"><?= self::h($title) ?></div>

                                    <?php if ($type === 'range_number'):
                                        $numValues = isset($fx['values']) && is_array($fx['values'])
                                            ? array_values(array_filter(array_map(static fn($v) => is_numeric((string)$v) ? (float)$v : null, $fx['values'])))
                                            : $this->facetNumericValues($col);
                                        if (!empty($numValues)) {
                                            sort($numValues, SORT_NUMERIC);
                                $numValues = array_values(array_unique($numValues));
                            }
                            ?>
                            <div class="tr-facet-range-number">
                                <div class="tr-range-group">
                                    <label class="tr-range-label"><?= $txFrom ?></label>
                                    <?= $renderRangeNumberDropdown($facetId, 'from', $numValues) ?>
                                </div>
                                <div class="tr-range-group">
                                    <label class="tr-range-label"><?= $txTo ?></label>
                                    <?= $renderRangeNumberDropdown($facetId, 'to', $numValues) ?>
                                </div>
                            </div>
                                    <?php elseif ($type === 'range_date'):
                                        $dateValues = isset($fx['values']) && is_array($fx['values'])
                                            ? $this->normalizeFacetValues(array_map(fn($v) => $this->normalizeDateForRange($v), $fx['values']))
                                            : $this->facetDateValues($col) ?>
                                        <div class="tr-facet-range-date">
                                            <div class="tr-range-group">
                                                <label class="tr-range-label"><?= $txFrom ?></label>
                                                <div class="tr-range-input-wrap">
                                                    <input type="date" class="form-control form-control-sm tr-range-from" placeholder="<?= $txFrom ?>">
                                                    <?php if (!empty($dateValues)): ?>
                                                    <select class="form-select form-select-sm tr-range-from-select" aria-label="<?= $txFrom ?>">
                                                        <option value="">—</option>
                                                        <?php foreach ($dateValues as $dv): ?>
                                                        <option value="<?= self::h($dv) ?>"><?= self::h($dv) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="tr-range-group">
                                                <label class="tr-range-label"><?= $txTo ?></label>
                                                <div class="tr-range-input-wrap">
                                                    <input type="date" class="form-control form-control-sm tr-range-to" placeholder="<?= $txTo ?>">
                                                    <?php if (!empty($dateValues)): ?>
                                                    <select class="form-select form-select-sm tr-range-to-select" aria-label="<?= $txTo ?>">
                                                        <option value="">—</option>
                                                        <?php foreach ($dateValues as $dv): ?>
                                                        <option value="<?= self::h($dv) ?>"><?= self::h($dv) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php elseif ($type === 'dropdown_multi'): ?>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle tr-dd-toggle"
                                                    type="button"
                                                    id="<?= self::h($facetId) ?>__btn"
                                                    aria-expanded="false">
                                                <span class="tr-dd-label"><?= $txAll ?></span>
                                                <span class="badge bg-secondary-subtle text-secondary-emphasis tr-dd-count d-none">0</span>
                                            </button>

                                            <div class="dropdown-menu p-2"
                                                 aria-labelledby="<?= self::h($facetId) ?>__btn"
                                                 style="min-width:280px; max-height:320px; overflow:auto;">
                                                <div class="d-flex gap-2 mb-2">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary tr-dd-all"><?= $txAll ?></button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary tr-dd-clear"><?= $txClear ?></button>
                                                </div>
                                                <?php if ($dropdownSearch): ?>
                                                <div class="mb-2">
                                                    <input type="text" class="form-control form-control-sm tr-dd-search" placeholder="<?= self::h($tt('table.search', 'Search')) ?>">
                                                </div>
                                                <?php endif; ?>

                                                <?php foreach ($values as $v):
                                                    $vid = $facetId . '__' . md5((string)$v);
                                                    ?>
                                                    <div class="form-check">
                                                        <input class="form-check-input tr-dd-chk"
                                                               type="checkbox"
                                                               id="<?= self::h($vid) ?>"
                                                               value="<?= self::h($v) ?>">
                                                        <label class="form-check-label" for="<?= self::h($vid) ?>">
                                                            <?= self::h($v) ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="btn-group btn-group-sm" role="group" aria-label="facet-<?= self::h($col) ?>">
                                            <button type="button" class="btn btn-outline-secondary active" data-value=""><?= $txAll ?></button>
                                            <?php foreach ($values as $v): ?>
                                                <button type="button" class="btn btn-outline-secondary" data-value="<?= self::h($v) ?>">
                                                    <?= self::h($v) ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <?php if (!empty($facetsSide) || $toolsInsideFilters): ?>
                                    <?php foreach ($facetsSide as $fx) {
                                        echo $renderFacet($fx);
                                    } ?>

                            <?php if ($toolsInsideFilters): ?>
                                <div class="tr-facet">
                                    <div class="small text-muted"><?= $txColumns ?></div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle tr-cols-dd-toggle"
                                                type="button"
                                                id="<?= $gid ?>__cols_btn"
                                                aria-expanded="false">
                                            <span class="tr-cols-dd-label"><?= $txAll ?></span>
                                            <span class="badge bg-secondary-subtle text-secondary-emphasis tr-cols-dd-count d-none">0</span>
                                        </button>
                                        <div class="dropdown-menu p-2 tr-cols-dd-menu"
                                             aria-labelledby="<?= $gid ?>__cols_btn"
                                             style="min-width:260px; max-height:320px; overflow:auto;">
                                            <div class="d-flex gap-2 mb-2">
                                                <button type="button" class="btn btn-sm btn-outline-secondary tr-cols-dd-all"><?= $txAll ?></button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary tr-cols-dd-clear"><?= $txClear ?></button>
                                            </div>
                                            <div id="<?= $gid ?>__cols" class="d-flex flex-column gap-1">
                                                <?php foreach ($this->columnsFinal as $idx => $col):
                                                    $label = $this->headersFinal[$col] ?? $col;
                                                    $id = $gid . '__col_' . $idx;
                                                    $isVisible = $this->columnVisibleByDefault($col);
                                                    ?>
                                                    <div class="form-check">
                                                        <input class="form-check-input tr-colchk" type="checkbox" id="<?= self::h($id) ?>"
                                                               data-col-idx="<?= (int)$idx ?>"<?= $isVisible ? ' checked' : '' ?>>
                                                        <label class="form-check-label" for="<?= self::h($id) ?>"><?= self::h($label) ?></label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="tr-facet">
                                    <div class="small text-muted">&nbsp;</div>
                                    <button type="button" id="<?= $gid ?>__reset" class="btn btn-sm btn-outline-danger">
                                        <?= $txResetFilters ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                            <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!$toolsInsideFilters): ?>
                    <div>
                        <div class="form-label mb-1"><?= $txColumns ?></div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle tr-cols-dd-toggle"
                                    type="button"
                                    id="<?= $gid ?>__cols_btn"
                                    aria-expanded="false">
                                <span class="tr-cols-dd-label"><?= $txAll ?></span>
                                <span class="badge bg-secondary-subtle text-secondary-emphasis tr-cols-dd-count d-none">0</span>
                            </button>
                            <div class="dropdown-menu p-2 tr-cols-dd-menu"
                                 aria-labelledby="<?= $gid ?>__cols_btn"
                                 style="min-width:260px; max-height:320px; overflow:auto;">
                                <div class="d-flex gap-2 mb-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary tr-cols-dd-all"><?= $txAll ?></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary tr-cols-dd-clear"><?= $txClear ?></button>
                                </div>
                                <div id="<?= $gid ?>__cols" class="d-flex flex-column gap-1">
                                    <?php foreach ($this->columnsFinal as $idx => $col):
                                        $label = $this->headersFinal[$col] ?? $col;
                                        $id = $gid . '__col_' . $idx;
                                        $isVisible = $this->columnVisibleByDefault($col);
                                        ?>
                                        <div class="form-check">
                                            <input class="form-check-input tr-colchk" type="checkbox" id="<?= self::h($id) ?>"
                                                   data-col-idx="<?= (int)$idx ?>"<?= $isVisible ? ' checked' : '' ?>>
                                            <label class="form-check-label" for="<?= self::h($id) ?>"><?= self::h($label) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div>
                    <div class="form-label mb-1 d-none d-md-block">&nbsp;</div>
                    <div id="<?= $gid ?>__dt_buttons" class="btn-group"></div>
                </div>

                <?php if (!$toolsInsideFilters): ?>
                    <div>
                        <div class="form-label mb-1 d-none d-md-block">&nbsp;</div>
                        <button type="button" id="<?= $gid ?>__reset" class="btn btn-sm btn-outline-danger">
                            <?= $txResetFilters ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="shadow-sm rounded-3">
            <div class="table-responsive" id="<?= $gid ?>__scroll">
                <table class="table table-bordered align-middle w-100" id="<?= $gid ?>__tbl">
                    <colgroup>
                        <?php foreach ($this->columnsFinal as $col):
                            $colWidth = $this->columnWidth($col);
                            $colStyles = [];
                            if ($colWidth !== '') {
                                $colStyles[] = 'width:' . $colWidth;
                                $colStyles[] = 'min-width:' . $colWidth;
                                $colStyles[] = 'max-width:' . $colWidth;
                            }
                            ?>
                            <col class="tr-col-width" data-col="<?= self::h($col) ?>"<?= $this->styleAttr($colStyles) ?>>
                        <?php endforeach; ?>
                    </colgroup>
                    <thead class="table-light">
                    <tr>
                        <?php foreach ($this->columnsFinal as $idx => $col):
                            $colWidth = $this->columnWidth($col);
                            $colEllipsis = $this->columnEllipsis($col);
                            $colNoWrap = $this->columnNoWrap($col);
                            $thStyles = [];
                            if ($colWidth !== '') {
                                $thStyles[] = 'width:' . $colWidth;
                                $thStyles[] = 'min-width:' . $colWidth;
                                $thStyles[] = 'max-width:' . $colWidth;
                            }
                            $thClass = 'th-col th-col-' . $col
                                . (($colEllipsis || $colNoWrap) ? ' tr-col-nowrap' : '')
                                . ($colEllipsis ? ' tr-col-ellipsis' : '');
                            ?>
                            <th class="<?= self::h($thClass) ?>" data-col="<?= self::h($col) ?>" data-col-idx="<?= (int)$idx ?>" role="columnheader"<?= $this->styleAttr($thStyles) ?>>
                                <span class="tr-th-label"><?= self::h($this->headersFinal[$col] ?? $col) ?></span>
                                <span class="sort-indicator" aria-hidden="true"></span>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($p->data as $i => $row): ?>
                        <tr class="tr-row tr-row-<?= $i % 2 === 0 ? 'even' : 'odd' ?>"
                            <?php
                            foreach ($p->facets as $fx) {
                                $c = (string)($fx['column'] ?? '');
                                if ($c === '') continue;
                                if (!in_array($c, $this->columnsFinal, true)) continue;
                                $raw = is_array($row) ? ($row[$c] ?? null) : null;
                                $facetType = strtolower((string)($fx['type'] ?? 'buttons'));
                                if ($facetType === 'range_date') {
                                    $val = $this->normalizeDateForRange($raw);
                                } else {
                                    $val = $this->toScalarString($raw);
                                }
                                if ($val !== '') echo ' data-' . self::h($c) . '="' . self::h($val) . '"';
                            }
                            ?>>
                            <?php foreach ($this->columnsFinal as $col):
                                $colWidth = $this->columnWidth($col);
                                $colEllipsis = $this->columnEllipsis($col);
                                $colNoWrap = $this->columnNoWrap($col);
                                $tdStyles = [];
                                if ($colWidth !== '') {
                                    $tdStyles[] = 'width:' . $colWidth;
                                    $tdStyles[] = 'min-width:' . $colWidth;
                                    $tdStyles[] = 'max-width:' . $colWidth;
                                }
                                $tdClass = 'td-col td-col-' . $col
                                    . (($colEllipsis || $colNoWrap) ? ' tr-col-nowrap' : '')
                                    . ($colEllipsis ? ' tr-col-ellipsis' : '');
                                $cellHtml = $this->renderCell($col, is_array($row) ? $row : []);
                                ?>
                                <td class="<?= self::h($tdClass) ?>" data-col="<?= self::h($col) ?>"<?= $this->styleAttr($tdStyles) ?>>
                                    <?php if ($colEllipsis || $colNoWrap): ?>
                                        <div class="tr-cell-clip">
                                            <?= $cellHtml ?>
                                        </div>
                                    <?php else: ?>
                                        <?= $cellHtml ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="modal fade" id="<?= $gid ?>__modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="post" class="modal-content" id="<?= $gid ?>__form">
                    <div class="modal-header">
                        <h5 class="modal-title" id="<?= $gid ?>__mtitle"><?= $txModalTitle ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $txClose ?>"></button>
                    </div>
                    <div class="modal-body" id="<?= $gid ?>__mbody"></div>
                    <div class="modal-footer" id="<?= $gid ?>__mfooter"></div>
                </form>
            </div>
        </div>
        <?php

        if ($wrapAfter !== '') echo $wrapAfter;
    }
}


//Р С—РЎР‚Р С‘Р СР ВµРЎР‚ Р С‘РЎРѓР С—Р С•Р В»РЎРЉР В·Р С•Р Р†Р В°Р Р…Р С‘РЎРЏ

//$tr = new TableRenderer();
//
//$tr->set_params->data       = $rows;
//$tr->set_params->headers    = $headers;
//$tr->set_params->columns    = $columns;      // Р С‘Р В»Р С‘ ['*'] Р Т‘Р В»РЎРЏ Р В°Р Р†РЎвЂљР С•
//$tr->set_params->wrap       = $wrap;
//$tr->set_params->perPage    = 50;
//$tr->set_params->searchCols = $searchCols;
//$tr->set_params->gridId     = 'appointments_grid';
//$tr->set_params->formatters = $formatters;
//$tr->set_params->facets     = $facets;
//$tr->set_params->actions    = [];
//$tr->set_params->context    = [];
//$tr->set_params->columnsOrder = $columns;
//
//$tr->render();
