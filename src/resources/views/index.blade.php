@extends('web::layouts.grids.12')

@section('title', trans('marketorders::global.title'))
@section('page_header', trans('marketorders::global.title'))
@section('page_description', trans('marketorders::global.description'))

@section('full')
  <div class="row">
    <div class="col-lg-7">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">{{ trans('marketorders::global.characters') }}</h3>
        </div>
        <div class="card-body p-0">
          <table class="table table-sm table-striped mb-0">
            <thead>
              <tr>
                <th>{{ trans('marketorders::global.col_character') }}</th>
                <th class="text-right">{{ trans('marketorders::global.col_sell') }}</th>
                <th class="text-right">{{ trans('marketorders::global.col_buy') }}</th>
                <th>{{ trans('marketorders::global.col_scope') }}</th>
                <th>{{ trans('marketorders::global.col_online') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($characters as $c)
                <tr>
                  <td>{!! img('characters', 'portrait', $c['id'], 32, ['class' => 'img-circle eve-icon small-icon']) !!} {{ $c['name'] }}</td>
                  <td class="text-right">{{ $c['sell'] }}</td>
                  <td class="text-right">{{ $c['buy'] }}</td>
                  <td>
                    @if($c['scope'])
                      <span class="badge badge-success">{{ trans('marketorders::global.scope_ok') }}</span>
                    @else
                      <span class="badge badge-warning" title="{{ trans('marketorders::global.scope_missing_help') }}">{{ trans('marketorders::global.scope_missing') }}</span>
                    @endif
                  </td>
                  <td>
                    @if($c['online'] === true)
                      <span class="badge badge-success">{{ trans('marketorders::global.online_yes') }}</span>
                    @elseif($c['online'] === false)
                      <span class="badge badge-secondary">{{ trans('marketorders::global.online_no') }}</span>
                    @else
                      <span class="text-muted">{{ trans('marketorders::global.online_unknown') }}</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="card-footer text-muted small">{{ trans('marketorders::global.characters_help') }}</div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">{{ trans('marketorders::global.test') }}</h3>
        </div>
        <div class="card-body">
          <p class="text-muted small">{{ trans('marketorders::global.test_help') }}</p>
          <div class="form-group">
            <label for="mo-character">{{ trans('marketorders::global.character') }}</label>
            <select id="mo-character" class="form-control">
              @foreach($characters as $c)
                <option value="{{ $c['id'] }}" @disabled(! $c['scope'])>{{ $c['name'] }}{{ $c['scope'] ? '' : ' (' . trans('marketorders::global.scope_missing') . ')' }}</option>
              @endforeach
            </select>
          </div>
          <div class="form-group">
            <label for="mo-item">{{ trans('marketorders::global.item') }}</label>
            <input id="mo-item" class="form-control" value="Tritanium" maxlength="100">
          </div>
          <button id="mo-open" type="button" class="btn btn-primary">
            <i class="fas fa-external-link-alt"></i> {{ trans('marketorders::global.open') }}
          </button>
          <div id="mo-result" class="mt-3" role="status"></div>
        </div>
      </div>
    </div>
  </div>
@stop

@push('javascript')
  <script>
    (function () {
      var url = @json(route('seatmarketorders::open-window'));
      var fallback = @json(trans('marketorders::global.error'));

      function show(ok, text) {
        $('#mo-result').empty().append($('<div>').addClass('alert mb-0 ' + (ok ? 'alert-success' : 'alert-danger')).text(text));
      }

      $('#mo-open').on('click', function () {
        var button = $(this).prop('disabled', true);
        // SeAT's layout sets the CSRF header for all jQuery requests.
        $.post(url, { character_id: $('#mo-character').val(), item: $('#mo-item').val() })
          .done(function (r) { show(true, r.message); })
          .fail(function (xhr) { show(false, (xhr.responseJSON && xhr.responseJSON.message) || fallback); })
          .always(function () { button.prop('disabled', false); });
      });
    })();
  </script>
@endpush
