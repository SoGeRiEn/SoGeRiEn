<?php
declare(strict_types=1);

/**
 * Класс FORMS — конструктор форм с палитрою + калькулятор + строки заказа
 */
final class Forms
{
    use SogerienClassHelp;

    private array $form = [
        'id' => 'form1',
        'class' => 'sog-form',
        'action' => '',
        'method' => 'POST',
        'ajax' => true,
        'enctype' => 'application/x-www-form-urlencoded',
        'items' => [],
        'meta' => [
            'datasets' => [],
            'calculator' => null,   // см. setCalculator()
            'order_lines' => null,   // см. addOrderLines()
        ],
    ];

    private int $_autoInc = 0;

    public function __construct(array $opts = [])
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->form = array_replace_recursive($this->form, $opts);
        if (!preg_match('~^(GET|POST)$~i', (string)$this->form['method'])) {
            $this->form['method'] = 'POST';
        }
}

    /* ====== Глобальные датасеты ====== */
    public function configure(array $opts): self { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->form = array_replace_recursive($this->form, $opts);
        if (!preg_match('~^(GET|POST)$~i', (string)$this->form['method'])) {
            $this->form['method'] = 'POST';
        }
        return   Sogerien::Debager()->capture_return($this, __CLASS__, __FUNCTION__);
}
    public function addHidden(string $name, $value=''): self { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return   Sogerien::Debager()->capture_return($this->addItem(['type'=>'hidden','name'=>$name,'value'=>$value]), __CLASS__, __FUNCTION__);
}

    public function setDataset(string $name, array $rows): self
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->form['meta']['datasets'][$name] = array_values($rows);
        return   Sogerien::Debager()->capture_return($this, __CLASS__, __FUNCTION__);
}

    /* ====== Калькулятор ======
     * Пример конфигурации:
     * ->setCalculator([
     *   'sources'       => ['price','addons_price'], // какие поля суммировать как базу (числа)
     *   'qty'           => 'quantity',               // поле количества (целое/вещественное)
     *   'discount_type' => 'discount_type',          // none|percent|fixed
     *   'discount_val'  => 'discount_value',         // число
     *   'target'        => 'total',                  // КУДА писать сумму (input)
     *   'precision'     => 2                         // округление
     * ])
     */
    public function setCalculator(array $cfg): self
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $def = [
            'sources' => [],
            'qty' => null,
            'discount_type' => null,
            'discount_val' => null,
            'target' => null,
            'precision' => 2,
        ];
        $this->form['meta']['calculator'] = array_replace($def, $cfg);
        return   Sogerien::Debager()->capture_return($this, __CLASS__, __FUNCTION__);
}

    /* ====== Строки заказа (таблица под формой) ======
     * Пример:
     * ->addOrderLines([
     *   'id'      => 'lines1',
     *   'button'  => ['text'=>'Замовити послугу'], // подпись на кнопке
     *   'columns' => [
     *      // key: ключ в строке, title: заголовок, from: имя поля формы, format: 'lookup:DATASET:field|number|raw'
     *      ['key'=>'service','title'=>'Послуга','from'=>'service_id','format'=>'lookup:services:title'],
     *      ['key'=>'qty',    'title'=>'К-сть',   'from'=>'quantity',  'format'=>'number'],
     *      ['key'=>'price',  'title'=>'Ціна',    'from'=>'price',     'format'=>'number'],
     *      ['key'=>'total',  'title'=>'Сума',    'from'=>'total',     'format'=>'number'],
     *   ],
     *   'dedupe_by' => ['service','price','qty'], // опционально: не добавлять дубли
     * ])
     */
    public function addOrderLines(array $cfg): self
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $def = [
            'id' => 'order_lines',
            'button' => ['text' => $this->t('forms.add_to_table')],
            'columns' => [],
            'dedupe_by' => [],
        ];
        $this->form['meta']['order_lines'] = array_replace_recursive($def, $cfg);
        return   Sogerien::Debager()->capture_return($this, __CLASS__, __FUNCTION__);
}

    /* ====== Билдеры полей ====== */
    private function addItem(array $item): self
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $item['id'] = $item['id'] ?? $this->form['id'] . '__' . $item['name'];
        $item['label'] = $item['label'] ?? '';
        $item['show_if'] = $item['show_if'] ?? null;
        $this->form['items'][] = $item;
        return   Sogerien::Debager()->capture_return($this, __CLASS__, __FUNCTION__);
}

    public function addSeparator(string $title = '', array $attrs = []): self
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return   Sogerien::Debager()->capture_return($this->addItem([
            'type' => 'separator', 'name' => 'sep_' . (count($this->form['items']) + 1), 'title' => $title, 'attrs' => $attrs,
        ]), __CLASS__, __FUNCTION__);
}

    public function addInput(string $name, string $label, string $inputType = 'text', array $attrs = [], $default = null): self
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return   Sogerien::Debager()->capture_return($this->addItem([
            'type' => 'input', 'input' => $inputType, 'name' => $name, 'label' => $label, 'attrs' => $attrs, 'value' => $default,
        ]), __CLASS__, __FUNCTION__);
}

    public function addHTML(string $html, array $attrs = [], ?string $name = null): self
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if ($name === null || $name === '') {
            $this->_autoInc++;
            $name = '__html_' . $this->_autoInc; // гарантия наличия ключа name
        }
        return   Sogerien::Debager()->capture_return($this->addItem([
            'type'  => 'html',
            'name'  => $name,
            'label' => null,       // чтобы рендер не искал подпись
            'html'  => $html,
            'attrs' => $attrs,
        ]), __CLASS__, __FUNCTION__);
}


    public function addTextarea(string $name, string $label, array $attrs = [], $default = null): self
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return   Sogerien::Debager()->capture_return($this->addItem([
            'type' => 'textarea', 'name' => $name, 'label' => $label, 'attrs' => $attrs + ['rows' => (string)($attrs['rows'] ?? '4')], 'value' => $default,
        ]), __CLASS__, __FUNCTION__);
}

    public function addSelect(string $name, string $label, array $options, array $attrs = [], $default = null): self
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return   Sogerien::Debager()->capture_return($this->addItem([
            'type' => 'select', 'name' => $name, 'label' => $label, 'attrs' => $attrs, 'options' => array_values($options), 'value' => $default,
        ]), __CLASS__, __FUNCTION__);
}

    public function addLinkedSelect(string $name, string $label, string $parentField, array $childOptions, string $byParent, array $attrs = [], $default = null): self
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return   Sogerien::Debager()->capture_return($this->addItem([
            'type' => 'linked_select', 'name' => $name, 'label' => $label, 'attrs' => $attrs,
            'parent' => $parentField, 'child' => array_values($childOptions), 'by_parent' => $byParent, 'value' => $default,
        ]), __CLASS__, __FUNCTION__);
}

    public function addCheckbox(string $name, string $label, array $attrs = [], bool $checked = false): self
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return   Sogerien::Debager()->capture_return($this->addItem([
            'type' => 'checkbox', 'name' => $name, 'label' => $label, 'attrs' => $attrs, 'value' => $checked ? '1' : '0',
        ]), __CLASS__, __FUNCTION__);
}

    public function addCheckboxGroup(string $name, string $label, array $options, array $attrs = [], array $checkedVals = []): self
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return   Sogerien::Debager()->capture_return($this->addItem([
            'type' => 'checkbox_group', 'name' => $name, 'label' => $label, 'attrs' => $attrs, 'options' => array_values($options), 'value' => $checkedVals,
        ]), __CLASS__, __FUNCTION__);
}

    public function addRadioGroup(string $name, string $label, array $options, array $attrs = [], $checked = null): self
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return   Sogerien::Debager()->capture_return($this->addItem([
            'type' => 'radio_group', 'name' => $name, 'label' => $label, 'attrs' => $attrs, 'options' => array_values($options), 'value' => $checked,
        ]), __CLASS__, __FUNCTION__);
}

    public function addFacetButtons(string $name, string $label, array $options, array $attrs = [], string $default = ''): self
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return Sogerien::Debager()->capture_return($this->addItem([
            'type' => 'facet_buttons',
            'name' => $name,
            'label' => $label,
            'attrs' => $attrs,
            'options' => array_values($options),
            'value' => $default,
        ]), __CLASS__, __FUNCTION__);
}

    public function addFacetDropdownMulti(string $name, string $label, array $options, array $attrs = [], array $selected = [], bool $search = false): self
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return Sogerien::Debager()->capture_return($this->addItem([
            'type' => 'facet_dropdown_multi',
            'name' => $name,
            'label' => $label,
            'attrs' => $attrs,
            'options' => array_values($options),
            'value' => array_values($selected),
            'search' => $search,
        ]), __CLASS__, __FUNCTION__);
}

    public function addFacetRangeNumber(string $name, string $label, array $values = [], array $attrs = [], ?string $from = null, ?string $to = null): self
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return Sogerien::Debager()->capture_return($this->addItem([
            'type' => 'facet_range_number',
            'name' => $name,
            'label' => $label,
            'attrs' => $attrs,
            'values' => array_values($values),
            'value' => ['from' => (string)($from ?? ''), 'to' => (string)($to ?? '')],
        ]), __CLASS__, __FUNCTION__);
}

    public function addFacetRangeDate(string $name, string $label, array $attrs = [], ?string $from = null, ?string $to = null): self
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return Sogerien::Debager()->capture_return($this->addItem([
            'type' => 'facet_range_date',
            'name' => $name,
            'label' => $label,
            'attrs' => $attrs,
            'value' => ['from' => (string)($from ?? ''), 'to' => (string)($to ?? '')],
        ]), __CLASS__, __FUNCTION__);
}

    public function addButton(string $text, array $attrs = []): self
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return   Sogerien::Debager()->capture_return($this->addItem(['type' => 'button', 'name' => 'btn_' . (count($this->form['items']) + 1), 'text' => $text, 'attrs' => $attrs]), __CLASS__, __FUNCTION__);
}

    public function addSubmit(string $text = '', array $attrs = []): self
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if ($text === '') {
            $text = $this->t('common.save');
        }
        return   Sogerien::Debager()->capture_return($this->addItem(['type' => 'submit', 'name' => 'submit', 'text' => $text, 'attrs' => $attrs]), __CLASS__, __FUNCTION__);
}

    public function showIf(array $cfg): self
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $i = count($this->form['items']) - 1;
        if ($i >= 0) $this->form['items'][$i]['show_if'] = $cfg;
        return   Sogerien::Debager()->capture_return($this, __CLASS__, __FUNCTION__);
}

    public function toArray(): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return   Sogerien::Debager()->capture_return($this->form, __CLASS__, __FUNCTION__);
}
    public function breakRow(): self
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $n = count($this->form['items']) + 1;
        $this->form['items'][] = [
            'type' => 'break',
            'name' => '__br_'.$n,
            'id'   => $this->form['id'].'__br_'.$n,
        ];
        return   Sogerien::Debager()->capture_return($this, __CLASS__, __FUNCTION__);
}

    public function col(int $sm=12, ?int $md=null, ?int $lg=null, ?int $xl=null): self
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $i = count($this->form['items']) - 1;
        if ($i >= 0) {
            $md = $md ?? $sm;
            $lg = $lg ?? $md;
            $xl = $xl ?? $lg;
            $this->form['items'][$i]['meta']['col'] = ['sm'=>$sm,'md'=>$md,'lg'=>$lg,'xl'=>$xl];
        }
        return   Sogerien::Debager()->capture_return($this, __CLASS__, __FUNCTION__);
}
    /* ====== Рендер ====== */
    public function render(): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $F = $this->toArray();

        // BODY
        $id      = htmlspecialchars((string)$F['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $action  = htmlspecialchars((string)$F['action'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $method  = htmlspecialchars((string)$F['method'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $enctype = htmlspecialchars((string)$F['enctype'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $cls     = htmlspecialchars((string)$F['class'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $ajax    = !empty($F['ajax']) ? 'true' : 'false';

        echo '<div class="sog-ui container-fluid">';

        // Форма как сетка Bootstrap
        echo "<form id=\"{$id}\" class=\"{$cls} row g-2 align-items-end\" action=\"{$action}\" method=\"{$method}\" enctype=\"{$enctype}\" data-ajax=\"{$ajax}\">";

        // Каждый item — колонка сетки. По умолчанию: 12 / 6 / 4 / 4
        foreach ($F['items'] as $it) {
            // ❶ hidden — рендерим без колоночного wrapper'а (не занимает место)
            if (($it['type'] ?? '') === 'hidden') {
                $this->renderItem($it);
                continue;
            }
            $meta = (array)($it['meta'] ?? []);
            $col  = (array)($meta['col'] ?? []);
            $cSm  = (int)($col['sm'] ?? 12);
            $cMd  = (int)($col['md'] ?? 6);
            $cLg  = (int)($col['lg'] ?? 4);
            $cXl  = (int)($col['xl'] ?? $cLg);

            $colCls = sprintf('col-12 col-sm-%d col-md-%d col-lg-%d col-xl-%d', $cSm, $cMd, $cLg, $cXl);

            // ❷ поддержка кастомного класса для внешнего wrapper'а
            $wrapExtra = trim((string)($it['wrap_class'] ?? ''));
            $wrapCls = $wrapExtra ? ($colCls.' '.$wrapExtra) : $colCls;


            echo '<div class="'.$wrapCls.'" id="'.htmlspecialchars($it['id'].'_wrap', ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'">';
            $this->renderItem($it);
            echo '</div>';
        }

        // Кнопочные действия (если заданы)
        if (!empty($F['actions'])) {
            echo '<div class="col-12 d-flex gap-2">';
            foreach ($F['actions'] as $btnHtml) {
                echo $btnHtml; // ожидается, что кнопки уже с классами btn btn-primary/secondary
            }
            echo '</div>';
        }

        echo '</form>';

        // Место под таблицу строк заказа (если включено)
        if (!empty($F['meta']['order_lines'])) {
            $tblId = htmlspecialchars((string)$F['meta']['order_lines']['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            echo "<div id=\"{$tblId}_host\" class=\"mt-3\"></div>";
        }

        if (!empty($F['show_hint'])) {
            echo "<div class='hint mt-2'>" . htmlspecialchars($this->t('forms.ajax_hint'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</div>";
        }



        // Модалка выбора (общая для всех селектов)
        $palTitle = $this->h($this->t('forms.select'));
        $palSearchPlaceholder = $this->h($this->t('forms.start_typing'));
        $palSearchAria = $this->h($this->t('forms.search'));
        $palCloseAria = $this->h($this->t('forms.close'));
        $palHint = $this->h($this->t('forms.select_hint'));
        echo '<div id="paletteModal" class="modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="palTitle">';
        echo '  <div class="panel" tabindex="-1">';
        echo '    <div class="head">';
        echo '      <strong id="palTitle">' . $palTitle . '</strong>';
        echo '      <input id="palSearch" placeholder="' . $palSearchPlaceholder . '" aria-label="' . $palSearchAria . '">';
        echo '      <button class="close" id="palClose" aria-label="' . $palCloseAria . '">Esc</button>';
        echo '    </div>';
        echo '    <div class="list" id="palList"></div>';
        echo '    <div class="hint" style="padding:8px 12px">' . $palHint . '</div>';
        echo '  </div>';
        echo '</div>';

        echo '</div>'; // .container-fluid

        // DATA
        $json = json_encode($F, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo "<script>window.__FORM_DEF__ = {$json};</script>";
}

    private function renderItem(array $it): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $type = $it['type'];
        $id = htmlspecialchars((string)($it['id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $name = htmlspecialchars((string)($it['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $label = htmlspecialchars((string)($it['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $attrHtml = $this->attrsToHtml($it['attrs'] ?? []);
//        $wrapperId = $id . '_wrap';
//        echo "<div class=\"rowline\" id=\"{$wrapperId}\">\n";
        $wrapperId= $id.'_wrap';
        $wrapClass = 'rowline'.(!empty($it['wrap_class']) ? ' '.htmlspecialchars($it['wrap_class'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') : '');
        if ($type!=='hidden') echo "<div class=\"rowline\" id=\"{$wrapperId}\">\n";
        switch ($type) {
            case 'separator':
                $title = htmlspecialchars((string)($it['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                echo "<div class=\"sog-sep\" {$attrHtml}>{$title}</div>\n";
                break;

            case 'input':
                $inpType = htmlspecialchars((string)($it['input'] ?? 'text'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $val = htmlspecialchars((string)($it['value'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                echo "<label for=\"{$id}\">{$label}</label>\n";
                echo "<input type=\"{$inpType}\" id=\"{$id}\" name=\"{$name}\" value=\"{$val}\" {$attrHtml}>\n";
                break;

            case 'textarea':
                $val = htmlspecialchars((string)($it['value'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                echo "<label for=\"{$id}\">{$label}</label>\n";
                echo "<textarea id=\"{$id}\" name=\"{$name}\" {$attrHtml}>{$val}</textarea>\n";
                break;

            case 'select':
                echo "<label for=\"{$id}\">{$label}</label>\n";
                echo "<select id=\"{$id}\" name=\"{$name}\" {$attrHtml} data-kind=\"solo\"></select>\n";
                break;

            case 'linked_select':
                echo "<label for=\"{$id}\">{$label}</label>\n";
                echo "<select id=\"{$id}\" name=\"{$name}\" {$attrHtml} data-kind=\"linked\" data-parent=\"" . htmlspecialchars($it['parent'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\" data-by-parent=\"" . htmlspecialchars($it['by_parent'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\"></select>\n";
                break;

            case 'checkbox':
                $checked = ((string)($it['value'] ?? '0') === '1') ? 'checked' : '';
                echo "<label>{$label}</label>\n";
                echo "<input type=\"checkbox\" id=\"{$id}\" name=\"{$name}\" value=\"1\" {$checked} {$attrHtml}>\n";
                break;

            case 'checkbox_group':
                echo "<label>{$label}</label>\n<div>";
                $vals = is_array($it['value'] ?? null) ? $it['value'] : [];
                foreach (($it['options'] ?? []) as $opt) {
                    $oid = $id . '__' . htmlspecialchars((string)$opt['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $ot = htmlspecialchars((string)($opt['text'] ?? $opt['title'] ?? $opt['id']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $oval = htmlspecialchars((string)$opt['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $chk = in_array($opt['id'], $vals, true) ? 'checked' : '';
                    echo "<label class=\"muted\" for=\"{$oid}\" style=\"min-width:auto;margin-right:10px\">";
                    echo "<input type=\"checkbox\" id=\"{$oid}\" name=\"{$name}[]\" value=\"{$oval}\" {$chk}> {$ot}</label>";
}
                echo "</div>\n";
                break;

            case 'radio_group':
                echo "<label>{$label}</label>\n<div>";
                $cur = $it['value'] ?? null;
                foreach (($it['options'] ?? []) as $opt) {
                    $oid = $id . '__' . htmlspecialchars((string)$opt['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $ot = htmlspecialchars((string)($opt['text'] ?? $opt['title'] ?? $opt['id']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $oval = htmlspecialchars((string)$opt['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $chk = ((string)$cur === (string)$opt['id']) ? 'checked' : '';
                    echo "<label class=\"muted\" for=\"{$oid}\" style=\"min-width:auto;margin-right:10px\">";
                    echo "<input type=\"radio\" id=\"{$oid}\" name=\"{$name}\" value=\"{$oval}\" {$chk}> {$ot}</label>";
                }
                echo "</div>\n";
                break;

            case 'facet_buttons':
                $cur = (string)($it['value'] ?? '');
                echo "<div class=\"tr-facet tr-facet--buttons\" data-type=\"buttons\" data-form-facet=\"1\">";
                echo "<div class=\"small text-muted\">{$label}</div>";
                echo "<div class=\"btn-group btn-group-sm\" role=\"group\" aria-label=\"facet-{$name}\">";
                echo "<button type=\"button\" class=\"btn btn-outline-secondary ".($cur === '' ? 'active' : '')."\" data-value=\"\">".$this->h($this->t('table.all'))."</button>";
                foreach (($it['options'] ?? []) as $opt) {
                    $ov = (string)($opt['id'] ?? $opt['value'] ?? '');
                    $ot = (string)($opt['text'] ?? $opt['title'] ?? $ov);
                    if ($ov === '') {
                        continue;
                    }
                    $active = $cur === $ov ? ' active' : '';
                    echo '<button type="button" class="btn btn-outline-secondary'.$active.'" data-value="'.$this->h($ov).'">'.$this->h($ot).'</button>';
                }
                echo "</div>";
                echo '<input type="hidden" class="tr-facet-hidden" name="'.$name.'" value="'.$this->h($cur).'">';
                echo "</div>\n";
                break;

            case 'facet_dropdown_multi':
                $selectedVals = is_array($it['value'] ?? null) ? $it['value'] : [];
                $searchEnabled = !empty($it['search']);
                echo $this->render_dropdown_multi_facet($id, $name, (string)$it['label'], (array)($it['options'] ?? []), $selectedVals, $searchEnabled);
                break;

            case 'facet_range_number':
                $from = (string)($it['value']['from'] ?? '');
                $to = (string)($it['value']['to'] ?? '');
                echo "<div class=\"tr-facet tr-facet--range_number\" data-type=\"range_number\" data-form-facet=\"1\">";
                echo "<div class=\"small text-muted\">{$label}</div>";
                echo '<div class="tr-facet-range-number">';
                echo '<div class="tr-range-group"><label class="tr-range-label">'.$this->h($this->t('table.range_from')).'</label><div class="tr-range-input-wrap">';
                echo '<input type="number" step="any" class="form-control form-control-sm tr-range-from" name="'.$name.'[from]" value="'.$this->h($from).'" '.$attrHtml.'>';
                echo '</div></div>';
                echo '<div class="tr-range-group"><label class="tr-range-label">'.$this->h($this->t('table.range_to')).'</label><div class="tr-range-input-wrap">';
                echo '<input type="number" step="any" class="form-control form-control-sm tr-range-to" name="'.$name.'[to]" value="'.$this->h($to).'" '.$attrHtml.'>';
                echo '</div></div>';
                echo '</div></div>';
                break;

            case 'facet_range_date':
                $from = (string)($it['value']['from'] ?? '');
                $to = (string)($it['value']['to'] ?? '');
                echo "<div class=\"tr-facet tr-facet--range_date\" data-type=\"range_date\" data-form-facet=\"1\">";
                echo "<div class=\"small text-muted\">{$label}</div>";
                echo '<div class="tr-facet-range-date">';
                echo '<div class="tr-range-group"><label class="tr-range-label">'.$this->h($this->t('table.range_from')).'</label><div class="tr-range-input-wrap">';
                echo '<input type="date" class="form-control form-control-sm tr-range-from" name="'.$name.'[from]" value="'.$this->h($from).'" '.$attrHtml.'>';
                echo '</div></div>';
                echo '<div class="tr-range-group"><label class="tr-range-label">'.$this->h($this->t('table.range_to')).'</label><div class="tr-range-input-wrap">';
                echo '<input type="date" class="form-control form-control-sm tr-range-to" name="'.$name.'[to]" value="'.$this->h($to).'" '.$attrHtml.'>';
                echo '</div></div>';
                echo '</div></div>';
                break;

            case 'button':
                $text = htmlspecialchars((string)($it['text'] ?? $this->t('forms.button')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                echo "<button type=\"button\" id=\"{$id}\" {$attrHtml} class=\"btn\">{$text}</button>\n";
                break;

            case 'submit':
                $text = htmlspecialchars((string)($it['text'] ?? $this->t('common.save')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                echo "<button type=\"submit\" id=\"{$id}\" {$attrHtml} class=\"btn\">{$text}</button>\n";
                break;

            case 'hidden':
               $val = htmlspecialchars((string)($it['value'] ?? ''), ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
               echo "<input type=\"hidden\" id=\"{$id}\" name=\"{$name}\" value=\"{$val}\">";
               break;
        }
//        echo "</div>\n";
        if ($type!=='hidden') echo "</div>\n";
    }

    private function attrsToHtml(array $attrs): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $out = '';
        foreach ($attrs as $k => $v) {
            if ($v === null) continue;
            $k = htmlspecialchars((string)$k, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $v = htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $out .= " {$k}=\"{$v}\"";
        }
        return  Sogerien::Debager()->capture_return($out, __CLASS__, __FUNCTION__);
    }

    private function attrsToString(array $attrs): string { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $parts = [];
        foreach ($attrs as $k=>$v) {
            $k = htmlspecialchars((string)$k, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
            $v = htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
            $parts[] = $k.'="'.$v.'"';
        }
        return  Sogerien::Debager()->capture_return($parts ? implode(' ', $parts) : '', __CLASS__, __FUNCTION__);
    }

    /**
     * Рендер CRUD-списка: список строк + модалки добавления/редактирования.
     * JS в forms.js читает window.__FORM_CRUD_DEF__.
     *
     * @param array<string,mixed> $config list_id, empty_id, message_id, btn_add_id, rows, row_primary_key, row_display, row_roles_key, roles_list, edit_modal, add_modal, reload_on_edit_success, row_css_class, info_css_class, btn_edit_class, btn_delete_class, btn_edit_text, btn_delete_text, delete_confirm, roles_label
     */
    public function render_crud_list(array $config): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $list_id    = (string)($config['list_id'] ?? 'crud_list');
        $empty_id   = (string)($config['empty_id'] ?? 'crud_empty');
        $message_id = (string)($config['message_id'] ?? 'crud_message');
        $btn_add_id = (string)($config['btn_add_id'] ?? 'crud_btn_add');
        $rows       = $config['rows'] ?? [];
        $row_pk     = (string)($config['row_primary_key'] ?? 'id');
        $row_display = isset($config['row_display']) && is_array($config['row_display']) ? $config['row_display'] : [];
        $row_roles_key = (string)($config['row_roles_key'] ?? 'roles');
        $roles_list = isset($config['roles_list']) && is_array($config['roles_list']) ? $config['roles_list'] : [];
        $edit_modal = $config['edit_modal'] ?? [];
        $add_modal  = $config['add_modal'] ?? [];
        $row_class  = (string)($config['row_css_class'] ?? 'crud-row');
        $info_class = (string)($config['info_css_class'] ?? 'crud-info');
        $btn_edit_class = (string)($config['btn_edit_class'] ?? 'crud-btn-edit');
        $btn_delete_class = (string)($config['btn_delete_class'] ?? 'crud-btn-delete');
        $btn_edit_text   = (string)($config['btn_edit_text'] ?? $this->t('common.edit'));
        $btn_delete_text = (string)($config['btn_delete_text'] ?? $this->t('common.delete'));
        $delete_confirm  = (string)($config['delete_confirm'] ?? ($this->t('common.delete') . ' (id {id})?'));
        $roles_label     = (string)($config['roles_label'] ?? $this->t('roles.role'));

        $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        echo '<div id="' . $h($list_id) . '" class="mb-4">';
        foreach ($rows as $row) {
            $id = (string)($row[$row_pk] ?? '');
            $roles = $row[$row_roles_key] ?? [];
            if (!is_array($roles)) {
                $roles = [];
            }
            $data_attrs = 'data-id="' . $id . '" data-roles="' . $h(json_encode(array_values($roles))) . '"';
            if (isset($row['status'])) {
                $data_attrs .= ' data-status="' . $h((string)$row['status']) . '"';
            }
            foreach ($row_display as $key) {
                $data_attrs .= ' data-' . $h($key) . '="' . $h((string)($row[$key] ?? '')) . '"';
            }
            $parts = [];
            foreach ($row_display as $i => $key) {
                $v = (string)($row[$key] ?? '');
                if ($v === '') {
                    continue;
                }
                $parts[] = $i === 0 ? '<strong>' . $h($v) . '</strong>' : $h($v);
            }
            $line = implode(' — ', $parts);
            if ($row_roles_key !== '' && $roles !== []) {
                $line .= ' <span class="users-roles ms-1 small text-muted">' . $roles_label . ': ' . $h(implode(', ', $roles)) . '</span>';
            }
            echo '<div class="' . $h($row_class) . ' d-flex align-items-center gap-2 py-2 border-bottom" ' . $data_attrs . '>';
            echo '<span class="' . $h($info_class) . ' flex-grow-1 text-break" role="button" tabindex="0" title="' . $h($this->t('forms.edit_hint')) . '">' . $line . '</span>';
            echo '<button type="button" class="btn btn-sm btn-outline-primary ' . $h($btn_edit_class) . '" data-id="' . $id . '">' . $h($btn_edit_text) . '</button>';
            echo '<button type="button" class="btn btn-sm btn-outline-danger ' . $h($btn_delete_class) . '" data-id="' . $id . '">' . $h($btn_delete_text) . '</button>';
            if (!empty($config['status_actions'])) {
                $btn_status_class = (string)($config['btn_status_class'] ?? 'crud-btn-status');
                echo '<div class="btn-group btn-group-sm ms-2" role="group">';
                echo '<button type="button" class="btn btn-outline-success ' . $h($btn_status_class) . '" data-status-target="actual">' . $h($this->t('common.status_active')) . '</button>';
                echo '<button type="button" class="btn btn-outline-secondary ' . $h($btn_status_class) . '" data-status-target="archive">' . $h($this->t('common.status_archive')) . '</button>';
                echo '<button type="button" class="btn btn-outline-danger ' . $h($btn_status_class) . '" data-status-target="delete">' . $h($this->t('common.status_delete')) . '</button>';
                echo '</div>';
            }
            echo '</div>';
        }
        if (count($rows) === 0) {
            echo '<p class="text-muted" id="' . $h($empty_id) . '">' . $h((string)($config['empty_text'] ?? $this->t('forms.list_empty'))) . '</p>';
        }
        echo '</div>';
        echo '<div class="d-flex gap-2"><button type="button" class="btn btn-primary" id="' . $h($btn_add_id) . '">' . $h((string)($config['btn_add_text'] ?? $this->t('common.add'))) . '</button></div>';
        echo '<div id="' . $h($message_id) . '" class="alert mt-3 d-none" role="alert"></div>';

        $this->render_crud_modal($edit_modal, $roles_list, $roles_label, true);
        $this->render_crud_modal($add_modal, $roles_list, $roles_label, false);

        $js_config = $config;
        $js_config['row_display'] = $row_display;
        echo '<script>window.__FORM_CRUD_DEF__ = ' . json_encode($js_config, JSON_UNESCAPED_UNICODE) . ';</script>';
    }

    /**
     * Рендер только модалок редактирования/добавления и window.__FORM_CRUD_DEF__ (без списка строк).
     * Для использования вместе с TableRenderer: таблица рисует кнопки, клик по .tr-action-edit открывает модалку.
     *
     * @param array<string,mixed> $config те же ключи, что и у render_crud_list (edit_modal, add_modal, roles_list, roles_label, row_to_edit_map, edit_to_row_map, row_primary_key, row_display, row_roles_key и т.д.)
     */
    public function render_crud_modals(array $config): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $edit_modal  = $config['edit_modal'] ?? [];
        $add_modal   = $config['add_modal'] ?? [];
        $roles_list  = isset($config['roles_list']) && is_array($config['roles_list']) ? $config['roles_list'] : [];
        $roles_label = (string)($config['roles_label'] ?? $this->t('roles.role'));

        $this->render_crud_modal($edit_modal, $roles_list, $roles_label, true);
        $this->render_crud_modal($add_modal, $roles_list, $roles_label, false);

        $js_config = $config;
        if (isset($config['row_display']) && is_array($config['row_display'])) {
            $js_config['row_display'] = $config['row_display'];
        }
        echo '<script>window.__FORM_CRUD_DEF__ = ' . json_encode($js_config, JSON_UNESCAPED_UNICODE) . ';</script>';
    }

    /**
     * @param array<string,mixed> $modal_cfg
     * @param array<int,string> $roles_list
     */
    private function render_crud_modal(array $modal_cfg, array $roles_list, string $roles_label, bool $is_edit): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $modal_id = (string)($modal_cfg['id'] ?? 'crudEditModal');
        $title   = (string)($modal_cfg['title'] ?? $this->t('common.edit'));
        $fields  = $modal_cfg['fields'] ?? [];
        $save_btn_id = (string)($modal_cfg['save_btn_id'] ?? 'crud_btn_save');
        $dialog_class = (string)($modal_cfg['dialog_class'] ?? 'modal-dialog');
        $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        echo '<div class="modal fade" id="' . $h($modal_id) . '" tabindex="-1" aria-hidden="true">';
        echo '<div class="' . $h($dialog_class) . '"><div class="modal-content">';
        echo '<div class="modal-header"><h5 class="modal-title">' . $h($title) . '</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . $h($this->t('common.close')) . '"></button></div>';
        echo '<div class="modal-body">';
        foreach ($fields as $key => $f) {
            if (isset($f['container_id'])) {
                echo '<div class="mb-2"><label class="form-label">' . $h($roles_label) . '</label><div id="' . $h($f['container_id']) . '" class="d-flex flex-wrap gap-2"></div></div>';
                continue;
            }
            $fid = (string)($f['id'] ?? '');
            $label = (string)($f['label'] ?? $key);
            $type = (string)($f['type'] ?? 'text');
            $attrs = (array)($f['attrs'] ?? []);
            $attrHtml = $this->attrsToHtml($attrs);
            if ($type === 'hidden') {
                echo '<input type="hidden" id="' . $h($fid) . '" value=""' . $attrHtml . '>';
                continue;
            }
            if ($type === 'checkbox') {
                echo '<div class="mb-2 form-check"><input type="checkbox" class="form-check-input" id="' . $h($fid) . '" value="1"' . $attrHtml . '><label for="' . $h($fid) . '" class="form-check-label">' . $h($label) . '</label></div>';
                continue;
            }
            $placeholder = (string)($f['placeholder'] ?? '');
            $maxlength = (int)($f['maxlength'] ?? 200);
            if ($type === 'textarea') {
                $rows = (int)($f['rows'] ?? 3);
                echo '<div class="mb-2"><label for="' . $h($fid) . '" class="form-label">' . $h($label) . '</label>';
                echo '<textarea class="form-control" id="' . $h($fid) . '" rows="' . $rows . '" placeholder="' . $h($placeholder) . '"' . $attrHtml . '></textarea></div>';
                continue;
            }
            $input_type = $type === 'password' ? 'password' : ($type === 'email' ? 'email' : ($type === 'number' ? 'number' : 'text'));
            echo '<div class="mb-2"><label for="' . $h($fid) . '" class="form-label">' . $h($label) . '</label>';
            echo '<input type="' . $h($input_type) . '" class="form-control" id="' . $h($fid) . '" placeholder="' . $h($placeholder) . '" maxlength="' . $maxlength . '"' . ($input_type === 'password' ? ' autocomplete="new-password"' : '') . $attrHtml . '></div>';
        }
        echo '</div><div class="modal-footer">';
        echo '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . $h($this->t('common.cancel')) . '</button>';
        echo '<button type="button" class="btn btn-primary" id="' . $h($save_btn_id) . '">' . $h($this->t('common.save')) . '</button>';
        echo '</div></div></div></div>';
    }

    private function t(string $key): string
    {
        return Sogerien::Lang()->get($key);
    }

    private function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param array<int,array<string,mixed>> $options
     * @param array<int,mixed> $selectedVals
     */
    private function render_dropdown_multi_facet(
        string $id,
        string $name,
        string $label,
        array $options,
        array $selectedVals,
        bool $searchEnabled
    ): string {
        $txAll = $this->h($this->t('table.all'));
        $txClear = $this->h($this->t('table.clear'));
        $txSearch = $this->h($this->t('table.search'));
        $facetId = $id;

        ob_start();
        ?>
        <div class="tr-facet tr-facet--dropdown_multi tr-facet--slot-side tr-facet--match-exact tr-facet--compact" data-type="dropdown_multi" data-match="exact" data-form-facet="1">
            <div class="small text-muted"><?= $this->h($label) ?></div>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle tr-dd-toggle" type="button" id="<?= $this->h($facetId) ?>__btn" aria-expanded="false">
                    <span class="tr-dd-label"><?= $txAll ?></span>
                    <span class="badge bg-secondary-subtle text-secondary-emphasis tr-dd-count d-none">0</span>
                </button>
                <div class="dropdown-menu p-2" aria-labelledby="<?= $this->h($facetId) ?>__btn" style="min-width: 280px; max-height: 320px; overflow: auto; z-index: 9999;">
                    <div class="d-flex gap-2 mb-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary tr-dd-all"><?= $txAll ?></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary tr-dd-clear"><?= $txClear ?></button>
                    </div>
                    <?php if ($searchEnabled): ?>
                    <div class="mb-2">
                        <input type="text" class="form-control form-control-sm tr-dd-search" placeholder="<?= $txSearch ?>">
                    </div>
                    <?php endif; ?>
                    <?php foreach ($options as $opt): ?>
                        <?php
                        $ov = (string)($opt['id'] ?? $opt['value'] ?? '');
                        $ot = (string)($opt['text'] ?? $opt['title'] ?? $ov);
                        if ($ov === '') {
                            continue;
                        }
                        $oid = $facetId . '__' . md5($ov);
                        $checked = in_array($ov, $selectedVals, true) ? ' checked' : '';
                        ?>
                        <div class="form-check">
                            <input class="form-check-input tr-dd-chk" type="checkbox" id="<?= $this->h($oid) ?>" name="<?= $this->h($name) ?>[]" value="<?= $this->h($ov) ?>"<?= $checked ?>>
                            <label class="form-check-label" for="<?= $this->h($oid) ?>"><?= $this->h($ot) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
        return (string)ob_get_clean();
    }
}
