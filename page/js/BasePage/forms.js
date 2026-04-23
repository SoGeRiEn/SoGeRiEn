(function(){
function bootForms(){
    var PMStyle = window.PMStyle || null;
    var DEF = window.__FORM_DEF__ || {};
    var formEl = document.getElementById(DEF.id || 'form1');
    if (!formEl) return;
    function t(key, fallback){
    if (typeof window.sogerienLangGet === 'function') return window.sogerienLangGet(key, fallback);
    return fallback || key;
}

    /* ===== Helpers ===== */
    function $(sel, root){ return (root||document).querySelector(sel); }
    function $all(sel, root){ return Array.prototype.slice.call((root||document).querySelectorAll(sel)); }
    function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g, function(m){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]); }); }
    function toNum(v){ var n = parseFloat(String(v).replace(',','.')); return isNaN(n)?0:n; }
    function findItemByName(name){ var it=null; (DEF.items||[]).some(function(x){ if(x.name===name){it=x;return true;} return false; }); return it; }
    function byId(id){ return document.getElementById(id); }
    function setFacetDropdownLabel(box){
    if(!box) return;
    var selected = $all('.tr-dd-chk:checked', box);
    var label = $('.tr-dd-label', box);
    var badge = $('.tr-dd-count', box);
    if(!label) return;
    if (!selected.length){
    label.textContent = t('table.all', 'All');
    if (badge) badge.classList.add('d-none');
    return;
}
    label.textContent = t('common.selected', 'Selected');
    if (badge){
    badge.textContent = String(selected.length);
    badge.classList.remove('d-none');
}
}
    function getFieldValue(name){
    var it = findItemByName(name); if(!it) return null;
    var el = byId(it.id); if(!el) return null;
    if (it.type==='checkbox'){ return el.checked ? '1':'0'; }
    if (it.type==='checkbox_group'){ var vals=[]; $all('input[name="'+it.name+'[]"]:checked').forEach(function(c){ vals.push(c.value); }); return vals; }
    if (it.type==='radio_group'){ var r = $('input[name="'+it.name+'"]:checked'); return r? r.value : null; }
    return el.value;
}
    function setFieldValue(name, val){
    var it = findItemByName(name); if(!it) return;
    var el = byId(it.id); if(!el) return;
    if (it.type==='input' || it.type==='textarea'){ el.value = String(val); el.dispatchEvent(new Event('input',{bubbles:true})); }
    else if (it.type==='select' || it.type==='linked_select'){ el.value = String(val); el.dispatchEvent(new Event('change',{bubbles:true})); }
}

    /* ===== Table-like facet controls inside Forms ===== */
    (function initFacetControls(){
    var facetsRoot = formEl;
    var allDropdowns = function(){ return $all('.tr-facet[data-form-facet="1"] .dropdown.tr-dd-open', facetsRoot); };
    function closeAll(except){
    allDropdowns().forEach(function(dd){
    if (except && dd===except) return;
    dd.classList.remove('tr-dd-open');
    var menu = $('.dropdown-menu', dd);
    if (menu) menu.classList.remove('show');
    var btn = $('.tr-dd-toggle', dd);
    if (btn) btn.setAttribute('aria-expanded','false');
});
}
    $all('.tr-facet[data-form-facet="1"]', facetsRoot).forEach(function(box){
    var type = String(box.getAttribute('data-type')||'').toLowerCase();
    if (type === 'buttons'){
    var hidden = $('.tr-facet-hidden', box);
    $all('button[data-value]', box).forEach(function(btn){
    btn.addEventListener('click', function(){
    $all('button[data-value]', box).forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');
    if (hidden) hidden.value = String(btn.getAttribute('data-value')||'');
});
});
    return;
}
    if (type !== 'dropdown_multi') return;
    var dd = $('.dropdown', box);
    var btn = $('.tr-dd-toggle', box);
    var menu = $('.dropdown-menu', box);
    var search = $('.tr-dd-search', box);
    if (!dd || !btn || !menu) return;
    btn.addEventListener('click', function(e){
    e.preventDefault(); e.stopPropagation();
    var isOpen = dd.classList.contains('tr-dd-open');
    closeAll(dd);
    if (!isOpen){
    dd.classList.add('tr-dd-open');
    menu.classList.add('show');
    btn.setAttribute('aria-expanded','true');
    if (search) window.setTimeout(function(){ search.focus(); }, 0);
} else {
    dd.classList.remove('tr-dd-open');
    menu.classList.remove('show');
    btn.setAttribute('aria-expanded','false');
}
});
    menu.addEventListener('click', function(e){ e.stopPropagation(); });
    function applySearchFilter(){
    if (!search) return;
    var q = String(search.value||'').trim().toLowerCase();
    $all('.form-check', box).forEach(function(wrap){
    var chk = $('.tr-dd-chk', wrap);
    if (!chk) return;
    var lbl = $('.form-check-label', wrap);
    var hay = String(((lbl && lbl.textContent) || chk.value || '')).toLowerCase();
    var visible = (q==='') || (hay.indexOf(q)!==-1);
    wrap.classList.toggle('d-none', !visible);
});
}
    function selectAllVisible(){
    $all('.form-check:not(.d-none) .tr-dd-chk', box).forEach(function(chk){
    if (!chk.disabled) chk.checked = true;
});
    setFacetDropdownLabel(box);
}
    var allBtn = $('.tr-dd-all', box);
    var clearBtn = $('.tr-dd-clear', box);
    if (allBtn) allBtn.addEventListener('click', selectAllVisible);
    if (clearBtn) clearBtn.addEventListener('click', function(){
    $all('.tr-dd-chk', box).forEach(function(chk){ chk.checked = false; });
    setFacetDropdownLabel(box);
});
    $all('.tr-dd-chk', box).forEach(function(chk){
    chk.addEventListener('change', function(){ setFacetDropdownLabel(box); });
});
    if (search){
    search.addEventListener('input', applySearchFilter);
    applySearchFilter();
}
    setFacetDropdownLabel(box);
});
    document.addEventListener('click', function(){ closeAll(null); });
    document.addEventListener('keydown', function(e){ if (e.key==='Escape') closeAll(null); });
})();

    /* ===== Modal (palette) ===== */
    var modal     = byId('paletteModal');
    var palTitle  = byId('palTitle');
    var palSearch = byId('palSearch');
    var palList   = byId('palList');
    var palClose  = byId('palClose');

    var currentSelect = null, currentRows=[], currentKind='', currentParentName='';

    // focus trap
    var lastFocusedBeforeModal = null;
    function trapFocus(e){
    if (modal.getAttribute('aria-hidden')==='false'){
    var focusables = $all('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])', modal)
    .filter(function(el){ return !el.hasAttribute('disabled') && el.getAttribute('aria-hidden')!=='true' && el.offsetParent!==null; });
    if (!focusables.length) return;
    var first = focusables[0], last = focusables[focusables.length-1];
    if (e.key === 'Tab'){
    if (e.shiftKey && document.activeElement === first){ e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last){ e.preventDefault(); first.focus(); }
}
}
}

    function getRowsFor(selectEl){
    var it = findItemByName(selectEl.name); if(!it) return [];
    var rows = [];
    if (it.type==='select') rows = (it.options||[]).slice();
    else if (it.type==='linked_select'){
    var parentVal = getFieldValue(it.parent); if(!parentVal) return [];
    var by = it.by_parent;
    (it.child||[]).forEach(function(r){ if(String(r[by])===String(parentVal)) rows.push(r); });
}
    // скрыть уже выбранный в этом поле
    var cur = String(selectEl.value||'');
    if (cur) rows = rows.filter(function(r){ return String(r.id)!==cur; });
    return rows;
}
    function renderList(rows){
    if(!rows.length){
    palList.innerHTML = '<div class="pal-row pal-row-empty"><span class="muted">'+esc(t('common.no_results', 'Nothing found'))+'</span></div>';
    return;
}
    var html='';
    rows.forEach(function(r){
    var t = r.title || r.text || r.id;
    var right = r.edrpou ? ((t('forms.edrpou', 'EDRPOU') + ' ' + r.edrpou)) : '';
    html += '<div class="pal-row" data-id="'+esc(r.id)+'" tabindex="0"><strong>'+esc(t)+'</strong>'+(right?'<small>'+esc(right)+'</small>':'')+'</div>';
});
    palList.innerHTML = html;
}
    function openModal(selectEl){
    if (!modal) return;
    currentSelect = selectEl;
    var it = findItemByName(selectEl.name); if(!it) return;
    palTitle.textContent = it.label || t('forms.select', 'Select');
    currentKind = (selectEl.getAttribute('data-kind')||'solo');
    currentParentName = (selectEl.getAttribute('data-parent')||'');
    currentRows = getRowsFor(selectEl);
    renderList(currentRows);
    palSearch.value = '';

    lastFocusedBeforeModal = document.activeElement;
    modal.setAttribute('aria-hidden','false');
    if (PMStyle && typeof PMStyle.setDocumentOverflowHidden === 'function') PMStyle.setDocumentOverflowHidden(true);
    else document.documentElement.style.overflow = 'hidden';
    window.setTimeout(function(){ palSearch.focus(); },0);
}
    function closeModal(){
    if (!modal) return;
    modal.setAttribute('aria-hidden','true');
    if (PMStyle && typeof PMStyle.setDocumentOverflowHidden === 'function') PMStyle.setDocumentOverflowHidden(false);
    else document.documentElement.style.overflow = '';
    if (currentSelect) currentSelect.focus();
}
    palList && palList.addEventListener('click', function(e){
    var row = e.target.closest('.pal-row'); if(!row) return;
    var id = row.getAttribute('data-id');
    if(!currentSelect) return;
    currentSelect.value = String(id);
    currentSelect.dispatchEvent(new Event('change',{bubbles:true}));
    closeModal();
});
    palList && palList.addEventListener('keydown', function(e){
    if (e.key==='Enter'){
    var row = e.target.closest('.pal-row'); if(!row) return;
    row.click();
}
});
    palClose && palClose.addEventListener('click', closeModal);
    modal && modal.addEventListener('click', function(e){ if(e.target===modal) closeModal(); });
    window.addEventListener('keydown', function(e){
    if (!modal || modal.getAttribute('aria-hidden')==='true') return;
    if (e.key==='Escape') { e.preventDefault(); closeModal(); }
    if (e.key==='Tab') trapFocus(e);
});

    var searchTimer = 0;
    palSearch && palSearch.addEventListener('input', function(){
    window.clearTimeout(searchTimer);
    var q = palSearch.value.trim().toLowerCase();
    searchTimer = window.setTimeout(function(){
    var base = getRowsFor(currentSelect).slice();
    if(!q){ renderList(base.slice(0,200)); return; }
    var tmp=[];
    for(var i=0;i<base.length;i++){
    var r=base[i]; var hay = (r.title||r.text||r.id)+' '+(r.edrpou||'');
    if(String(hay).toLowerCase().indexOf(q)!==-1){ tmp.push(r); if(tmp.length>=200) break; }
}
    renderList(tmp);
},120);
});

    /* ===== init selects ===== */
    (DEF.items||[]).forEach(function(it){
    if (it.type!=='select' && it.type!=='linked_select') return;
    var el = byId(it.id); if(!el) return;

    // блокируем нативный дропдаун, открываем нашу модалку
    function intercept(e){ e.preventDefault(); openModal(el); }
    el.addEventListener('mousedown', intercept);
    el.addEventListener('click', intercept);
    el.addEventListener('keydown', function(e){
    if (e.key===' '||e.key==='Enter'){ e.preventDefault(); openModal(el); }
});

    // наполняем опции (для graceful-degradation и текущего value)
    el.innerHTML='';
    var wantEmpty = (el.getAttribute('data-empty')||'on') !== 'off';
    if (wantEmpty) {
    var opt = document.createElement('option'); opt.value=''; opt.textContent='—'; el.appendChild(opt);
}

    if (it.type==='select'){
    (it.options||[]).forEach(function(r){
    var o = document.createElement('option'); o.value=String(r.id); o.textContent=r.title||r.text||r.id; el.appendChild(o);
});
    if (it.value!=null) el.value = String(it.value);
}
});

    // linked_select: динамически пересобираем список
    function rebuildLinkedOptions(it){
    var el = byId(it.id); if(!el) return;
    var parentVal = getFieldValue(it.parent);
    el.innerHTML=''; var opt = document.createElement('option'); opt.value=''; opt.textContent='—'; el.appendChild(opt);
    if(!parentVal){ el.disabled=true; return; } el.disabled=false;
    var by = it.by_parent, list=[];
    (it.child||[]).forEach(function(r){ if(String(r[by])===String(parentVal)) list.push(r); });
    list.forEach(function(r){ var o=document.createElement('option'); o.value=String(r.id); o.textContent=r.title||r.text||r.id; el.appendChild(o); });
    if (it.value!=null && String(it.value)!==''){
    var has = list.some(function(r){ return String(r.id)===String(it.value); });
    el.value = has ? String(it.value) : '';
} else el.value='';
}
    (DEF.items||[]).forEach(function(it){
    if (it.type!=='linked_select') return;
    var parentIt = findItemByName(it.parent);
    if(!parentIt) return;
    var pel = byId(parentIt.id);
    if(!pel) return;
    pel.addEventListener('change', function(){ rebuildLinkedOptions(it); });
    rebuildLinkedOptions(it);
});

    /* ===== show_if ===== */
    function cmpIn(val, tgtArr){ return tgtArr.some(function(t){ return String(val)===String(t); }); }
    function evalRule(rule){
    var v = getFieldValue(rule.field), op=(rule.op||'=').toLowerCase(), tgt=rule.value;
    var isArr = Array.isArray(v);

    if (isArr){
    if (op==='in')     return Array.isArray(tgt) ? v.some(function(x){return cmpIn(x, tgt);}) : v.indexOf(String(tgt))!==-1;
    if (op==='not_in') return Array.isArray(tgt) ? !v.some(function(x){return cmpIn(x, tgt);}) : v.indexOf(String(tgt))===-1;
    if (op==='=')      return v.join(',')===String(tgt);
    if (op==='!=')     return v.join(',')!==String(tgt);
    return false;
}
    if (op==='=')      return String(v)===String(tgt);
    if (op==='!=')     return String(v)!==String(tgt);
    if (op==='in')     return Array.isArray(tgt) ? cmpIn(v, tgt) : String(v)===String(tgt);
    if (op==='not_in') return Array.isArray(tgt) ? !cmpIn(v, tgt) : String(v)!==String(tgt);
    if (op==='truthy') return !!v && v!=='0';
    if (op==='falsy')  return !v || v==='0';
    return false;
}
    function applyVisibility(){
    (DEF.items||[]).forEach(function(it){
    var wrap = byId(it.id+'_wrap'); if(!wrap) return;
    var cond = it.show_if; if(!cond){ wrap.classList.remove('hidden','d-none'); return; }
    var logic = (cond.logic||'all').toLowerCase(), rules = cond.rules||[];
    var res = (logic==='any') ? rules.some(evalRule) : rules.every(evalRule);
    if(res) wrap.classList.remove('hidden','d-none'); else wrap.classList.add('hidden','d-none');
});
}
    (DEF.items||[]).forEach(function(it){
    var el = byId(it.id); if(!el) return;
    var ev = (it.type==='checkbox'||it.type==='checkbox_group'||it.type==='radio_group') ? 'change':'input';
    if (it.type==='select'||it.type==='linked_select') ev='change';
    if (it.type==='checkbox_group'){ $all('input[name="'+it.name+'[]"]').forEach(function(c){ c.addEventListener('change', applyVisibility); }); }
    else if (it.type==='radio_group'){ $all('input[name="'+it.name+'"]').forEach(function(r){ r.addEventListener('change', applyVisibility); }); }
    else { el.addEventListener(ev, applyVisibility); }
});
    applyVisibility();

    /* ===== Calculator ===== */
    var CALC = (DEF.meta||{}).calculator || null;
    function calcAndSet(){
    if(!CALC) return;
    var precision = parseInt(String(CALC.precision||2),10);
    var base = 0;
    (CALC.sources||[]).forEach(function(name){ base += toNum(getFieldValue(name)); });
    var qty = toNum(getFieldValue(CALC.qty)); if (qty<=0) qty = 1;
    var amount = base * qty;

    var dt = (CALC.discount_type ? String(getFieldValue(CALC.discount_type)) : 'none');
    var dv = toNum(getFieldValue(CALC.discount_val));
    if (dt==='percent' && dv>0) amount = amount * (1 - dv/100);
    else if (dt==='fixed' && dv>0) amount = amount - dv;

    if (amount < 0) amount = 0;
    var out = (precision>=0) ? amount.toFixed(precision) : String(amount);
    if (CALC.target) setFieldValue(CALC.target, out);
}
    if (CALC){
    var watch = [].concat(CALC.sources||[], [CALC.qty, CALC.discount_type, CALC.discount_val].filter(Boolean));
    watch.forEach(function(name){
    var it = findItemByName(name); if(!it) return;
    var el = byId(it.id); if(!el) return;
    var ev = (it.type==='select'||it.type==='linked_select'||it.type==='checkbox'||it.type==='radio_group'||it.type==='checkbox_group') ? 'change':'input';
    if (it.type==='radio_group'){ $all('input[name="'+it.name+'"]').forEach(function(r){ r.addEventListener('change', calcAndSet); }); }
    else if (it.type==='checkbox_group'){ $all('input[name="'+it.name+'[]"]').forEach(function(c){ c.addEventListener('change', calcAndSet); }); }
    else { el.addEventListener(ev, calcAndSet); }
});
    calcAndSet();
}

    /* ===== Order Lines (таблица) ===== */
    var LINES = (DEF.meta||{}).order_lines || null;
    var rowsMem = []; // видимо для submit
    if (LINES){
    var host = byId(LINES.id+'_host');
    var btnId = DEF.id+'__add_line_btn';
    var btnText = esc((LINES.button && LINES.button.text) || t('forms.add_to_table', 'Add to table'));

    // Кнопка добавления в сетке Bootstrap
    var btnHtml = ''
    + '<div class="col-12 my-2">'
    + '  <button type="button" class="btn btn-outline-primary btn-sm" id="'+btnId+'">'+btnText+'</button>'
    + '</div>';
    host.insertAdjacentHTML('beforebegin', btnHtml);

    var ths = (LINES.columns||[]).map(function(c){ return '<th>'+esc(c.title||c.key)+'</th>'; }).join('') + '<th>'+esc(t('common.actions', 'Actions'))+'</th>';
    host.innerHTML = '<table class="sog-lines" id="'+LINES.id+'"><thead><tr>'+ths+'</tr></thead><tbody></tbody></table>';

    var tbody = $('#'+LINES.id+' tbody');

    function lookup(datasetName, id, field){
    var ds = ((DEF.meta||{}).datasets||{})[datasetName]||[];
    for (var i=0;i<ds.length;i++){ if(String(ds[i].id)===String(id)) return ds[i][field]; }
    return '';
}
    function takeRowObject(){
    var obj = {};
    (LINES.columns||[]).forEach(function(c){
    var val = getFieldValue(c.from);
    obj[c.key] = val;
    obj['_disp_'+c.key] = (c.format||'raw').startsWith('lookup:') ? (function(){
    var parts = c.format.split(':'); return lookup(parts[1], val, parts[2]||'title');
})() : (c.format==='number' ? toNum(val).toFixed(2) : String(val==null?'':val));
});
    return obj;
}
    function isDuplicate(obj){
    var keys = (LINES.dedupe_by||[]); if(!keys.length) return false;
    return rowsMem.some(function(r){ return keys.every(function(k){ return String(r[k])===String(obj[k]); }); });
}
    function renderRows(){
    var html='';
    rowsMem.forEach(function(r, idx){
    var tds = (LINES.columns||[]).map(function(c){ return '<td>'+esc(r['_disp_'+c.key])+'</td>'; }).join('');
    html += '<tr data-idx="'+idx+'">'+ tds
    + '<td><div class="row-actions">'
    + '<button type="button" class="btn btn-outline-danger btn-sm" data-idx="'+idx+'">'+esc(t('forms.delete_row', 'Delete'))+'</button>'
    + '</div></td></tr>';
});
    tbody.innerHTML = html || '<tr><td colspan="'+((LINES.columns||[]).length+1)+'" class="muted">'+esc(t('forms.empty', 'Empty'))+'</td></tr>';
}
    byId(btnId).addEventListener('click', function(){
    var obj = takeRowObject();
    if (isDuplicate(obj)) { alert(t('forms.duplicate_row', 'This row already exists')); return; }
    rowsMem.push(obj);
    renderRows();
});
    tbody.addEventListener('click', function(e){
    var btn = e.target.closest('.btn-outline-danger'); if(!btn) return;
    var idx = parseInt(btn.getAttribute('data-idx')||'-1',10);
    if (idx>=0){ rowsMem.splice(idx,1); renderRows(); }
});
}

    /* ===== Submit (AJAX) ===== */
    formEl.addEventListener('submit', function(e){
    var ajax = (formEl.getAttribute('data-ajax') === 'true');
    if (!ajax) return;
    e.preventDefault();
    var fd = new FormData(formEl);
    if ((DEF.meta||{}).order_lines){
    try { fd.append('__order_lines', JSON.stringify(rowsMem||[])); } catch(_) {}
}
    fetch(formEl.action || location.href, { method: formEl.method || 'POST', body: fd })
    .then(function(r){ return r.text(); })
    .then(function(txt){ alert(t('forms.server_response', 'Server response') + ':\n' + txt.slice(0,1000)); })
    .catch(function(err){ alert(t('forms.send_error', 'Send error') + ': ' + err); });
});
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bootForms);
else bootForms();
})();

