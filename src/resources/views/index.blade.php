@extends('web::layouts.grids.12')

@section('title', trans('marketorders::global.title'))
@section('page_header', trans('marketorders::global.title'))
@section('page_description', trans('marketorders::global.description'))

@section('full')
  <div class="card">
    <div class="card-body pb-2">
      <div class="d-flex flex-wrap align-items-center mb-2">
        <span class="mo-label mr-2">{{ trans('marketorders::global.characters') }}</span>
        <div id="mo-chars" class="d-flex flex-wrap"></div>
      </div>
      <div class="d-flex flex-wrap align-items-center">
        <span class="mo-label mr-2">{{ trans('marketorders::global.view') }}</span>
        <div id="mo-view" class="btn-group btn-group-sm mr-3 mb-2" role="group">
          <button type="button" class="btn btn-outline-primary" data-view="split">{{ trans('marketorders::global.view_split') }}</button>
          <button type="button" class="btn btn-outline-primary" data-view="sell">{{ trans('marketorders::global.view_sell') }}</button>
          <button type="button" class="btn btn-outline-primary" data-view="buy">{{ trans('marketorders::global.view_buy') }}</button>
        </div>
        <label for="mo-location" class="mo-label mr-2 mb-2">{{ trans('marketorders::global.location') }}</label>
        <select id="mo-location" class="form-control form-control-sm mr-3 mb-2" style="width: auto; max-width: 22rem">
          <option value="">{{ trans('marketorders::global.all_locations') }}</option>
        </select>
        <label for="mo-search" class="mo-label mr-2 mb-2">{{ trans('marketorders::global.search') }}</label>
        <input id="mo-search" type="search" class="form-control form-control-sm mr-3 mb-2" style="width: 12rem" placeholder="{{ trans('marketorders::global.search_placeholder') }}">
        <div class="custom-control custom-checkbox mr-3 mb-2">
          <input type="checkbox" class="custom-control-input" id="mo-only-undercut">
          <label class="custom-control-label" for="mo-only-undercut">{{ trans('marketorders::global.only_undercut') }}</label>
        </div>
        <div class="ml-auto d-flex align-items-center mb-2">
          <small id="mo-updated" class="text-muted mr-2"></small>
          <button id="mo-refresh" type="button" class="btn btn-sm btn-outline-secondary" title="{{ trans('marketorders::global.refresh_help') }}">
            <i class="fas fa-sync-alt"></i> {{ trans('marketorders::global.refresh') }}
          </button>
        </div>
      </div>
      <div id="mo-progress" class="small text-muted d-none" role="status"></div>
      <div id="mo-error" class="alert alert-danger mt-2 mb-0 d-none" role="alert"></div>
    </div>
  </div>

  <div id="mo-kpis" class="row"></div>
  <div id="mo-tables"></div>
  <div id="mo-toast" class="mo-toast d-none" role="status"></div>
@stop

