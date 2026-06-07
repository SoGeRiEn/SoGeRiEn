<?php
declare(strict_types=1);

/**
 * ECharts - серверный помощник для генерации контейнеров и опций графиков.
 *
 * Методы:
 *  - line_stack, area_stack, line_race, mix_line_bar, bar_race_country, pie_legend
 *    принимают структурированные массивы и собирают option для ECharts.
 *  - geo_*, map_* принимают готовый option-массив, валидируют базовую структуру и рендерят как есть.
 *
 * Все методы выводят HTML-контейнер <div> и inline-скрипт инициализации графика.
 */

final class ECharts
{
    use SogerienClassHelp;

    /**
     * HTML-экранирование.
     */
    public static function h(?string $v): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return   Sogerien::Debager()->capture_return(htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), __CLASS__, __FUNCTION__);
}

    /**
     * Нормализация id DOM-элемента (только буквы/цифры/-/_).
     */
    private function normalize_id(string $id): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $idNorm = preg_replace('~[^a-zA-Z0-9_\-]~', '_', $id);
        if ($idNorm === null || $idNorm === '') {
            $idNorm = 'echart_' . bin2hex(random_bytes(4));
        }
        return Sogerien::Debager()->capture_return($idNorm, __CLASS__, __FUNCTION__);
    }

    /**
     * Базовый рендер: контейнер + скрипт инициализации с option.
     *
     * @param array<string,mixed> $option
     */
    private function render_chart(string $domId, array $option, ?string $style = null): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } if (empty($option)) {
            echo '<!-- ECharts: empty option -->';
            do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
        }

        if (!isset($option['series']) && !isset($option['dataset'])) {
            echo '<!-- ECharts: option without series/dataset -->';
            do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
        }

        $idNorm = $this->normalize_id($domId);

        $json = json_encode($option, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            echo '<!-- ECharts: json_encode failed -->';
            do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
        }

        $styleAttr = $style !== null && $style !== ''
            ? $style
            : 'width:100%;min-height:320px;';

        echo '<div id="' . self::h($idNorm) . '" style="' . self::h($styleAttr) . '"></div>';

        echo "<script>(function(){"
            . "if(typeof echarts==='undefined'){do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);}"
            . "var el=document.getElementById(" . json_encode($idNorm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ");"
            . "if(!el){do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);}"
            . "var chart=echarts.getInstanceByDom(el)||echarts.init(el);"
            . "var option=" . $json . ";"
            . "chart.setOption(option,true);"
            . "
})();</script>";
    }

    /**
     * Валидация категорий/серий для линейных/стековых графиков.
     *
     * @param array<int,string|int|float>           $categories
     * @param array<int,array<string,mixed>>        $series
     */
    private function validate_line_like(array $categories, array $series): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } if ($categories === [] || $series === []) {
            throw new \InvalidArgumentException('ECharts: categories and series must be non-empty');
        }

        $n = count($categories);

        foreach ($series as $idx => $s) {
            if (!is_array($s)) {
                throw new \InvalidArgumentException('ECharts: each series must be array, index ' . $idx);
            }
            if (!array_key_exists('name', $s)) {
                throw new \InvalidArgumentException('ECharts: series["name"] required, index ' . $idx);
            }
            if (!array_key_exists('data', $s) || !is_array($s['data'])) {
                throw new \InvalidArgumentException('ECharts: series["data"] array required, index ' . $idx);
            }
            if (count($s['data']) !== $n) {
                throw new \InvalidArgumentException('ECharts: series["data"] length must equal categories length, index ' . $idx);
            }
        }
    }

    /**
     * line-stack - стековые линии.
     *
     * @param array<int,string|int|float>    $categories   подписи по оси X
     * @param array<int,array<string,mixed>> $series       [
     *      ['name'=>'Email','data'=>[120,132,...],'stack'=>'total'],
     *      ...
     * ]
     * @param array<string,mixed>            $extraOptions доп. опции поверх базовых (array_replace_recursive)
     */
    public function line_stack(string $domId, array $categories, array $series, array $extraOptions = []): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $this->validate_line_like($categories, $series);

        $legend = [];
        foreach ($series as $s) {
            $legend[] = (string)($s['name'] ?? '');
        }

        $seriesCfg = [];
        foreach ($series as $s) {
            /** @var array<int,int|float|string> $data */
            $data = array_values((array)($s['data'] ?? []));
            $seriesCfg[] = [
                'name'  => (string)$s['name'],
                'type'  => 'line',
                'stack' => (string)($s['stack'] ?? 'total'),
                'smooth' => (bool)($s['smooth'] ?? false),
                'data'  => $data,
            ];
        }

        $base = [
            'title'   => ['text' => 'line-stack'],
            'tooltip' => ['trigger' => 'axis'],
            'legend'  => ['data' => $legend],
            'toolbox' => ['feature' => ['saveAsImage' => new \stdClass()]],
            'grid'    => ['left' => '3%', 'right' => '4%', 'bottom' => '3%', 'containLabel' => true],
            'xAxis'   => [
                [
                    'type'       => 'category',
                    'boundaryGap'=> false,
                    'data'       => array_values($categories),
                ],
            ],
            'yAxis'   => [
                ['type' => 'value'],
            ],
            'series'  => $seriesCfg,
        ];

        $option = $extraOptions ? array_replace_recursive($base, $extraOptions) : $base;
        $this->render_chart($domId, $option);
    }

    /**
     * area-stack - стековые области (areaStyle).
     *
     * Схема параметров такая же, как у line_stack.
     *
     * @param array<int,string|int|float>    $categories
     * @param array<int,array<string,mixed>> $series
     * @param array<string,mixed>            $extraOptions
     */
    public function area_stack(string $domId, array $categories, array $series, array $extraOptions = []): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $this->validate_line_like($categories, $series);

        $legend = [];
        foreach ($series as $s) {
            $legend[] = (string)($s['name'] ?? '');
        }

        $seriesCfg = [];
        foreach ($series as $s) {
            $data = array_values((array)($s['data'] ?? []));
            $seriesCfg[] = [
                'name'      => (string)$s['name'],
                'type'      => 'line',
                'stack'     => (string)($s['stack'] ?? 'total'),
                'smooth'    => (bool)($s['smooth'] ?? false),
                'showSymbol'=> (bool)($s['showSymbol'] ?? false),
                'areaStyle' => new \stdClass(),
                'emphasis'  => ['focus' => 'series'],
                'data'      => $data,
            ];
        }

        $base = [
            'title'   => ['text' => 'area-stack'],
            'tooltip' => ['trigger' => 'axis', 'axisPointer' => ['type' => 'cross', 'label' => ['backgroundColor' => '#6a7985']]],
            'legend'  => ['data' => $legend],
            'toolbox' => ['feature' => ['saveAsImage' => new \stdClass()]],
            'grid'    => ['left' => '3%', 'right' => '4%', 'bottom' => '3%', 'containLabel' => true],
            'xAxis'   => [
                [
                    'type'       => 'category',
                    'boundaryGap'=> false,
                    'data'       => array_values($categories),
                ],
            ],
            'yAxis'   => [
                ['type' => 'value'],
            ],
            'series'  => $seriesCfg,
        ];

        $option = $extraOptions ? array_replace_recursive($base, $extraOptions) : $base;
        $this->render_chart($domId, $option);
    }

    /**
     * drbjyx568x — обобщённый "line stack" (внутренний id примера).
     *
     * Для упрощения делаем ту же схему параметров, что и line_stack,
     * но с чуть другими настройками анимации по умолчанию.
     *
     * @param array<int,string|int|float>    $categories
     * @param array<int,array<string,mixed>> $series
     * @param array<string,mixed>            $extraOptions
     */
    public function drbjyx568x(string $domId, array $categories, array $series, array $extraOptions = []): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $this->validate_line_like($categories, $series);

        $base = [
            'title'              => ['text' => 'drbjyx568x'],
            'animationDuration'  => 800,
            'animationEasing'    => 'cubicOut',
        ];

        $extra = $extraOptions;
        $extra['xAxis'][0]['data'] = array_values($categories);
        $extra['series'] = $series;

        $option = array_replace_recursive($base, $extra);
        $this->render_chart($domId, $option);
    }

    /**
     * line-race — "гонка" для временных рядов.
     *
     * Ожидаются данные формата:
     *  - categories: список временных меток / шагов
     *  - series: как в line_stack, но можно добавить 'showSymbol'=>false
     *
     * @param array<int,string|int|float>    $categories
     * @param array<int,array<string,mixed>> $series
     * @param array<string,mixed>            $extraOptions
     */
    public function line_race(string $domId, array $categories, array $series, array $extraOptions = []): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $this->validate_line_like($categories, $series);

        $legend = [];
        foreach ($series as $s) {
            $legend[] = (string)($s['name'] ?? '');
        }

        $seriesCfg = [];
        foreach ($series as $s) {
            $data = array_values((array)($s['data'] ?? []));
            $seriesCfg[] = [
                'name'       => (string)$s['name'],
                'type'       => 'line',
                'showSymbol' => (bool)($s['showSymbol'] ?? false),
                'smooth'     => (bool)($s['smooth'] ?? true),
                'data'       => $data,
            ];
        }

        $base = [
            'title'   => ['text' => 'line-race'],
            'tooltip' => ['trigger' => 'axis'],
            'legend'  => ['data' => $legend],
            'xAxis'   => [['type' => 'category', 'data' => array_values($categories)]],
            'yAxis'   => [['type' => 'value']],
            'dataZoom'=> [
                ['type' => 'slider', 'start' => 0, 'end' => 100],
                ['type' => 'inside', 'start' => 0, 'end' => 100],
            ],
            'series'  => $seriesCfg,
        ];

        $option = $extraOptions ? array_replace_recursive($base, $extraOptions) : $base;
        $this->render_chart($domId, $option);
    }

    /**
     * mix-line-bar — комбинированный график "столбцы + линия".
     *
     * @param array<int,string|int|float> $categories
     * @param array<string,mixed>         $bars   ['name'=>'Evaporation','data'=>[...]]
     * @param array<string,mixed>         $line   ['name'=>'Average','data'=>[...]]
     * @param array<string,mixed>         $extraOptions
     */
    public function mix_line_bar(string $domId, array $categories, array $bars, array $line, array $extraOptions = []): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $this->validate_line_like($categories, [$bars, $line]);

        $base = [
            'title'   => ['text' => 'mix-line-bar'],
            'tooltip' => ['trigger' => 'axis', 'axisPointer' => ['type' => 'cross']],
            'legend'  => ['data' => [(string)($bars['name'] ?? 'Bars'), (string)($line['name'] ?? 'Line')]],
            'xAxis'   => [
                [
                    'type' => 'category',
                    'data' => array_values($categories),
                ],
            ],
            'yAxis'   => [
                ['type' => 'value', 'name' => (string)($bars['name'] ?? 'Bars')],
                ['type' => 'value', 'name' => (string)($line['name'] ?? 'Line')],
            ],
            'series'  => [
                [
                    'name' => (string)($bars['name'] ?? 'Bars'),
                    'type' => 'bar',
                    'data' => array_values((array)($bars['data'] ?? [])),
                ],
                [
                    'name'  => (string)($line['name'] ?? 'Line'),
                    'type'  => 'line',
                    'yAxisIndex' => 1,
                    'data'  => array_values((array)($line['data'] ?? [])),
                ],
            ],
        ];

        $option = $extraOptions ? array_replace_recursive($base, $extraOptions) : $base;
        $this->render_chart($domId, $option);
    }

    /**
     * bar-race-country — гонка баров по странам (один шаг времени).
     *
     * @param array<int,array{label:string,value:float|int}> $items
     * @param array<string,mixed>                            $extraOptions
     */
    public function bar_race_country(string $domId, array $items, array $extraOptions = []): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } if ($items === []) {
            throw new \InvalidArgumentException('ECharts: items must be non-empty for bar_race_country');
        }

        $labels = [];
        $values = [];
        foreach ($items as $idx => $it) {
            if (!is_array($it) || !array_key_exists('label', $it) || !array_key_exists('value', $it)) {
                throw new \InvalidArgumentException('ECharts: item must have label/value, index ' . $idx);
            }
            $labels[] = (string)$it['label'];
            $values[] = (float)$it['value'];
        }

        $base = [
            'title'   => ['text' => 'bar-race-country'],
            'tooltip' => ['trigger' => 'axis', 'axisPointer' => ['type' => 'shadow']],
            'xAxis'   => ['type' => 'value'],
            'yAxis'   => [
                [
                    'type' => 'category',
                    'inverse' => true,
                    'data' => $labels,
                ],
            ],
            'series'  => [
                [
                    'type' => 'bar',
                    'data' => $values,
                ],
            ],
            'animationDuration' => 800,
        ];

        $option = $extraOptions ? array_replace_recursive($base, $extraOptions) : $base;
        $this->render_chart($domId, $option, 'width:100%;min-height:400px;');
    }

    /**
     * pie-legend — круговая диаграмма с легендой.
     *
     * @param array<int,array{name:string,value:float|int}> $items
     * @param array<string,mixed>                           $extraOptions
     */
    public function pie_legend(string $domId, array $items, array $extraOptions = []): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } if ($items === []) {
            throw new \InvalidArgumentException('ECharts: items must be non-empty for pie_legend');
        }

        $legend = [];
        $data = [];
        foreach ($items as $idx => $it) {
            if (!is_array($it) || !array_key_exists('name', $it) || !array_key_exists('value', $it)) {
                throw new \InvalidArgumentException('ECharts: item must have name/value, index ' . $idx);
            }
            $legend[] = (string)$it['name'];
            $data[] = [
                'name'  => (string)$it['name'],
                'value' => (float)$it['value'],
            ];
        }

        $base = [
            'title'   => ['text' => 'pie-legend'],
            'tooltip' => ['trigger' => 'item'],
            'legend'  => ['orient' => 'vertical', 'left' => 'left', 'data' => $legend],
            'series'  => [
                [
                    'type' => 'pie',
                    'radius' => '70%',
                    'center' => ['60%', '50%'],
                    'data' => $data,
                    'emphasis' => [
                        'itemStyle' => [
                            'shadowBlur' => 10,
                            'shadowOffsetX' => 0,
                            'shadowColor' => 'rgba(0, 0, 0, 0.5)',
                        ],
                    ],
                ],
            ],
        ];

        $option = $extraOptions ? array_replace_recursive($base, $extraOptions) : $base;
        $this->render_chart($domId, $option);
    }

    /**
     * geo-choropleth-scatter — ожидание готового option для карты с точками.
     *
     * @param array<string,mixed> $option полный option-конфиг (с geo/map и series)
     */
    public function geo_choropleth_scatter(string $domId, array $option): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $this->render_chart($domId, $option, 'width:100%;min-height:480px;');
    }

    /**
     * map-iceland-pie — карта с круговыми диаграммами.
     *
     * @param array<string,mixed> $option
     */
    public function map_iceland_pie(string $domId, array $option): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $this->render_chart($domId, $option, 'width:100%;min-height:480px;');
    }

    /**
     * geo-svg-scatter-simple — гео по SVG, принимаем готовый option.
     *
     * @param array<string,mixed> $option
     */
    public function geo_svg_scatter_simple(string $domId, array $option): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $this->render_chart($domId, $option, 'width:100%;min-height:480px;');
    }

    /**
     * map-HK — карта Гонконга, принимаем готовый option.
     *
     * @param array<string,mixed> $option
     */
    public function map_hk(string $domId, array $option): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $this->render_chart($domId, $option, 'width:100%;min-height:480px;');
    }
}

