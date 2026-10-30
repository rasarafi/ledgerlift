@foreach ($clientNotes as $clientNote)
    <div class="panel panel-default small">
        <div class="panel-body">
            {!! nl2br(e($clientNote->client_note)) !!}
        </div>
        <div class="panel-footer text-muted">
            {{ date_from_mysql($clientNote->client_note_date, true) }}
            <span data-id="{{ $clientNote->client_note_id }}" class="delete_client_note pull-right btn btn-xs btn-danger">
                <i class="fa fa-trash-o"></i> {!! __('ip.delete') !!}
            </span>
        </div>
    </div>
@endforeach