@push('head')
  <style>
    .mo-label { font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #6c757d; }
    .mo-chip { border: 1px solid #ced4da; border-radius: 1rem; padding: .1rem .6rem .1rem .15rem; margin: 0 .35rem .35rem 0; background: transparent; color: inherit; display: inline-flex; align-items: center; }
    .mo-chip.active { border-color: #007bff; background: rgba(0, 123, 255, .12); }
    .mo-chip img { width: 22px; height: 22px; border-radius: 50%; margin-right: .35rem; }
    .mo-chip .mo-count { font-size: .75rem; color: #6c757d; margin-left: .35rem; }
    .mo-dot { display: inline-block; width: .5rem; height: .5rem; border-radius: 50%; margin-left: .35rem; background: #adb5bd; }
    .mo-dot.on { background: #28a745; }
    .mo-table { font-size: .875rem; }
    .mo-table td { vertical-align: middle; white-space: nowrap; }
    .mo-table td.mo-item { white-space: normal; min-width: 14rem; }
    .mo-table .eve-icon { width: 28px; height: 28px; margin-right: .5rem; }
    .mo-item-name { font-weight: 500; }
    .mo-sub { font-size: .75rem; color: #6c757d; }
    .mo-bar { height: 3px; background: rgba(108, 117, 125, .25); border-radius: 2px; margin-top: 2px; }
    .mo-bar > span { display: block; height: 100%; background: #007bff; border-radius: 2px; }
    tr.mo-undercut > td:first-child { box-shadow: inset 3px 0 0 #dc3545; }
    tr.mo-tied > td:first-child { box-shadow: inset 3px 0 0 #fd7e14; }
    tr.mo-ahead > td:first-child { box-shadow: inset 3px 0 0 #28a745; }
    tr.mo-alone > td:first-child { box-shadow: inset 3px 0 0 #17a2b8; }
    .mo-toast { position: fixed; right: 1rem; bottom: 1rem; z-index: 1080; max-width: 28rem; }
  </style>
@endpush

@push('javascript')
  <script>
    $(function () {
      var urls = {
        orders: @json(route('seatmarketorders::orders')),
        status: @json(route('seatmarketorders::status')),
        refresh: @json(route('seatmarketorders::refresh')),
        open: @json(route('seatmarketorders::open-window'))
      };
      var t = @json(trans('marketorders::global'));
      var characters = @json($characters);
      var portraits = @json($portraits);
      var state = {
        chars: @json($selected),
        view: @json($view),
        location: '', search: '', onlyUndercut: false,
        rows: [], loadedAt: 0
      };
      var POLL_MS = 1500, MAX_WAIT_MS = 120000;
      var STATUS_ORDER = { undercut: 0, tied: 1, unknown: 2, alone: 3, ahead: 4 };

      function tr(key, repl) {
        var s = t[key] || key;
        Object.keys(repl || {}).forEach(function (k) { s = s.split(':' + k).join(repl[k]); });
        return s;
      }
      function el(tag, cls, text) {
        var e = document.createElement(tag);
        if (cls) e.className = cls;
        if (text !== undefined && text !== null) e.textContent = text;
        return e;
      }
      function charOf(id) { return characters.find(function (c) { return c.id === id; }) || { id: id, name: String(id), scope: false, online: null }; }

      var toastTimer = null;
      function toast(text, ok) {
        var box = $('#mo-toast');
        box.empty().append($('<div>').addClass('alert mb-0 shadow ' + (ok ? 'alert-success' : 'alert-warning')).text(text)).removeClass('d-none');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { box.addClass('d-none'); }, 5000);
      }
      function showError(message) { $('#mo-error').text(message || t.error).removeClass('d-none'); }
      function errorMessage(xhr) { return (xhr.responseJSON && xhr.responseJSON.message) || t.error; }

      // ---------- characters and filters ----------
      function renderChars() {
        var box = document.getElementById('mo-chars');
        box.innerHTML = '';
        var all = el('button', 'mo-chip' + (state.chars.length === characters.length ? ' active' : ''), t.all);
        all.type = 'button';
        all.style.paddingLeft = '.6rem';
        all.onclick = function () {
          state.chars = state.chars.length === characters.length ? [] : characters.map(function (c) { return c.id; });
          renderChars(); load(false);
        };
        box.appendChild(all);
        characters.forEach(function (c) {
          var b = el('button', 'mo-chip' + (state.chars.indexOf(c.id) >= 0 ? ' active' : ''));
          b.type = 'button';
          b.setAttribute('aria-pressed', state.chars.indexOf(c.id) >= 0);
          b.innerHTML = portraits[c.id] || '';
          b.appendChild(document.createTextNode(c.name));
          b.appendChild(el('span', 'mo-count', c.sell + ' / ' + c.buy));
          var dot = el('span', 'mo-dot' + (c.online ? ' on' : ''));
          dot.title = c.online ? t.online : t.offline;
          b.appendChild(dot);
          if (!c.scope) {
            var w = el('i', 'fas fa-exclamation-triangle text-warning ml-1');
            w.title = t.scope_missing;
            b.appendChild(w);
          }
          b.onclick = function () {
            var i = state.chars.indexOf(c.id);
            if (i >= 0) state.chars.splice(i, 1); else state.chars.push(c.id);
            renderChars(); load(false);
          };
          box.appendChild(b);
        });
      }

      function renderView() {
        $('#mo-view button').each(function () {
          var on = this.dataset.view === state.view;
          $(this).toggleClass('active', on).attr('aria-pressed', on);
        });
      }

      function renderLocations() {
        var sel = $('#mo-location'), keep = state.location;
        sel.find('option:not(:first)').remove();
        var seen = {};
        state.rows.forEach(function (r) { seen[r.location_id] = r.location; });
        Object.keys(seen).sort(function (a, b) { return seen[a].localeCompare(seen[b]); }).forEach(function (id) {
          sel.append($('<option>').val(id).text(seen[id]));
        });
        if (keep && seen[keep]) sel.val(keep); else { state.location = ''; sel.val(''); }
      }

      // ---------- loading ----------
      function setBusy(on) { $('#mo-refresh').prop('disabled', on); if (!on) $('#mo-progress').addClass('d-none'); }

      function load(noFetch, slow) {
        $('#mo-error').addClass('d-none');
        if (state.chars.length === 0) {
          state.rows = [];
          render();
          $.post(urls.orders, { characters: [], view: state.view, no_fetch: 1 });
          return;
        }
        setBusy(true);
        if (!state.rows.length) $('#mo-tables').html('<p class="text-muted">' + $('<span>').text(t.loading).html() + '</p>');
        $.post(urls.orders, { characters: state.chars, view: state.view, no_fetch: noFetch ? 1 : 0 })
          .done(function (data) {
            if (data.status === 'pending') {
              wait(data.batches, Date.now(), 'fetching', function (late) { load(true, late); });
              return;
            }
            setBusy(false);
            state.rows = data.rows;
            state.loadedAt = Date.now();
            $('#mo-updated').text(tr('updated', { time: data.updated }));
            if (slow) showError(t.fetch_slow);
            renderLocations();
            render();
          })
          .fail(function (xhr) { setBusy(false); showError(errorMessage(xhr)); });
      }

      // Waits for SeAT's job batches, then calls done(late). After MAX_WAIT_MS it goes on with what is there.
      function wait(batches, started, label, done) {
        $.get(urls.status, { batches: batches }).done(function (s) {
          $('#mo-progress').text(tr(label, { done: s.done, total: s.total })).removeClass('d-none');
          var late = Date.now() - started > MAX_WAIT_MS;
          if (s.finished || late) done(late && !s.finished);
          else setTimeout(function () { wait(batches, started, label, done); }, POLL_MS);
        }).fail(function (xhr) { setBusy(false); showError(errorMessage(xhr)); });
      }

      // ---------- tables ----------
      function visible(side) {
        var q = state.search.toLowerCase();
        return state.rows.filter(function (r) {
          return r.side === side
            && (!state.location || String(r.location_id) === state.location)
            && (!q || r.type.toLowerCase().indexOf(q) >= 0)
            && (!state.onlyUndercut || r.status === 'undercut' || r.status === 'tied');
        }).sort(function (a, b) {
          return (STATUS_ORDER[a.status] - STATUS_ORDER[b.status]) || a.type.localeCompare(b.type);
        });
      }

      function statusBadge(r) {
        var map = {
          undercut: ['badge-danger', r.side === 'sell' ? t.status_undercut_sell : t.status_undercut_buy],
          tied: ['badge-warning', t.status_tied],
          ahead: ['badge-success', t.status_ahead],
          alone: ['badge-info', t.status_alone],
          unknown: ['badge-secondary', t.status_unknown]
        };
        var b = el('span', 'badge ' + map[r.status][0], map[r.status][1]);
        if (r.status === 'tied') b.title = t.tied_help;
        return b;
      }

      function lockMinutes(r) {
        var left = r.lock_seconds - (Date.now() - state.loadedAt) / 1000;
        return left > 0 ? Math.ceil(left / 60) : 0;
      }

      function copyAndOpen(r) {
        var price = r.suggestion_copy;
        // Copy first: browsers allow writing the clipboard only directly inside the click.
        var copied = navigator.clipboard ? navigator.clipboard.writeText(price) : Promise.reject();
        var opened = $.post(urls.open, { character_id: r.character_id, type_id: r.type_id });
        copied.then(function () { return true; }, function () { return false; }).then(function (ok) {
          var first = ok ? tr('copied', { price: r.fmt.suggestion }) : tr('copy_failed', { price: price });
          opened.done(function (res) { toast(first + ' ' + res.message, ok); })
            .fail(function (xhr) { toast(first + ' ' + errorMessage(xhr), false); });
        });
      }

      function openOnly(r) {
        $.post(urls.open, { character_id: r.character_id, type_id: r.type_id })
          .done(function (res) { toast(res.message, true); })
          .fail(function (xhr) { toast(errorMessage(xhr), false); });
      }

      function row(r, showChar) {
        var c = charOf(r.character_id);
        var tr_ = el('tr', 'mo-' + r.status);

        var item = el('td', 'mo-item');
        var wrap = el('div', 'd-flex align-items-center');
        var icon = el('span'); icon.innerHTML = r.icon; wrap.appendChild(icon);
        var txt = el('div');
        txt.appendChild(el('div', 'mo-item-name', r.type));
        txt.appendChild(el('div', 'mo-sub', r.location));
        wrap.appendChild(txt); item.appendChild(wrap); tr_.appendChild(item);

        if (showChar) tr_.appendChild(el('td', null, c.name));

        var qty = el('td');
        qty.appendChild(el('div', null, r.fmt.remain + ' / ' + r.fmt.total));
        var bar = el('div', 'mo-bar'), fill = el('span');
        fill.style.width = Math.round(r.remain / Math.max(r.total, 1) * 100) + '%';
        bar.appendChild(fill); qty.appendChild(bar); tr_.appendChild(qty);

        tr_.appendChild(el('td', 'text-right', r.fmt.price));

        var best = el('td', 'text-right');
        if (r.status === 'undercut' || r.status === 'tied') {
          best.appendChild(el('div', null, r.fmt.best));
          if (r.status === 'undercut') best.appendChild(el('div', 'mo-sub text-danger', (r.side === 'sell' ? '−' : '+') + r.fmt.diff + ' (' + r.fmt.diff_pct + ' %)'));
        } else if (r.status === 'ahead') {
          best.appendChild(el('div', null, r.fmt.best));
          best.appendChild(el('div', 'mo-sub text-success', t.best_price));
        } else {
          best.appendChild(el('span', 'text-muted', r.status === 'alone' ? t.no_competition : t.not_readable));
        }
        tr_.appendChild(best);

        var st = el('td'); st.appendChild(statusBadge(r));
        var lock = lockMinutes(r);
        if (lock > 0) {
          var l = el('span', 'badge badge-light ml-1', tr('locked', { min: lock }));
          l.title = t.locked_help;
          st.appendChild(l);
        }
        tr_.appendChild(st);

        var sug = el('td', 'text-right');
        if (r.suggestion !== null) {
          var b = el('button', 'btn btn-sm btn-primary');
          b.type = 'button';
          b.title = tr('suggestion_help', { character: c.name });
          b.appendChild(document.createTextNode(r.fmt.suggestion + ' '));
          b.appendChild(el('i', 'fas fa-copy'));
          b.onclick = function () { copyAndOpen(r); };
          sug.appendChild(b);
        } else {
          sug.appendChild(el('span', 'text-muted', '–'));
        }
        tr_.appendChild(sug);

        var exp = el('td', r.expires_soon ? 'text-warning' : null, r.fmt.expires);
        if (r.expires_soon) exp.title = t.expires_soon;
        tr_.appendChild(exp);

        var open = el('td', 'text-right');
        var ob = el('button', 'btn btn-sm btn-outline-secondary');
        ob.type = 'button';
        ob.title = tr('open_help', { character: c.name });
        ob.appendChild(el('i', 'fas fa-external-link-alt mr-1'));
        ob.appendChild(document.createTextNode(t.open));
        ob.disabled = !c.scope;
        ob.onclick = function () { openOnly(r); };
        open.appendChild(ob);
        tr_.appendChild(open);

        return tr_;
      }

      function table(side) {
        var rows = visible(side);
        var card = el('div', 'card');
        var head = el('div', 'card-header d-flex flex-wrap align-items-center');
        var h = el('h3', 'card-title mr-2', side === 'sell' ? t.sell_orders : t.buy_orders);
        head.appendChild(h);
        head.appendChild(el('span', 'badge badge-primary mr-1', String(rows.length)));
        var bad = rows.filter(function (r) { return r.status === 'undercut'; }).length;
        if (bad) head.appendChild(el('span', 'badge badge-danger', bad + ' ' + (side === 'sell' ? t.status_undercut_sell : t.status_undercut_buy)));
        head.appendChild(el('small', 'text-muted ml-auto', side === 'sell' ? t.sell_note : t.buy_note));
        card.appendChild(head);

        if (!rows.length) {
          card.appendChild(el('div', 'card-body text-muted', t.empty));
          return card;
        }
        var showChar = state.chars.length > 1;
        var body = el('div', 'card-body p-0 table-responsive');
        var tbl = el('table', 'table table-sm table-hover mb-0 mo-table');
        var thead = el('thead'), hr = el('tr');
        var cols = [['col_item', ''], ['col_character', ''], ['col_qty', ''], ['col_price', 'text-right'], ['col_best', 'text-right'],
          ['col_status', ''], ['col_suggestion', 'text-right'], ['col_expires', ''], ['', '']];
        cols.forEach(function (col) {
          if (col[0] === 'col_character' && !showChar) return;
          hr.appendChild(el('th', col[1], col[0] ? t[col[0]] : ''));
        });
        thead.appendChild(hr); tbl.appendChild(thead);
        var tb = el('tbody');
        rows.forEach(function (r) { tb.appendChild(row(r, showChar)); });
        tbl.appendChild(tb); body.appendChild(tbl); card.appendChild(body);

        var foot = el('div', 'card-footer small text-muted');
        [['badge-danger', t.legend_undercut], ['badge-success', t.legend_ahead], ['badge-info', t.legend_alone]].forEach(function (l) {
          foot.appendChild(el('span', 'badge ' + l[0] + ' mr-1', ' '));
          foot.appendChild(el('span', 'mr-3', l[1]));
        });
        foot.appendChild(el('span', null, t.legend_click));
        card.appendChild(foot);
        return card;
      }

      // Figures for what is on screen: the chosen characters, view, location, search and undercut filter.
      // When filters hide orders, each tile also shows the figure for all orders of the chosen characters.
      function kpis() {
        var shown = (state.view !== 'buy' ? visible('sell') : []).concat(state.view !== 'sell' ? visible('buy') : []);
        var sum = function (rows) {
          var s = { sell: 0, buy: 0, bad: 0, count: rows.length };
          rows.forEach(function (r) {
            if (r.side === 'sell') s.sell += r.value; else s.buy += r.value;
            if (r.status === 'undercut') s.bad++;
          });
          return s;
        };
        var now = sum(shown), all = sum(state.rows), filtered = shown.length !== state.rows.length;
        var isk = function (v) {
          if (v >= 1e9) return (v / 1e9).toFixed(2) + ' B ISK';
          if (v >= 1e6) return (v / 1e6).toFixed(1) + ' M ISK';
          return Math.round(v).toLocaleString() + ' ISK';
        };
        var box = $('#mo-kpis').empty();
        [
          [t.kpi_sell, isk(now.sell), false, isk(all.sell)],
          [t.kpi_buy, isk(now.buy), false, isk(all.buy)],
          [t.kpi_undercut, tr('of', { count: now.bad, total: now.count }), now.bad > 0, tr('of', { count: all.bad, total: all.count })],
          [t.kpi_orders, String(now.count), false, String(all.count)]
        ].forEach(function (k) {
          var col = $('<div class="col-6 col-lg-3">');
          var card = $('<div class="info-box">');
          var content = $('<div class="info-box-content">');
          content.append($('<span class="info-box-text">').text(k[0]));
          content.append($('<span class="info-box-number">').toggleClass('text-danger', !!k[2]).text(k[1]));
          if (filtered) content.append($('<small class="text-muted">').text(tr('kpi_all', { value: k[3] })));
          box.append(col.append(card.append(content)));
        });
      }

      function render() {
        renderView();
        var box = document.getElementById('mo-tables');
        box.innerHTML = '';
        if (state.chars.length === 0) {
          $('#mo-kpis').empty();
          box.appendChild(el('p', 'text-muted', t.no_characters));
          return;
        }
        kpis();
        if (state.view !== 'buy') box.appendChild(table('sell'));
        if (state.view !== 'sell') box.appendChild(table('buy'));
      }

      // ---------- events ----------
      $('#mo-view button').on('click', function () {
        state.view = this.dataset.view;
        render();
        // Remember the view; the orders are cached, so this is cheap.
        $.post(urls.orders, { characters: state.chars, view: state.view, no_fetch: 1 });
      });
      $('#mo-location').on('change', function () { state.location = this.value; render(); });
      $('#mo-search').on('input', function () { state.search = this.value; render(); });
      $('#mo-only-undercut').on('change', function () { state.onlyUndercut = this.checked; render(); });
      $('#mo-refresh').on('click', function () {
        if (!state.chars.length) return;
        setBusy(true);
        $.post(urls.refresh, { characters: state.chars }).done(function (data) {
          if (!data.batches.length) { load(false); return; }
          wait(data.batches, Date.now(), 'refreshing', function () { load(false); });
        }).fail(function (xhr) { setBusy(false); showError(errorMessage(xhr)); });
      });
      // Keep the "locked" badges current.
      setInterval(function () { if (state.rows.some(function (r) { return lockMinutes(r) > 0; })) render(); }, 30000);

      renderChars();
      renderView();
      load(false);
    });
  </script>
@endpush
