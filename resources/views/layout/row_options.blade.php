{{-- The Options dropdown at the end of a reference-data row: edit, and delete as a POST with the CSRF field. --}}
<div class="options btn-group">
    <a class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" href="#">
        <i class="fa fa-cog"></i> {!! __('ip.options') !!}
    </a>
    <ul class="dropdown-menu">
        <li>
            <a href="{{ url($module.'/form/'.$id) }}">
                <i class="fa fa-edit fa-margin"></i> {!! __('ip.edit') !!}
            </a>
        </li>
        <li>
            <form action="{{ url($module.'/delete/'.$id) }}" method="POST">
                <input type="hidden" name="_ip_csrf" value="{{ csrf_token() }}">
                <button type="submit" class="dropdown-button"
                        onclick="return confirm('{!! __('ip.delete_record_warning') !!}');">
                    <i class="fa fa-trash-o fa-margin"></i> {!! __('ip.delete') !!}
                </button>
            </form>
        </li>
    </ul>
</div>
