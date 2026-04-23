/* table_renderer.js
 *
 * РўСЂРµР±РѕРІР°РЅРёСЏ:
 * - jQuery
 * - DataTables + Buttons (excelHtml5, print)
 * - Bootstrap CSS (РґР»СЏ РєР»Р°СЃСЃРѕРІ), Bootstrap JS РЅРµ РѕР±СЏР·Р°С‚РµР»РµРЅ (РјС‹ РЅРµ РёСЃРїРѕР»СЊР·СѓРµРј data-bs-toggle)
 *
 * РљР°Рє РїРѕРґРєР»СЋС‡Р°С‚СЊ РІ PHP:
 * 1) РћРґРёРЅ СЂР°Р· РЅР° СЃС‚СЂР°РЅРёС†Сѓ:
 *    <script src="/assets/table_renderer.js"></script>
 *
 * 2) Р”Р»СЏ РєР°Р¶РґРѕРіРѕ РіСЂРёРґР° РґРѕР±Р°РІСЊ РјР°СЂРєРµСЂ-РєРѕРЅС‚РµР№РЅРµСЂ:
 *    <div class="tr-grid"
 *         data-tr-gid="<?= self::h($this->gridId) ?>"
 *         data-tr-per-page="<?= (int)$this->perPage ?>"
 *         data-tr-csrf="<?= self::h($_SESSION['csrf'] ?? '') ?>">
 *    </div>
 *
 * Р РѕСЃС‚Р°РІСЊ id-С€РЅРёРєРё РєР°Рє СЃРµР№С‡Р°СЃ: #{gid}__tbl, #{gid}__search, #{gid}__facets, #{gid}__cols, #{gid}__reset, #{gid}__modal Рё С‚.Рґ.
 */