/* ===== CRUD List (список + модалки добавления/редактирования). Конфиг: window.__FORM_CRUD_DEF__ = { list_id, empty_id, message_id, btn_add_id, row_primary_key, row_display, row_roles_key, roles_list, edit_modal, add_modal, reload_on_edit_success, row_css_class, info_css_class, btn_edit_class, btn_delete_class, btn_edit_text, btn_delete_text, delete_confirm } */
(function(){
function initCrud(){
var CRUD = window.__FORM_CRUD_DEF__;
if (!CRUD || !CRUD.list_id) return;
var PMStyle = window.PMStyle || null;
function t(key, fallback){
  if (typeof window.sogerienLangGet === 'function') return window.sogerienLangGet(key, fallback);
  return fallback || key;
}

var statusKey = CRUD.status_key || null;
var btnStatusClass = CRUD.btn_status_class || null;

var listEl = document.getElementById(CRUD.list_id);
var emptyEl = document.getElementById(CRUD.empty_id || 'crud_empty');
var msgEl = document.getElementById(CRUD.message_id || 'crud_message');
var btnAdd = document.getElementById(CRUD.btn_add_id || 'crud_btn_add');
var rolesList = CRUD.roles_list || [];
var rowDisplay = CRUD.row_display || [];
var rowPk = CRUD.row_primary_key || 'id';
var rowRolesKey = CRUD.row_roles_key || 'roles';
var rowClass = CRUD.row_css_class || 'crud-row';
var infoClass = CRUD.info_css_class || 'crud-info';
var btnEditClass = CRUD.btn_edit_class || 'crud-btn-edit';
var btnDeleteClass = CRUD.btn_delete_class || 'crud-btn-delete';
var btnEditText = CRUD.btn_edit_text || t('common.edit', 'Edit');
var btnDeleteText = CRUD.btn_delete_text || t('common.delete', 'Delete');
var deleteConfirm = CRUD.delete_confirm || (t('common.delete', 'Delete') + ' (id {id})?');
var editModalCfg = CRUD.edit_modal || {};
var addModalCfg = CRUD.add_modal || {};
var editModalEl = document.getElementById(editModalCfg.id);
var addModalEl = document.getElementById(addModalCfg.id);
var editSaveBtn = document.getElementById(editModalCfg.save_btn_id);
var addSaveBtn = document.getElementById(addModalCfg.save_btn_id);

if (!listEl || !msgEl || !editModalEl || !addModalEl || !editSaveBtn || !addSaveBtn) return;

function esc(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// ===== Users picker support (для config.users_dataset) =====
var usersDataset = Array.isArray(CRUD.users_dataset) ? CRUD.users_dataset : [];
var usersById = {};
usersDataset.forEach(function(u){ usersById[String(u.id)] = u; });

function parseIds(str){
  if (!str) return [];
  return String(str).split(/[\s,;]+/).map(function(s){ return s.trim(); }).filter(function(s){ return s !== ''; });
}
function unique(arr){
  var out = [], seen = {};
  arr.forEach(function(v){
    if (!seen[v]){
      seen[v] = true;
      out.push(v);
    }
  });
  return out;
}
function buildUserLabel(id){
  var u = usersById[String(id)];
  if (!u) return String(id);
  var name = u.name || u.login || u.email || ('user #' + u.id);
  return String(u.id) + ': ' + name;
}
function ensureUsersTagsContainer(input){
  var wrapId = input.id + '_tags_wrap';
  var wrap = document.getElementById(wrapId);
  if (!wrap){
    wrap = document.createElement('div');
    wrap.id = wrapId;
    wrap.className = 'mb-3';
    var label = document.createElement('div');
    label.className = 'form-label';
    label.textContent = t('common.users', 'Users') + ' (ID)';
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-sm btn-outline-primary mb-2';
    btn.textContent = t('users.add_user', 'Add user');
    btn.addEventListener('click', function(){ openUsersPicker(input); });
    var box = document.createElement('div');
    box.className = 'd-flex flex-wrap gap-1';
    box.id = input.id + '_tags';
    wrap.appendChild(label);
    wrap.appendChild(btn);
    wrap.appendChild(box);
    input.parentNode.insertBefore(wrap, input.nextSibling);
  }
  return document.getElementById(input.id + '_tags');
}
function setUsersOnInput(input, ids){
  ids = unique(ids);
  input.value = ids.join(', ');
  var box = ensureUsersTagsContainer(input);
  box.innerHTML = '';
  ids.forEach(function(id){
    var badge = document.createElement('span');
    badge.className = 'badge bg-secondary d-inline-flex align-items-center gap-1';
    badge.setAttribute('data-id', String(id));
    var text = document.createElement('span');
    text.textContent = buildUserLabel(id);
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-sm btn-link p-0 text-white text-decoration-none';
    btn.setAttribute('aria-label', t('common.delete', 'Delete'));
    btn.innerHTML = '&times;';
    btn.onclick = function(){
      var current = parseIds(input.value);
      current = current.filter(function(x){ return String(x)!==String(id); });
      setUsersOnInput(input, current);
    };
    badge.appendChild(text);
    badge.appendChild(btn);
    box.appendChild(badge);
  });
}
function openUsersPicker(input){
  if (!usersDataset.length) return;
  var overlay = document.getElementById('formsUsersPickerOverlay');
  if (!overlay){
    overlay = document.createElement('div');
    overlay.id = 'formsUsersPickerOverlay';
    var panel = document.createElement('div');
    panel.id = 'formsUsersPickerPanel';
    var search = document.createElement('input');
    search.type = 'text';
    search.placeholder = t('common.search', 'Search') + '...';
    search.className = 'form-control form-control-sm';
    search.id = 'formsUsersPickerSearch';
    var list = document.createElement('div');
    list.id = 'formsUsersPickerList';
    var hint = document.createElement('div');
    hint.textContent = t('forms.select_hint', 'Enter - select, Esc - close');
    hint.className = 'small text-muted';
    if (PMStyle && typeof PMStyle.applyUsersPickerStyles === 'function') {
      PMStyle.applyUsersPickerStyles(overlay, panel, search, list, hint);
    } else {
      overlay.style.position = 'fixed';
      overlay.style.inset = '0';
      overlay.style.background = 'rgba(0,0,0,0.05)';
      overlay.style.zIndex = '9999';
      panel.style.position = 'absolute';
      panel.style.maxHeight = '400px';
      panel.style.width = '420px';
      panel.style.background = '#fff';
      panel.style.border = '1px solid #ccc';
      panel.style.borderRadius = '4px';
      panel.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
      panel.style.display = 'flex';
      panel.style.flexDirection = 'column';
      search.style.margin = '8px';
      list.style.flex = '1';
      list.style.overflow = 'auto';
      hint.style.padding = '6px 8px';
    }
    panel.appendChild(search);
    panel.appendChild(list);
    panel.appendChild(hint);
    overlay.appendChild(panel);
    document.body.appendChild(overlay);
  }
  var panelEl = document.getElementById('formsUsersPickerPanel');
  var searchEl = document.getElementById('formsUsersPickerSearch');
  var listEl = document.getElementById('formsUsersPickerList');
  var rect = input.getBoundingClientRect();
  var top = rect.bottom + window.scrollY + 4;
  var left = rect.left + window.scrollX;
  if (PMStyle && typeof PMStyle.positionUsersPickerPanel === 'function') PMStyle.positionUsersPickerPanel(panelEl, top, left);
  else { panelEl.style.top = top + 'px'; panelEl.style.left = left + 'px'; }
  function render(filter){
    var q = (filter || '').toLowerCase();
    var html = '';
    usersDataset.forEach(function(u){
      var label = u.login || '';
      if (u.name) label += (label ? ' — ' : '') + u.name;
      if (u.email) label += (label ? ' — ' : '') + u.email;
      var hay = label.toLowerCase();
      if (q && hay.indexOf(q) === -1) return;
      html += '<div class="px-2 py-1 forms-users-row" data-id="'+String(u.id)+'">'
        + '<strong>'+esc(label)+'</strong></div>';
    });
    if (!html) html = '<div class="px-2 py-1 text-muted">' + esc(t('common.no_results', 'Nothing found')) + '</div>';
    listEl.innerHTML = html;
  }
  function close(){
    overlay.removeEventListener('click', onOverlayClick);
    document.removeEventListener('keydown', onKey);
    if (PMStyle && typeof PMStyle.toggleDisplay === 'function') PMStyle.toggleDisplay(overlay, false);
    else overlay.style.display = 'none';
  }
  function onOverlayClick(e){
    if (e.target === overlay) close();
  }
  function onKey(e){
    if (e.key === 'Escape'){ e.preventDefault(); close(); }
  }
  if (PMStyle && typeof PMStyle.toggleDisplay === 'function') PMStyle.toggleDisplay(overlay, true);
  else overlay.style.display = 'block';
  render('');
  searchEl.value = '';
  searchEl.focus();
  overlay.addEventListener('click', onOverlayClick);
  document.addEventListener('keydown', onKey);
  listEl.onclick = function(e){
    var row = e.target.closest('.forms-users-row');
    if (!row) return;
    var id = row.getAttribute('data-id') || '';
    if (!id) return;
    var current = parseIds(input.value);
    current.push(id);
    setUsersOnInput(input, current);
  };
  var timer = 0;
  searchEl.oninput = function(){
    window.clearTimeout(timer);
    var v = searchEl.value;
    timer = window.setTimeout(function(){ render(v); }, 120);
  };
}
function bindUsersPickerInput(input){
  if (!input || input.getAttribute('data-users-picker-bound') === '1') return;
  input.setAttribute('data-users-picker-bound','1');
  setUsersOnInput(input, parseIds(input.value));
}

function getEditValues(){
  var out = {};
  var keys = Object.keys(editModalCfg.fields || {});
  keys.forEach(function(k){
    var f = (editModalCfg.fields || {})[k];
    if (!f) return;
    if (f.container_id){ var c = document.getElementById(f.container_id); var roles = []; if (c){ var cbs = c.querySelectorAll('.form-check-input:checked'); for (var i = 0; i < cbs.length; i++) roles.push(cbs[i].value); } out[k] = roles; return; }
    var el = document.getElementById(f.id); if (!el) return;
    if (f.type === 'checkbox') out[k] = el.checked ? '1' : '0';
    else out[k] = el.value;
  });
  return out;
}

function getAddValues(){
  var out = {};
  var keys = Object.keys(addModalCfg.fields || {});
  keys.forEach(function(k){
    var f = (addModalCfg.fields || {})[k];
    if (!f) return;
    if (f.container_id){ var c = document.getElementById(f.container_id); var roles = []; if (c){ var cbs = c.querySelectorAll('.form-check-input:checked'); for (var i = 0; i < cbs.length; i++) roles.push(cbs[i].value); } out[k] = roles; return; }
    var el = document.getElementById(f.id); if (el) out[k] = el.value;
  });
  return out;
}

function setEditValues(values){
  var keys = Object.keys(editModalCfg.fields || {});
  keys.forEach(function(k){
    var f = (editModalCfg.fields || {})[k];
    if (!f) return;
    if (f.container_id){ renderRolesCheckboxes(f.container_id, values[k] || []); return; }
    var el = document.getElementById(f.id);
    if (el && values[k] !== undefined){
      if (f.type === 'checkbox') el.checked = (values[k] === true || values[k] === 'true' || values[k] === '1');
      else { el.value = values[k]; if (el.getAttribute('data-widget') === 'users_picker') bindUsersPickerInput(el); }
    }
  });
}

function setAddValues(values){
  var keys = Object.keys(addModalCfg.fields || {});
  keys.forEach(function(k){
    var f = (addModalCfg.fields || {})[k];
    if (!f) return;
    if (f.container_id){ renderRolesCheckboxes(f.container_id, values[k] || []); return; }
    var el = document.getElementById(f.id);
    if (el && values[k] !== undefined){
      el.value = values[k];
      if (el.getAttribute('data-widget') === 'users_picker') bindUsersPickerInput(el);
    }
  });
}

function renderRolesCheckboxes(containerId, selectedRoles){
  selectedRoles = selectedRoles || [];
  var container = document.getElementById(containerId);
  if (!container) return;
  container.innerHTML = '';
  rolesList.forEach(function(role){
    var label = document.createElement('label');
    label.className = 'form-check form-check-inline';
    var cb = document.createElement('input');
    cb.type = 'checkbox'; cb.className = 'form-check-input'; cb.value = role;
    cb.checked = selectedRoles.indexOf(role) !== -1;
    label.appendChild(cb); label.appendChild(document.createTextNode(' ' + esc(role)));
    container.appendChild(label);
  });
}

function showMessage(text, isError){
  msgEl.textContent = text;
  msgEl.classList.remove('alert-success','alert-danger','d-none');
  msgEl.classList.add(isError ? 'alert-danger' : 'alert-success');
}

function post(action, data, onDone){
  var form = new FormData();
  form.append('action', action);
  form.append('ajax', '1');
  for (var k in data){ if (!data.hasOwnProperty(k)) continue;
    if (k === 'roles' && Array.isArray(data.roles)){ data.roles.forEach(function(r){ form.append('roles[]', r); }); }
    else { form.append(k, data[k]); }
  }
  var xhr = new XMLHttpRequest();
  xhr.open('POST', window.location.href);
  xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
  xhr.onload = function(){
    var res = { ok: false, error: t('common.response_error', 'Response error') };
    var text = xhr.responseText || '';
    try { var start = text.indexOf('{'); if (start !== -1) res = JSON.parse(text.slice(start)); } catch(e){}
    if (onDone) onDone(res);
  };
  xhr.onerror = function(){ if (onDone) onDone({ ok: false, error: t('common.network_error', 'Network error') }); };
  xhr.send(form);
}

function rolesDisplay(roles){
  if (!roles || roles.length === 0) return '';
  return ' <span class="users-roles ms-1 small text-muted">' + esc(t('roles.role', 'Role')) + ': ' + esc(roles.join(', ')) + '</span>';
}

function buildRowDataFromEl(rowEl){
  var data = { id: rowEl.getAttribute('data-id') };
  rowDisplay.forEach(function(k){ data[k] = rowEl.getAttribute('data-' + k) || ''; });
  var rolesRaw = rowEl.getAttribute('data-roles') || '[]';
  try { data.roles = JSON.parse(rolesRaw); } catch(e){ data.roles = []; }
  if (statusKey){
    data[statusKey] = rowEl.getAttribute('data-status') || '';
  }
  return data;
}

function addRow(rowData){
  if (emptyEl) emptyEl.classList.add('d-none');
  var row = document.createElement('div');
  row.className = rowClass + ' d-flex align-items-center gap-2 py-2 border-bottom';
  row.setAttribute('data-id', String(rowData[rowPk]));
  rowDisplay.forEach(function(k){ row.setAttribute('data-' + k, String(rowData[k] || '')); });
  row.setAttribute('data-roles', JSON.stringify(rowData[rowRolesKey] || []));
  if (statusKey && rowData[statusKey] !== undefined){
    row.setAttribute('data-status', String(rowData[statusKey]));
  }
  var parts = [];
  rowDisplay.forEach(function(key, i){
    var v = rowData[key] || '';
    if (v === '') return;
    parts.push(i === 0 ? '<strong>' + esc(v) + '</strong>' : esc(v));
  });
  var line = parts.join(' — ') + (rowData[rowRolesKey] && rowData[rowRolesKey].length ? rolesDisplay(rowData[rowRolesKey]) : '');
  row.innerHTML = '<span class="' + infoClass + ' flex-grow-1 text-break" role="button" tabindex="0" title="' + esc(t('forms.edit_hint', 'Click to edit')) + '">' + line + '</span>' +
    '<button type="button" class="btn btn-sm btn-outline-primary ' + btnEditClass + '" data-id="' + esc(String(rowData[rowPk])) + '">' + esc(btnEditText) + '</button>' +
    '<button type="button" class="btn btn-sm btn-outline-danger ' + btnDeleteClass + '" data-id="' + esc(String(rowData[rowPk])) + '">' + esc(btnDeleteText) + '</button>';
  listEl.appendChild(row);
  bindRow(row);
}

function removeRow(id){
  var rows = listEl.querySelectorAll('.' + rowClass);
  for (var i = 0; i < rows.length; i++){
    if (rows[i].getAttribute('data-id') === String(id)){ rows[i].remove(); break; }
  }
  if (listEl.querySelectorAll('.' + rowClass).length === 0 && emptyEl) emptyEl.classList.remove('d-none');
}

function updateRow(id, rowData){
  var idStr = String(id);
  var rows = listEl.querySelectorAll('.' + rowClass);
  for (var i = 0; i < rows.length; i++){
    if (rows[i].getAttribute('data-id') === idStr){
      var row = rows[i];
      row.setAttribute('data-id', idStr);
      rowDisplay.forEach(function(k){ row.setAttribute('data-' + k, String(rowData[k] || '')); });
      row.setAttribute('data-roles', JSON.stringify(rowData[rowRolesKey] || []));
      if (statusKey && rowData[statusKey] !== undefined){
        row.setAttribute('data-status', String(rowData[statusKey]));
      }
      var parts = [];
      rowDisplay.forEach(function(key, idx){
        var v = rowData[key] || '';
        if (v === '') return;
        parts.push(idx === 0 ? '<strong>' + esc(v) + '</strong>' : esc(v));
      });
      var line = parts.join(' — ') + (rowData[rowRolesKey] && rowData[rowRolesKey].length ? rolesDisplay(rowData[rowRolesKey]) : '');
      var info = row.querySelector('.' + infoClass);
      if (info) info.innerHTML = line;
      break;
    }
  }
}

function bindRow(row){
  var infoEl = row.querySelector('.' + infoClass);
  var id = row.getAttribute('data-id');
  var data = buildRowDataFromEl(row);
  var roles = data.roles || [];
  if (infoEl){
    infoEl.addEventListener('click', function(){ openEditModal(data); });
    infoEl.addEventListener('keydown', function(e){ if (e.key === 'Enter' || e.key === ' '){ e.preventDefault(); openEditModal(data); } });
  }
  var btnEdit = row.querySelector('.' + btnEditClass);
  if (btnEdit) btnEdit.addEventListener('click', function(e){ e.stopPropagation(); openEditModal(data); });
  var btnDel = row.querySelector('.' + btnDeleteClass);
  if (btnDel) btnDel.addEventListener('click', function(e){ e.stopPropagation(); doDelete(id); });
  if (btnStatusClass){
    var statusBtns = row.querySelectorAll('.' + btnStatusClass);
    for (var i = 0; i < statusBtns.length; i++){
      (function(btn){
        btn.addEventListener('click', function(e){
          e.stopPropagation();
          var targetStatus = btn.getAttribute('data-status-target');
          if (!targetStatus) return;
          doSetStatus(id, targetStatus);
        });
      })(statusBtns[i]);
    }
  }
}

function openEditModal(rowData){
  var values = {};
  for (var k in rowData){
    var targetKey = (CRUD.row_to_edit_map && CRUD.row_to_edit_map[k]) || k;
    values[targetKey] = rowData[k];
  }
  setEditValues(values);
  if (typeof bootstrap !== 'undefined' && bootstrap.Modal){
    normalizeBootstrapBackdrop();
    bootstrap.Modal.getOrCreateInstance(editModalEl).show();
    setTimeout(normalizeBootstrapBackdrop, 0);
  }
  else {
    if (PMStyle && typeof PMStyle.toggleFallbackModal === 'function') PMStyle.toggleFallbackModal(editModalEl, true);
    else { editModalEl.classList.add('show'); editModalEl.style.display = 'block'; }
  }
  var firstInput = editModalEl.querySelector('input:not([type="hidden"])');
  if (firstInput) firstInput.focus();
}

function openAddModal(){
  setAddValues({});
  if (typeof bootstrap !== 'undefined' && bootstrap.Modal){
    normalizeBootstrapBackdrop();
    bootstrap.Modal.getOrCreateInstance(addModalEl).show();
    setTimeout(normalizeBootstrapBackdrop, 0);
  }
  else {
    if (PMStyle && typeof PMStyle.toggleFallbackModal === 'function') PMStyle.toggleFallbackModal(addModalEl, true);
    else { addModalEl.classList.add('show'); addModalEl.style.display = 'block'; }
  }
  var firstInput = addModalEl.querySelector('input:not([type="hidden"])');
  if (firstInput) firstInput.focus();
}

function doDelete(id){
  if (!confirm(deleteConfirm.replace('{id}', id))) return;
  post('delete', { id: id }, function(res){
    if (res.ok){ removeRow(id); showMessage(t('forms.deleted', 'Deleted.'), false); }
    else { showMessage(res.error || t('common.response_error', 'Delete error'), true); }
  });
}

function doSetStatus(id, status){
  post('set_status', { id: id, status: status }, function(res){
    if (res.ok){
      showMessage(t('forms.status_changed', 'Status updated. Reloading page...'), false);
      setTimeout(function(){ location.reload(); }, 800);
    } else {
      showMessage(res.error || t('common.response_error', 'Status change error'), true);
    }
  });
}

function normalizeBootstrapBackdrop(){
  var backdrops = document.querySelectorAll('.modal-backdrop');
  if (!backdrops || !backdrops.length) return;

  for (var i = 0; i < backdrops.length; i++){
    backdrops[i].remove();
  }

  if (document.body){
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('padding-right');
  }
}

function hideModal(modalEl){
  if (typeof bootstrap !== 'undefined' && bootstrap.Modal){
    var inst = bootstrap.Modal.getOrCreateInstance(modalEl);
    inst.hide();
    setTimeout(normalizeBootstrapBackdrop, 0);
  }
  else {
    if (PMStyle && typeof PMStyle.toggleFallbackModal === 'function') PMStyle.toggleFallbackModal(modalEl, false);
    else { modalEl.classList.remove('show'); modalEl.style.display = 'none'; }
  }
}

editSaveBtn.addEventListener('click', function(){
  var values = getEditValues();
  var id = values.id || values[rowPk];
  if (!id) return;
  var data = { id: id };
  Object.keys(values).forEach(function(k){
    if (k === 'id' || k === 'password') return;
    var rowKey = (CRUD.edit_to_row_map && CRUD.edit_to_row_map[k]) || k;
    data[k] = values[k];
  });
  if (values.roles !== undefined) data.roles = values.roles;
  if (values.password !== undefined && values.password !== '') data.password = values.password;
  var rowDataForDisplay = {};
  // всегда сохраняем первичный ключ как строку
  rowDataForDisplay[rowPk] = String(id);
  for (var k in data){
    if (k === 'id') continue;
    var target = (CRUD.edit_to_row_map && CRUD.edit_to_row_map[k]) || k;
    rowDataForDisplay[target] = data[k];
  }
  if (data.roles !== undefined) rowDataForDisplay[rowRolesKey] = data.roles;
  post('update', data, function(res){
    if (res.ok){
      hideModal(editModalEl);
      if (CRUD.reload_on_edit_success){
        showMessage(t('forms.status_changed', 'Changes saved. Reloading page...'), false);
        setTimeout(function(){ location.reload(); }, 800);
      } else {
        updateRow(id, rowDataForDisplay);
        showMessage(t('forms.changes_saved', 'Changes saved.'), false);
      }
    } else { showMessage(res.error || t('common.response_error', 'Save error'), true); }
  });
});

addSaveBtn.addEventListener('click', function(){
  var values = getAddValues();
  var data = {};
  Object.keys(values).forEach(function(k){ data[k] = values[k]; });
  post('add', data, function(res){
    if (res.ok && res.user){
      if (CRUD.reload_on_edit_success){
        hideModal(addModalEl);
        showMessage(t('forms.added', 'Added. Reloading page...'), false);
        setTimeout(function(){ location.reload(); }, 800);
      } else {
        addRow(res.user);
        showMessage(t('forms.added', 'Added.'), false);
        hideModal(addModalEl);
      }
    } else { showMessage(res.error || t('common.response_error', 'Add error'), true); }
  });
});

if (btnAdd) btnAdd.addEventListener('click', openAddModal);
var rows = listEl.querySelectorAll('.' + rowClass);
for (var i = 0; i < rows.length; i++) bindRow(rows[i]);

  // Клик по .tr-action-edit — открыть модалку: при get_user_on_edit загружаем пользователя по id, иначе из data-*
  document.addEventListener('click', function(e) {
    var a = e.target.closest('.tr-action-edit');
    if (!a) return;
    e.preventDefault();
    var id = a.getAttribute('data-id') || '';
    // Специальный случай: страница прямых прав доступа (page_rules_access)
    if (CRUD.list_id === 'rules_access_list') {
      var notes = a.getAttribute('data-notes') || '';
      var usersStr = a.getAttribute('data-users_id_str') || '';
      var rolesStrRa = a.getAttribute('data-roles') || '[]';
      var rolesArr;
      try { rolesArr = rolesStrRa ? JSON.parse(rolesStrRa) : []; } catch (err) { rolesArr = []; }
      var rowData = { id: id, roles_name: id, notes: notes, users_id_str: usersStr, roles: rolesArr };
      openEditModal(rowData);
      return;
    }
    if (CRUD.get_user_on_edit && id) {
      post('get_user', { id: id }, function(res) {
        if (res.ok && res.user) {
          setEditValues(res.user);
          if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            normalizeBootstrapBackdrop();
            bootstrap.Modal.getOrCreateInstance(editModalEl).show();
            setTimeout(normalizeBootstrapBackdrop, 0);
          }
          else {
            if (PMStyle && typeof PMStyle.toggleFallbackModal === 'function') PMStyle.toggleFallbackModal(editModalEl, true);
            else { editModalEl.classList.add('show'); editModalEl.style.display = 'block'; }
          }
          var firstInput = editModalEl.querySelector('input:not([type="hidden"]), textarea');
          if (firstInput) firstInput.focus();
        } else { showMessage(res.error || t('common.response_error', 'Load error'), true); }
      });
    } else {
      var rowData = { id: id, login: a.getAttribute('data-login') || '', email: a.getAttribute('data-email') || '', name: a.getAttribute('data-name') || '' };
      var rolesStr = a.getAttribute('data-roles') || '[]';
      try { rowData.roles = rolesStr ? JSON.parse(rolesStr) : []; } catch (err) { rowData.roles = []; }
      openEditModal(rowData);
    }
  });
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initCrud);
else initCrud();

})();