(function () {
    'use strict';

    function initGrid(cfg) {
        const gid = String(cfg.gid || '');
        if (!gid) return;
        const t = function (key, fallback) {
            if (typeof window.sogerienLangGet === 'function') {
                return window.sogerienLangGet(key, fallback);
            }
            return fallback || key;
        };

        const perPage = Number.isFinite(cfg.perPage) ? cfg.perPage : parseInt(String(cfg.perPage || '100'), 10) || 100;
        const csrf = String(cfg.csrf || '');
        const multiselectMsgId = String(cfg.multiselectMsgId || '');
        const resetQueryParams = Array.isArray(cfg.resetQueryParams)
            ? cfg.resetQueryParams.map(x => String(x || '').trim()).filter(Boolean)
            : [];

        const $tbl = $('#' + gid + '__tbl');
        if (!$tbl.length) return;

        // =========================
        // URL scope + cookieKey
        // =========================
        const url = new URL(location.href);
        const scope = url.searchParams.get('scope') || 'all';

        const cookieKey = String(`${gid}__state__${scope}__${location.pathname}`)
            .replace(/[^a-z0-9_\-:.]/gi, '_');

        // =========================
        // Utils
        // =========================
        const U = {
            debounce(fn, wait = 1000) {
                let timerId = null;
                return function (...args) {
                    if (timerId) {
                        clearTimeout(timerId);
                    }
                    timerId = setTimeout(() => {
                        timerId = null;
                        fn.apply(this, args);
                    }, wait);
                };
            },
            setCookie(name, value, days = 365) {
                const d = new Date();
                d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
                document.cookie = [
                    `${encodeURIComponent(name)}=${encodeURIComponent(value)}`,
                    `expires=${d.toUTCString()}`,
                    'path=/',
                    'SameSite=Lax'
                ].join('; ');
            },
            getCookie(name) {
                const key = encodeURIComponent(name) + '=';
                const ca = document.cookie.split(';');
                for (let c of ca) {
                    c = c.trim();
                    if (c.indexOf(key) === 0) return decodeURIComponent(c.substring(key.length));
                }
                return '';
            },
            deleteCookie(name) {
                document.cookie = [
                    `${encodeURIComponent(name)}=`,
                    'expires=Thu, 01 Jan 1970 00:00:00 GMT',
                    'path=/',
                    'SameSite=Lax'
                ].join('; ');
            },
            deleteCookiesByPrefix(prefix) {
                const encPref = encodeURIComponent(prefix);
                document.cookie.split(';').forEach(raw => {
                    const parts = raw.trim().split('=');
                    const name = parts[0] || '';
                    if (name && name.startsWith(encPref)) {
                        U.deleteCookie(decodeURIComponent(name));
                    }
                });
            },
            buildUrlWithoutQueryParams(names) {
                if (!Array.isArray(names) || !names.length) return '';

                const url = new URL(location.href);
                let changed = false;
                names.forEach(name => {
                    const key = String(name || '').trim();
                    if (!key) return;
                    if (url.searchParams.has(key)) {
                        url.searchParams.delete(key);
                        changed = true;
                    }
                });

                if (!changed) return '';
                const qs = url.searchParams.toString();
                return url.pathname + (qs ? ('?' + qs) : '') + url.hash;
            },
            escapeRegex(s) {
                return (s || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            },
            cssEscape(s) {
                return (s || '').replace(/(["'\\])/g, '\\$1');
            },
            normalizeFacetVal(v) {
                if (v === null || typeof v === 'undefined') return [];
                if (Array.isArray(v)) return v.filter(x => typeof x === 'string' && x !== '');
                if (typeof v === 'string' && v !== '') return [v];
                return [];
            },
            buildRegexFromSelected(arr, match = 'exact') {
                const a = U.normalizeFacetVal(arr);
                if (!a.length) return '';
                const parts = a.map(x => U.escapeRegex(x));
                if (match === 'contains') {
                    return '(?:' + parts.join('|') + ')';
                }
                if (match === 'csv_token') {
                    return '(?:^|,\\s*)(?:' + parts.join('|') + ')(?:\\s*,|$)';
                }
                return '^(?:' + parts.join('|') + ')$';
            },
            setDropdownLabel(box, selected) {
                const btnLabel = box.querySelector('.tr-dd-label');
                const badge = box.querySelector('.tr-dd-count');
                const n = U.normalizeFacetVal(selected).length;

                if (!btnLabel) return;

                if (!n) {
                    btnLabel.textContent = t('table.all', 'All');
                    if (badge) badge.classList.add('d-none');
                    return;
                }
                btnLabel.textContent = t('common.selected', 'Selected');
                if (badge) {
                    badge.textContent = String(n);
                    badge.classList.remove('d-none');
                }
            },
            setSingleDropdownLabel(box, selected, fallback = t('table.all', 'All')) {
                if (!box) return;
                const label = box.querySelector('.tr-range-dd-label');
                if (!label) return;
                const text = String(selected ?? '');
                label.textContent = text !== '' ? text : fallback;
            }
        };

        // =========================
        // GridState (datatable + persistence)
        // =========================
        const GridState = (function () {
            const defaultState = {
                colsVisible: null,
                searchText: '',
                facets: {},
                pageLength: perPage,
                order: [],
                page: 0
            };
            let state = Object.assign({}, defaultState);

            try {
                const raw = U.getCookie(cookieKey);
                if (raw) state = Object.assign({}, state, JSON.parse(raw));
            } catch (e) {}

            if (!state.facets || typeof state.facets !== 'object' || Array.isArray(state.facets)) state.facets = {};
            if (!Array.isArray(state.order)) state.order = [];
            if (!Number.isFinite(parseInt(String(state.pageLength || ''), 10))) state.pageLength = perPage;
            if (!Number.isFinite(parseInt(String(state.page || ''), 10))) state.page = 0;

            function persist() {
                try { U.setCookie(cookieKey, JSON.stringify(state)); } catch (e) {}
            }

            function findColIdxByName(colName) {
                const ths = Array.from($tbl.find('thead th'));
                return ths.findIndex(th => (th.getAttribute('data-col') || '') === colName);
            }

            const actionsIdx = $tbl.find('thead th').toArray()
                .findIndex(th => (th.getAttribute('data-col') || '') === 'actions');

        function initSelect2MultiselectInGrid() {
            const $t = $('#' + gid + '__tbl');
            $t.find('.tr-select2-multiselect, .tr-range-from-select, .tr-range-to-select').each(function () {
                const $el = $(this);
                if ($el.data('select2')) {
                    try { $el.select2('destroy'); } catch (e) {}
                }
                if (typeof $el.select2 === 'function') {
                    const $modal = $el.closest('.modal');
                    const options = {
                        theme: 'bootstrap-5',
                        width: '100%',
                        dropdownAutoWidth: true
                    };
                    options.dropdownParent = $modal.length ? $modal : $(document.body);
                    if ($el.hasClass('tr-range-from-select') || $el.hasClass('tr-range-to-select')) {
                        options.minimumResultsForSearch = Infinity;
                    }
                    $el.select2(options);
                }
            });
        }

        function initLengthSelectInGrid() {
            const $wrapper = $('#' + gid + '__tbl_wrapper');
            if (!$wrapper.length) return;

            $wrapper.find('div.dataTables_length select').each(function () {
                const $el = $(this);
                if (typeof $el.select2 !== 'function') return;
                if ($el.data('select2')) return;

                $el.select2({
                    theme: 'bootstrap-5',
                    width: 'style',
                    minimumResultsForSearch: Infinity,
                    dropdownParent: $wrapper,
                    selectionCssClass: 'pm-dt-length-select2-selection',
                    dropdownCssClass: 'pm-dt-length-select2-dropdown'
                });
            });
        }

            const dt = $tbl.DataTable({
                pageLength: state.pageLength || perPage,
                lengthMenu: [[10, 25, 50, 100, 250, 500, 1000], [10, 25, 50, 100, 250, 500, 1000]],
                order: Array.isArray(state.order) ? state.order : [],
                responsive: true,
                processing: false,
                serverSide: false,
                drawCallback: function () {
                    initSelect2MultiselectInGrid();
                    initLengthSelectInGrid();
                },
                dom:
                    '<"row mb-2"<"col-sm-12 col-md-6"l>>' +
                    't' +
                    '<"row mt-2"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                language: {
                    lengthMenu: t('table.dt.length_menu', 'Rows per page _MENU_'),
                    search: t('table.dt.search', 'Search:'),
                    zeroRecords: t('table.dt.zero_records', 'Nothing found'),
                    info: t('table.dt.info', 'Showing _START_ to _END_ of _TOTAL_'),
                    infoEmpty: t('table.dt.info_empty', 'No data'),
                    infoFiltered: t('table.dt.info_filtered', '(filtered from _MAX_)'),
                    paginate: { previous: t('table.dt.previous', 'Previous'), next: t('table.dt.next', 'Next') },
                    processing: t('table.dt.processing', 'Processing...')
                },
                columnDefs: [
                    ...(actionsIdx >= 0 ? [{
                        targets: actionsIdx,
                        orderable: false,
                        searchable: false
                    }] : [])
                ]
            });

            initLengthSelectInGrid();

            // restore columns visibility + listeners
            (function initColumns() {
                const colCbs = Array.from(document.querySelectorAll('#' + gid + '__cols .tr-colchk'));

                if (Array.isArray(state.colsVisible) && state.colsVisible.length === dt.columns().count()) {
                    state.colsVisible.forEach((vis, idx) => {
                        dt.column(idx).visible(!!vis);
                        if (colCbs[idx]) colCbs[idx].checked = !!vis;
                    });
                } else {
                    state.colsVisible = [];
                    for (let i = 0; i < dt.columns().count(); i++) {
                        const isChecked = colCbs[i] ? !!colCbs[i].checked : dt.column(i).visible();
                        dt.column(i).visible(isChecked);
                        state.colsVisible[i] = isChecked;
                    }
                }

                colCbs.forEach(cb => {
                    const idx = parseInt(cb.dataset.colIdx || '-1', 10);
                    cb.addEventListener('change', () => {
                        if (idx < 0) return;
                        dt.column(idx).visible(cb.checked);
                        state.colsVisible[idx] = !!cb.checked;
                        persist();
                    });
                });
            })();

            // search restore + listener
            (function initSearch() {
                const el = document.getElementById(gid + '__search');
                if (!el) return;

                if (state.searchText) {
                    el.value = state.searchText;
                    dt.search(state.searchText).draw();
                }

                const applySearchDebounced = U.debounce(() => {
                    state.searchText = el.value;
                    dt.search(state.searchText).draw();
                    persist();
                }, 1000);

                el.addEventListener('input', applySearchDebounced);
            })();

            // facets restore + listeners
            (function initFacets() {
                const root = document.getElementById(gid + '__facets');
                if (!root) return;
                const facetBoxes = Array.from(root.querySelectorAll('.tr-facet'));

                const parseCsvTokens = (raw) => {
                    if (!raw) return [];
                    return String(raw)
                        .split(',')
                        .map(x => x.trim().toLowerCase())
                        .filter(Boolean);
                };

                const rowValueByCol = (rowNode, colName) => {
                    if (!rowNode) return '';
                    return String(rowNode.getAttribute('data-' + colName) || '');
                };

                const selectedForFacet = (box) => {
                    const colName = String(box.dataset.col || '');
                    const type = String(box.dataset.type || 'buttons').toLowerCase();
                    if (type === 'dropdown_multi') return U.normalizeFacetVal(state.facets[colName]);
                    if (type === 'range_number' || type === 'range_date') {
                        const r = state.facets[colName];
                        return (r && typeof r === 'object') ? { from: String(r.from || ''), to: String(r.to || '') } : { from: '', to: '' };
                    }
                    const one = String(state.facets[colName] || '');
                    return one !== '' ? [one] : [];
                };

                const matchOne = (raw, needle, match) => {
                    const n = String(needle || '').toLowerCase();
                    if (n === '') return true;

                    const hay = String(raw || '');
                    const hayLower = hay.toLowerCase();

                    if (match === 'csv_token') {
                        return parseCsvTokens(hay).includes(n);
                    }
                    if (match === 'contains') {
                        return hayLower.indexOf(n) !== -1;
                    }
                    return hayLower === n;
                };

                const matchAny = (raw, needles, match) => {
                    if (!Array.isArray(needles) || needles.length === 0) return true;
                    for (const n of needles) {
                        if (matchOne(raw, n, match)) return true;
                    }
                    return false;
                };

                const allRowNodes = () => {
                    const nodesApi = dt.rows({ search: 'none', page: 'all' }).nodes();
                    return Array.from(nodesApi || []);
                };

                const parseRowValueForRange = (raw, type) => {
                    const s = String(raw || '').trim();
                    if (s === '') return null;
                    if (type === 'range_number') {
                        const n = parseFloat(s);
                        return Number.isFinite(n) ? n : null;
                    }
                    if (type === 'range_date') {
                        const m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
                        if (m) return m[1] + '-' + m[2] + '-' + m[3];
                        const d = new Date(s);
                        return !isNaN(d.getTime()) ? d.toISOString().slice(0, 10) : null;
                    }
                    return null;
                };

                const normalizeRangeBoundary = (raw, type) => {
                    const s = String(raw || '').trim();
                    if (s === '') return '';
                    if (type === 'range_number') {
                        const n = parseFloat(s);
                        return Number.isFinite(n) ? String(n) : '';
                    }
                    if (type === 'range_date') {
                        const d = parseRowValueForRange(s, type);
                        return d === null ? '' : String(d);
                    }
                    return s;
                };

                const compareRangeValues = (left, right, type) => {
                    if (type === 'range_number') return Number(left) - Number(right);
                    if (String(left) === String(right)) return 0;
                    return String(left) > String(right) ? 1 : -1;
                };

                const rowMatchesRange = (raw, range, type) => {
                    const fromVal = normalizeRangeBoundary(range && range.from ? range.from : '', type);
                    const toVal = normalizeRangeBoundary(range && range.to ? range.to : '', type);
                    if (fromVal === '' && toVal === '') return true;

                    const rowVal = parseRowValueForRange(raw, type);
                    if (rowVal === null) return false;

                    if (fromVal !== '' && compareRangeValues(rowVal, type === 'range_number' ? parseFloat(fromVal) : fromVal, type) < 0) {
                        return false;
                    }
                    if (toVal !== '' && compareRangeValues(rowVal, type === 'range_number' ? parseFloat(toVal) : toVal, type) > 0) {
                        return false;
                    }
                    return true;
                };

                const rowMatchesFacetSelection = (rowNode, exceptCol = '', rangeOverrides = null) => {
                    for (const box of facetBoxes) {
                        const colName = String(box.dataset.col || '');
                        if (!colName || colName === exceptCol) continue;

                        const type = String(box.dataset.type || 'buttons').toLowerCase();
                        let selected = selectedForFacet(box);
                        if (rangeOverrides && Object.prototype.hasOwnProperty.call(rangeOverrides, colName)) {
                            selected = rangeOverrides[colName];
                        }

                        if (type === 'range_number' || type === 'range_date') {
                            const raw = rowValueByCol(rowNode, colName);
                            const range = selected && typeof selected === 'object'
                                ? selected
                                : { from: '', to: '' };
                            if (!rowMatchesRange(raw, range, type)) return false;
                            continue;
                        }

                        const match = String(box.dataset.match || 'exact').toLowerCase();
                        if (!selected.length) continue;
                        const raw = rowValueByCol(rowNode, colName);
                        if (!matchAny(raw, selected, match)) return false;
                    }
                    return true;
                };

                $.fn.dataTable.ext.search.push((settings, data, dataIndex) => {
                    const tableNode = settings && settings.nTable ? settings.nTable : null;
                    if (!tableNode || tableNode.id !== gid + '__tbl') {
                        return true;
                    }

                    const rowNode = dt.row(dataIndex).node();
                    if (!rowNode) {
                        return true;
                    }

                    return rowMatchesFacetSelection(rowNode);
                });

                const optionAvailableForFacet = (box, optionValue, rows) => {
                    const colName = String(box.dataset.col || '');
                    const match = String(box.dataset.match || 'exact').toLowerCase();
                    if (!colName) return true;

                    for (const rowNode of rows) {
                        if (!rowMatchesFacetSelection(rowNode, colName)) continue;
                        const raw = rowValueByCol(rowNode, colName);
                        if (matchOne(raw, optionValue, match)) return true;
                    }
                    return false;
                };

                const rangeOptionAvailable = (box, range, rows) => {
                    const colName = String(box.dataset.col || '');
                    if (!colName) return true;

                    const nextRange = {
                        from: String(range && range.from ? range.from : ''),
                        to: String(range && range.to ? range.to : '')
                    };
                    const rangeOverride = { [colName]: nextRange };

                    for (const rowNode of rows) {
                        if (rowMatchesFacetSelection(rowNode, '', rangeOverride)) {
                            return true;
                        }
                    }
                    return false;
                };

                const refreshRangeAvailability = (box, rangeType, rows) => {
                    const colName = String(box.dataset.col || '');
                    if (!colName) return;

                    const fromInput = box.querySelector('.tr-range-from');
                    const toInput = box.querySelector('.tr-range-to');
                    const fromSelect = box.querySelector('.tr-range-from-select');
                    const toSelect = box.querySelector('.tr-range-to-select');
                    const fromDd = box.querySelector('.tr-range-dd[data-side="from"]');
                    const toDd = box.querySelector('.tr-range-dd[data-side="to"]');
                    const fromButtons = fromDd ? Array.from(fromDd.querySelectorAll('.tr-range-option[data-value]')) : [];
                    const toButtons = toDd ? Array.from(toDd.querySelectorAll('.tr-range-option[data-value]')) : [];
                    const current = selectedForFacet(box);
                    const fromVal = normalizeRangeBoundary(current && current.from ? current.from : '', rangeType);
                    const toVal = normalizeRangeBoundary(current && current.to ? current.to : '', rangeType);

                    if (rangeType === 'range_number') {
                        const setButtonState = (btn, disabled) => {
                            btn.disabled = !!disabled;
                            btn.classList.toggle('tr-opt-unavailable', !!disabled);
                        };

                        const refreshButtons = (buttons, side) => {
                            buttons.forEach(btn => {
                                const v = normalizeRangeBoundary(String(btn.dataset.value || ''), rangeType);
                                if (v === '') {
                                    setButtonState(btn, false);
                                    return;
                                }

                                const candidate = {
                                    from: side === 'from' ? v : fromVal,
                                    to: side === 'to' ? v : toVal
                                };
                                const selectedSideVal = side === 'from' ? fromVal : toVal;
                                const isSelected = selectedSideVal !== '' && selectedSideVal === v;
                                const available = rangeOptionAvailable(box, candidate, rows);
                                setButtonState(btn, !available && !isSelected);
                            });
                        };

                        refreshButtons(fromButtons, 'from');
                        refreshButtons(toButtons, 'to');
                        return;
                    }

                    if (rangeType === 'range_date') {
                        if (fromInput) fromInput.max = toVal || '';
                        if (toInput) toInput.min = fromVal || '';
                    }

                    const setOptionState = (opt, disabled) => {
                        opt.disabled = !!disabled;
                        opt.classList.toggle('tr-opt-unavailable', !!disabled);
                    };

                    const refreshOptions = (options, side) => {
                        options.forEach(opt => {
                            const v = normalizeRangeBoundary(String(opt.value || ''), rangeType);
                            if (v === '') {
                                setOptionState(opt, false);
                                return;
                            }

                            const candidate = {
                                from: side === 'from' ? v : fromVal,
                                to: side === 'to' ? v : toVal
                            };
                            const selectedSideVal = side === 'from' ? fromVal : toVal;
                            const isSelected = selectedSideVal !== '' && selectedSideVal === v;
                            const available = rangeOptionAvailable(box, candidate, rows);
                            setOptionState(opt, !available && !isSelected);
                        });
                    };

                    if (fromSelect) {
                        refreshOptions(Array.from(fromSelect.querySelectorAll('option[value]')), 'from');
                    }
                    if (toSelect) {
                        refreshOptions(Array.from(toSelect.querySelectorAll('option[value]')), 'to');
                    }
                    if (fromInput && fromInput.list) {
                        refreshOptions(Array.from(fromInput.list.querySelectorAll('option[value]')), 'from');
                    }
                    if (toInput && toInput.list) {
                        refreshOptions(Array.from(toInput.list.querySelectorAll('option[value]')), 'to');
                    }
                };

                const refreshFacetAvailability = () => {
                    const rows = allRowNodes();
                    for (const box of facetBoxes) {
                        const type = String(box.dataset.type || 'buttons').toLowerCase();
                        if (type === 'range_number' || type === 'range_date') {
                            refreshRangeAvailability(box, type, rows);
                            continue;
                        }

                        if (type === 'dropdown_multi') {
                            box.querySelectorAll('.tr-dd-chk').forEach(chk => {
                                const val = String(chk.value || '');
                                const selected = chk.checked;
                                const available = optionAvailableForFacet(box, val, rows);
                                chk.disabled = !available && !selected;
                                const wrap = chk.closest('.form-check');
                                if (wrap) wrap.classList.toggle('tr-opt-unavailable', chk.disabled);
                            });
                            continue;
                        }

                        box.querySelectorAll('button[data-value]').forEach(btn => {
                            const val = String(btn.dataset.value || '');
                            if (val === '') {
                                btn.disabled = false;
                                btn.classList.remove('tr-opt-unavailable');
                                return;
                            }
                            const active = btn.classList.contains('active');
                            const available = optionAvailableForFacet(box, val, rows);
                            btn.disabled = !available && !active;
                            btn.classList.toggle('tr-opt-unavailable', btn.disabled);
                        });
                    }
                };

                const initRangeFacet = (box, rangeType) => {
                    const colName = String(box.dataset.col || '');
                    if (!colName) return;
                    if (!state.facets[colName] || typeof state.facets[colName] !== 'object') {
                        state.facets[colName] = { from: '', to: '' };
                    }

                    const r = state.facets[colName];
                    const isNumberRange = rangeType === 'range_number';
                    const fromInput = isNumberRange ? null : box.querySelector('.tr-range-from');
                    const toInput = isNumberRange ? null : box.querySelector('.tr-range-to');
                    const fromSelect = isNumberRange ? null : box.querySelector('.tr-range-from-select');
                    const toSelect = isNumberRange ? null : box.querySelector('.tr-range-to-select');
                    const fromDd = isNumberRange ? box.querySelector('.tr-range-dd[data-side="from"]') : null;
                    const toDd = isNumberRange ? box.querySelector('.tr-range-dd[data-side="to"]') : null;

                    const closeDropdown = (dd) => {
                        if (!dd) return;
                        dd.classList.remove('tr-dd-open');
                        const menu = dd.querySelector('.dropdown-menu');
                        if (menu) menu.classList.remove('show');
                        const btn = dd.querySelector('.tr-dd-toggle');
                        if (btn) btn.setAttribute('aria-expanded', 'false');
                    };

                    const getFromVal = () => {
                        if (isNumberRange) {
                            return String(r.from ?? '').trim();
                        }
                        return (fromInput ? String(fromInput.value || '').trim() : '') || (fromSelect ? String(fromSelect.value || '').trim() : '');
                    };

                    const getToVal = () => {
                        if (isNumberRange) {
                            return String(r.to ?? '').trim();
                        }
                        return (toInput ? String(toInput.value || '').trim() : '') || (toSelect ? String(toSelect.value || '').trim() : '');
                    };

                    const syncRangeControls = () => {
                        if (isNumberRange) {
                            const fromValue = normalizeRangeBoundary(String(r.from ?? ''), rangeType);
                            const toValue = normalizeRangeBoundary(String(r.to ?? ''), rangeType);

                            if (fromDd) {
                                U.setSingleDropdownLabel(fromDd, fromValue, t('table.all', 'All'));
                                fromDd.querySelectorAll('.tr-range-option').forEach(btn => {
                                    const value = normalizeRangeBoundary(String(btn.dataset.value || ''), rangeType);
                                    btn.classList.toggle('active', fromValue !== '' && value === fromValue);
                                });
                            }
                            if (toDd) {
                                U.setSingleDropdownLabel(toDd, toValue, t('table.all', 'All'));
                                toDd.querySelectorAll('.tr-range-option').forEach(btn => {
                                    const value = normalizeRangeBoundary(String(btn.dataset.value || ''), rangeType);
                                    btn.classList.toggle('active', toValue !== '' && value === toValue);
                                });
                            }
                            return;
                        }

                        if (fromInput) fromInput.value = r.from || '';
                        if (toInput) toInput.value = r.to || '';
                        if (fromSelect) fromSelect.value = r.from || '';
                        if (toSelect) toSelect.value = r.to || '';
                    };

                    const applyRange = (changedSide = 'from') => {
                        let nextFrom = normalizeRangeBoundary(getFromVal(), rangeType);
                        let nextTo = normalizeRangeBoundary(getToVal(), rangeType);

                        if (nextFrom !== '' && nextTo !== '') {
                            const fromCmp = rangeType === 'range_number' ? parseFloat(nextFrom) : nextFrom;
                            const toCmp = rangeType === 'range_number' ? parseFloat(nextTo) : nextTo;
                            if (compareRangeValues(fromCmp, toCmp, rangeType) > 0) {
                                if (changedSide === 'to') {
                                    nextFrom = nextTo;
                                } else {
                                    nextTo = nextFrom;
                                }
                            }
                        }

                        r.from = nextFrom;
                        r.to = nextTo;
                        syncRangeControls();
                        refreshFacetAvailability();
                    };

                    const drawRangeDebounced = U.debounce(() => {
                        dt.draw();
                        persist();
                    }, 1000);
                    const applyRangeAndScheduleDraw = (changedSide = 'from') => {
                        applyRange(changedSide);
                        drawRangeDebounced();
                    };

                    if (isNumberRange) {
                        const bindRangeDropdown = (dd, side) => {
                            if (!dd) return;

                            const setSideValue = (rawValue) => {
                                if (side === 'from') {
                                    r.from = normalizeRangeBoundary(rawValue, rangeType);
                                } else {
                                    r.to = normalizeRangeBoundary(rawValue, rangeType);
                                }
                                applyRangeAndScheduleDraw(side);
                                closeDropdown(dd);
                            };

                            const allBtn = dd.querySelector('.tr-range-all');
                            const clearBtn = dd.querySelector('.tr-range-clear');
                            const optionButtons = Array.from(dd.querySelectorAll('.tr-range-option[data-value]'));

                            if (allBtn) {
                                allBtn.addEventListener('click', (e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    setSideValue('');
                                });
                            }
                            if (clearBtn) {
                                clearBtn.addEventListener('click', (e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    setSideValue('');
                                });
                            }
                            optionButtons.forEach(btn => {
                                btn.addEventListener('click', (e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    if (btn.disabled) return;
                                    setSideValue(btn.dataset.value || '');
                                });
                            });
                        };

                        bindRangeDropdown(fromDd, 'from');
                        bindRangeDropdown(toDd, 'to');
                        syncRangeControls();
                        applyRange('from');
                        return;
                    }

                    if (fromInput) {
                        fromInput.value = normalizeRangeBoundary(r.from || '', rangeType);
                        fromInput.addEventListener('input', () => applyRangeAndScheduleDraw('from'));
                        fromInput.addEventListener('change', () => applyRangeAndScheduleDraw('from'));
                    }
                    if (toInput) {
                        toInput.value = normalizeRangeBoundary(r.to || '', rangeType);
                        toInput.addEventListener('input', () => applyRangeAndScheduleDraw('to'));
                        toInput.addEventListener('change', () => applyRangeAndScheduleDraw('to'));
                    }
                    if (fromSelect) {
                        fromSelect.value = normalizeRangeBoundary(r.from || '', rangeType);
                        fromSelect.addEventListener('change', () => {
                            if (fromInput) fromInput.value = fromSelect.value || '';
                            applyRangeAndScheduleDraw('from');
                        });
                    }
                    if (toSelect) {
                        toSelect.value = normalizeRangeBoundary(r.to || '', rangeType);
                        toSelect.addEventListener('change', () => {
                            if (toInput) toInput.value = toSelect.value || '';
                            applyRangeAndScheduleDraw('to');
                        });
                    }
                    applyRange('from');
                };

                facetBoxes.forEach(box => {
                    const colName = box.dataset.col || '';
                    const type = String(box.dataset.type || 'buttons').toLowerCase();
                    if (!colName) return;

                    if (type === 'range_number' || type === 'range_date') {
                        initRangeFacet(box, type);
                        return;
                    }

                    if (type === 'dropdown_multi') {
                        const selected = U.normalizeFacetVal(state.facets[colName]);
                        state.facets[colName] = selected;
                        const searchInput = box.querySelector('.tr-dd-search');

                        box.querySelectorAll('.tr-dd-chk').forEach(chk => {
                            chk.checked = selected.includes(chk.value);
                        });

                        const applySearchFilter = () => {
                            if (!searchInput) return;
                            const q = String(searchInput.value || '').trim().toLowerCase();
                            box.querySelectorAll('.form-check').forEach(wrap => {
                                const chk = wrap.querySelector('.tr-dd-chk');
                                if (!chk) return;
                                const lbl = wrap.querySelector('.form-check-label');
                                const hay = ((lbl ? lbl.textContent : '') || chk.value || '').toLowerCase();
                                const visible = q === '' || hay.indexOf(q) !== -1;
                                wrap.classList.toggle('d-none', !visible);
                            });
                        };

                        U.setDropdownLabel(box, selected);

                        const applySelection = () => {
                            const now = Array.from(box.querySelectorAll('.tr-dd-chk:checked')).map(x => x.value);
                            state.facets[colName] = now;
                            U.setDropdownLabel(box, now);
                            dt.draw();
                            persist();
                            refreshFacetAvailability();
                        };

                        const clear = () => {
                            box.querySelectorAll('.tr-dd-chk').forEach(chk => chk.checked = false);
                            state.facets[colName] = [];
                            U.setDropdownLabel(box, []);
                            dt.draw();
                            persist();
                            refreshFacetAvailability();
                        };

                        const selectAllVisible = () => {
                            const visibleChecks = Array.from(box.querySelectorAll('.form-check:not(.d-none) .tr-dd-chk'));
                            visibleChecks.forEach(chk => {
                                if (!chk.disabled) chk.checked = true;
                            });
                            applySelection();
                        };

                        const allBtn = box.querySelector('.tr-dd-all');
                        const clrBtn = box.querySelector('.tr-dd-clear');
                        if (allBtn) allBtn.addEventListener('click', selectAllVisible);
                        if (clrBtn) clrBtn.addEventListener('click', clear);

                        box.querySelectorAll('.tr-dd-chk').forEach(chk => {
                            chk.addEventListener('change', applySelection);
                        });

                        if (searchInput) {
                            searchInput.addEventListener('input', applySearchFilter);
                            applySearchFilter();
                        }

                        return;
                    }

                    // buttons facet
                    const val = state.facets[colName] || '';
                    const targetBtn = val
                        ? box.querySelector(`button[data-value="${U.cssEscape(val)}"]`)
                        : box.querySelector('button[data-value=""]');

                    if (targetBtn) {
                        box.querySelectorAll('button').forEach(b => b.classList.remove('active'));
                        targetBtn.classList.add('active');
                    }

                    box.addEventListener('click', (e) => {
                        const btn = e.target.closest('button[data-value]');
                        if (!btn) return;

                        box.querySelectorAll('button').forEach(b => b.classList.remove('active'));
                        btn.classList.add('active');

                        const v = btn.dataset.value || '';
                        state.facets[colName] = v;
                        dt.draw();
                        persist();
                        refreshFacetAvailability();
                    });
                });

                dt.on('draw.dt', refreshFacetAvailability);
                refreshFacetAvailability();
            })();

            // persist on length/page/order
            dt.on('length.dt', (e, settings, len) => { state.pageLength = len; persist(); });
            dt.on('page.dt', () => { state.page = dt.page(); persist(); });
            dt.on('order.dt', () => { state.order = dt.order(); persist(); });

            // restore page
            if (typeof state.page === 'number' && state.page >= 0) dt.page(state.page).draw('page');
            else dt.draw();

            // reset
            (function initReset() {
                const btn = document.getElementById(gid + '__reset');
                if (!btn) return;

                btn.addEventListener('click', () => {
                    U.deleteCookie(cookieKey);
                    U.deleteCookie(`${gid}_cols`);
                    U.deleteCookie(`${gid}_page`);
                    U.deleteCookie(`${gid}_facet`);

                    const redirectUrl = U.buildUrlWithoutQueryParams(resetQueryParams);
                    if (redirectUrl) {
                        location.assign(redirectUrl);
                        return;
                    }

                    const searchEl = document.getElementById(gid + '__search');
                    if (searchEl) {
                        searchEl.value = '';
                        dt.search('').draw();
                    }

                    document.querySelectorAll('#' + gid + '__facets .tr-facet').forEach(box => {
                        const colName = box.dataset.col || '';
                        const type = String(box.dataset.type || 'buttons').toLowerCase();
                        const colIdx = findColIdxByName(colName);

                        if (type === 'range_number') {
                            box.querySelectorAll('.tr-range-dd').forEach(dd => {
                                dd.classList.remove('tr-dd-open');
                                const menu = dd.querySelector('.dropdown-menu');
                                if (menu) menu.classList.remove('show');
                                const toggle = dd.querySelector('.tr-dd-toggle');
                                if (toggle) toggle.setAttribute('aria-expanded', 'false');
                                dd.querySelectorAll('.tr-range-option').forEach(opt => opt.classList.remove('active'));
                                U.setSingleDropdownLabel(dd, '', t('table.all', 'All'));
                            });
                            state.facets[colName] = { from: '', to: '' };
                            return;
                        }

                        if (type === 'range_date') {
                            const fromInput = box.querySelector('.tr-range-from');
                            const toInput = box.querySelector('.tr-range-to');
                            const fromSelect = box.querySelector('.tr-range-from-select');
                            const toSelect = box.querySelector('.tr-range-to-select');
                            if (fromInput) fromInput.value = '';
                            if (toInput) toInput.value = '';
                            if (fromSelect) fromSelect.value = '';
                            if (toSelect) toSelect.value = '';
                            state.facets[colName] = { from: '', to: '' };
                            return;
                        }

                        if (type === 'dropdown_multi') {
                            box.querySelectorAll('.tr-dd-chk').forEach(chk => chk.checked = false);
                            U.setDropdownLabel(box, []);
                            if (colIdx >= 0) dt.column(colIdx).search('', true, false);
                            return;
                        }

                        const allBtn = box.querySelector('button[data-value=""]');
                        if (allBtn) {
                            box.querySelectorAll('button').forEach(b => b.classList.remove('active'));
                            allBtn.classList.add('active');
                            if (colIdx >= 0) dt.column(colIdx).search('', true, false);
                        }
                    });

                    const colCbs = Array.from(document.querySelectorAll('#' + gid + '__cols .tr-colchk'));
                    for (let i = 0; i < dt.columns().count(); i++) {
                        dt.column(i).visible(true);
                        if (colCbs[i]) colCbs[i].checked = true;
                    }

                    dt.order([]);
                    dt.page.len(perPage);
                    dt.page(0).draw('page');

                    Object.keys(state).forEach(key => { delete state[key]; });
                    Object.assign(state, {
                        colsVisible: null,
                        searchText: '',
                        facets: {},
                        pageLength: perPage,
                        order: [],
                        page: 0
                    });
                    try { U.setCookie(cookieKey, JSON.stringify(state)); } catch (e) {}
                });
            })();

            return { dt, getState: () => state, persist };
        })();
        const dt = GridState.dt;

        // РЎРѕС…СЂР°РЅРµРЅРёРµ РїСЂРё РёР·РјРµРЅРµРЅРёРё РјСѓР»СЊС‚РёСЃРµР»РµРєС‚Р° (Select2): POST action=update, id, save_param
        if (multiselectMsgId) {
            $tbl.on('change', '.tr-select2-multiselect', function () {
                const $sel = $(this);
                const rowId = $sel.attr('data-row-id') || '';
                const saveParam = $sel.attr('data-save-param') || 'users_id_str';
                if (!rowId) return;
                const vals = $sel.val();
                const form = new FormData();
                form.append('action', 'update');
                form.append('id', rowId);

                // saveParam РјРѕР¶РµС‚ Р±С‹С‚СЊ СЃС‚СЂРѕРєРѕР№ (users_id_str) РёР»Рё РјР°СЃСЃРёРІРѕРј (roles[])
                if (/\[\]\s*$/.test(saveParam)) {
                    const key = saveParam.replace(/\s+$/, '');
                    if (Array.isArray(vals)) {
                        vals.forEach(v => form.append(key, String(v)));
                    } else if (vals) {
                        form.append(key, String(vals));
                    }
                } else {
                    const valStr = Array.isArray(vals) ? vals.join(', ') : (vals || '');
                    form.append(saveParam, String(valStr));
                }
                form.append('ajax', '1');
                const xhr = new XMLHttpRequest();
                xhr.open('POST', window.location.href);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.onload = function () {
                    let res = { ok: false };
                    try { res = JSON.parse(xhr.responseText); } catch (e) {}
                    const msgEl = document.getElementById(multiselectMsgId);
                    if (msgEl) {
                        msgEl.textContent = res.ok ? t('forms.saved', 'Saved.') : (res.error || t('common.response_error', 'Error'));
                        msgEl.classList.remove('d-none', 'alert-success', 'alert-danger');
                        msgEl.classList.add(res.ok ? 'alert-success' : 'alert-danger');
                    }
                };
                xhr.send(form);
            });
        }

        // =========================
        // Facet dropdowns (no bootstrap JS)
        // =========================
        (function initFacetDropdowns() {
            const root = document.getElementById(gid + '__facets');
            if (!root) return;

            function closeAll(except) {
                root.querySelectorAll('.dropdown.tr-dd-open').forEach(dd => {
                    if (except && dd === except) return;
                    dd.classList.remove('tr-dd-open');
                    const menu = dd.querySelector('.dropdown-menu');
                    if (menu) menu.classList.remove('show');
                    const btn = dd.querySelector('.tr-dd-toggle');
                    if (btn) btn.setAttribute('aria-expanded', 'false');
                });
            }

            root.querySelectorAll('.tr-facet[data-type="dropdown_multi"] .dropdown, .tr-facet[data-type="range_number"] .dropdown').forEach(dd => {
                const btn = dd.querySelector('.tr-dd-toggle');
                const menu = dd.querySelector('.dropdown-menu');
                const searchInput = menu ? menu.querySelector('.tr-dd-search') : null;
                if (!btn || !menu) return;

                if (window.PMStyle && typeof window.PMStyle.setDropdownMenuZIndex === 'function') window.PMStyle.setDropdownMenuZIndex(menu, 9999);

                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();

                    const isOpen = dd.classList.contains('tr-dd-open');
                    closeAll(dd);

                    if (!isOpen) {
                        dd.classList.add('tr-dd-open');
                        menu.classList.add('show');
                        btn.setAttribute('aria-expanded', 'true');
                        const searchInput = menu.querySelector('.tr-dd-search');
                        if (searchInput) {
                            window.setTimeout(() => searchInput.focus(), 0);
                        }
                    } else {
                        dd.classList.remove('tr-dd-open');
                        menu.classList.remove('show');
                        btn.setAttribute('aria-expanded', 'false');
                    }
                });

                menu.addEventListener('click', (e) => e.stopPropagation());

                if (searchInput) {
                    const applySearchFilter = () => {
                        const q = String(searchInput.value || '').trim().toLowerCase();
                        menu.querySelectorAll('.form-check').forEach(wrap => {
                            const chk = wrap.querySelector('.tr-dd-chk');
                            if (!chk) return;
                            const lbl = wrap.querySelector('.form-check-label');
                            const hay = String(((lbl ? lbl.textContent : '') || chk.value || '')).toLowerCase();
                            const visible = q === '' || hay.indexOf(q) !== -1;
                            wrap.classList.toggle('d-none', !visible);
                        });
                    };
                    searchInput.addEventListener('input', applySearchFilter);
                    applySearchFilter();
                }
            });

            document.addEventListener('click', () => closeAll(null));
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeAll(null); });
        })();

        // =========================
        // Columns dropdown (multi-select of visible columns)
        // =========================
        (function initColumnsDropdown() {
            const wrap = document.getElementById(gid + '__cols_btn')?.closest('.dropdown');
            if (!wrap) return;
            const btn = wrap.querySelector('.tr-cols-dd-toggle');
            const menu = wrap.querySelector('.tr-cols-dd-menu');
            const label = wrap.querySelector('.tr-cols-dd-label');
            const badge = wrap.querySelector('.tr-cols-dd-count');
            if (!btn || !menu) return;
            const state = GridState.getState();

            function syncLabel() {
                const checks = menu.querySelectorAll('.tr-colchk');
                let visible = 0;
                checks.forEach(ch => { if (ch.checked) visible++; });
                if (!label) return;
                if (visible === checks.length || visible === 0) {
                    label.textContent = t('table.all', 'All');
                    if (badge) badge.classList.add('d-none');
                } else {
                    label.textContent = t('common.selected', 'Selected');
                    if (badge) {
                        badge.textContent = String(visible);
                        badge.classList.remove('d-none');
                    }
                }
            }

            syncLabel();

            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const isOpen = wrap.classList.contains('tr-dd-open');
                document.querySelectorAll('.tr-cols-dd-menu').forEach(m => {
                    const w = m.closest('.dropdown');
                    if (!w) return;
                    w.classList.remove('tr-dd-open');
                    m.classList.remove('show');
                    const b = w.querySelector('.tr-cols-dd-toggle');
                    if (b) b.setAttribute('aria-expanded', 'false');
                });
                if (!isOpen) {
                    wrap.classList.add('tr-dd-open');
                    menu.classList.add('show');
                    btn.setAttribute('aria-expanded', 'true');
                }
            });

            menu.addEventListener('click', (e) => e.stopPropagation());

            const allBtn = menu.querySelector('.tr-cols-dd-all');
            const clearBtn = menu.querySelector('.tr-cols-dd-clear');
            if (allBtn) {
                allBtn.addEventListener('click', () => {
                    menu.querySelectorAll('.tr-colchk').forEach(ch => {
                        ch.checked = true;
                        const idx = parseInt(ch.dataset.colIdx || '-1', 10);
                        if (Number.isInteger(idx) && idx >= 0) {
                            dt.column(idx).visible(true);
                            state.colsVisible[idx] = true;
                        }
                    });
                    syncLabel();
                    GridState.persist();
                });
            }
            if (clearBtn) {
                clearBtn.addEventListener('click', () => {
                    menu.querySelectorAll('.tr-colchk').forEach(ch => {
                        ch.checked = false;
                        const idx = parseInt(ch.dataset.colIdx || '-1', 10);
                        if (Number.isInteger(idx) && idx >= 0) {
                            dt.column(idx).visible(false);
                            state.colsVisible[idx] = false;
                        }
                    });
                    syncLabel();
                    GridState.persist();
                });
            }

            menu.querySelectorAll('.tr-colchk').forEach(ch => {
                ch.addEventListener('change', () => {
                    syncLabel();
                });
            });

            document.addEventListener('click', () => {
                wrap.classList.remove('tr-dd-open');
                menu.classList.remove('show');
                btn.setAttribute('aria-expanded', 'false');
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    wrap.classList.remove('tr-dd-open');
                    menu.classList.remove('show');
                    btn.setAttribute('aria-expanded', 'false');
                }
            });
        })();

        // =========================
        // GridActions (links + confirm modal + post)
        // =========================
        (function initGridActions() {
            const $modal = $('#' + gid + '__modal');
            const $mTitle = $('#' + gid + '__mtitle');
            const $mBody = $('#' + gid + '__mbody');
            const $mFoot = $('#' + gid + '__mfooter');
            const $form = $('#' + gid + '__form');

            function btnClass(kind) {
                switch (String(kind || '').toLowerCase()) {
                    case 'primary': return 'btn btn-primary';
                    case 'secondary': return 'btn btn-outline-secondary';
                    case 'danger': return 'btn btn-danger';
                    case 'warning': return 'btn btn-warning';
                    default: return 'btn btn-outline-primary';
                }
            }

            function doAction($a) {
                const type = String($a.data('action-type') || 'GET').toUpperCase();

                if (type === 'GET') {
                    const href = $a.data('href') || $a.attr('href');
                    if (href && !String(href).startsWith('#')) location.href = href;
                    return;
                }

                const endpoint = $a.data('endpoint') || location.href;
                let payload;
                try { payload = JSON.parse($a.attr('data-post') || '{}') || {}; }
                catch (e) { payload = {}; }

                if (csrf && !payload.__csrf) payload.__csrf = csrf;

                // POST С‡РµСЂРµР· fetch, С‡С‚РѕР±С‹ РЅРµ Р·Р°РјРµРЅСЏС‚СЊ СЃС‚СЂР°РЅРёС†Сѓ JSON-РѕС‚РІРµС‚РѕРј; РїСЂРё СѓСЃРїРµС…Рµ вЂ” РїРµСЂРµР·Р°РіСЂСѓР·РєР°
                const body = new URLSearchParams();
                Object.entries(payload).forEach(([k, v]) => {
                    if (Array.isArray(v)) v.forEach(vv => body.append(k + '[]', vv));
                    else body.append(k, String(v));
                });

                fetch(endpoint, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: body.toString()
                })
                    .then(r => r.text())
                    .then(txt => {
                        try {
                            const start = txt.indexOf('{');
                            const res = start >= 0 ? JSON.parse(txt.slice(start)) : {};
                            if (res && res.ok) location.reload();
                            else alert(res.error || t('common.response_error', 'Request error'));
                        } catch (e) {
                            alert(t('common.response_error', 'Server response error'));
                        }
                    })
                    .catch(() => alert(t('common.network_error', 'Network error')));
            }

            function showDialog($a) {
                const title = $a.data('dialog-title') || t('table.modal_title', 'Confirmation');
                const msg = $a.data('dialog-msg') || '';
                let buttons;
                try { buttons = JSON.parse($a.attr('data-dialog-buttons') || '[]') || []; }
                catch (e) { buttons = []; }

                $mTitle.text(title);
                $mBody.html(msg);
                $mFoot.empty();

                if (!buttons.length) {
                    buttons = [
                        { label: 'OK', role: 'confirm', kind: 'primary' },
                        { label: t('common.cancel', 'Cancel'), role: 'cancel', kind: 'secondary' }
                    ];
                }

                buttons.forEach(b => {
                    const $b = $('<button>', {
                        type: 'button',
                        class: btnClass(b.kind),
                        'data-role': (b.role || 'confirm')
                    }).text(b.label || 'OK');
                    $mFoot.append($b);
                });

                $mFoot.off('click.trdlg').on('click.trdlg', 'button', function () {
                    const role = $(this).data('role');
                    // РµСЃР»Рё bootstrap modal РµСЃС‚СЊ - Р·Р°РєСЂРѕРµРј, РµСЃР»Рё РЅРµС‚ - РїСЂРѕСЃС‚Рѕ СЃРїСЂСЏС‡РµРј РєР»Р°СЃСЃР°РјРё
                    try { $modal.modal('hide'); } catch (e) { $modal.removeClass('show'); $modal.hide(); }
                    if (role === 'confirm') doAction($a);
                });

                try { $modal.modal('show'); }
                catch (e) { $modal.addClass('show'); $modal.show(); }
            }

            $tbl.on('click', 'a.tr-action', function (e) {
                e.preventDefault();
                const $a = $(this);
                const hasDialog = String($a.data('has-dialog') || '') === '1';
                if (hasDialog) showDialog($a);
                else doAction($a);
            });
        })();
    }

    function bootAll() {
        document.querySelectorAll('.tr-grid[data-tr-gid]').forEach(el => {
            const gid = el.getAttribute('data-tr-gid') || '';
            if (!gid) return;

            let resetQueryParams = [];
            try {
                const raw = el.getAttribute('data-tr-reset-query-params') || '[]';
                const parsed = JSON.parse(raw);
                if (Array.isArray(parsed)) {
                    resetQueryParams = parsed.map(x => String(x || '').trim()).filter(Boolean);
                }
            } catch (e) {}

            initGrid({
                gid,
                perPage: parseInt(el.getAttribute('data-tr-per-page') || '100', 10) || 100,
                csrf: el.getAttribute('data-tr-csrf') || '',
                multiselectMsgId: el.getAttribute('data-tr-multiselect-msg') || '',
                resetQueryParams
            });
        });
    }

    if (window.jQuery) $(bootAll);
    else document.addEventListener('DOMContentLoaded', bootAll);
})();



