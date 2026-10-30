    <div class="table-responsive">
        <table class="table table-hover table-striped">

            <thead>
            <tr>
                <th>{!! __('ip.family_name') !!}</th>
                <th>{!! __('ip.options') !!}</th>
            </tr>
            </thead>

            <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td><a href="{{ url('families/form/'.$row->family_id) }}"><i class="fa fa-edit"></i> {{ $row->family_name }}</a></td>
                    <td>
                        @include('layout.row_options', ['module' => 'families', 'id' => $row->family_id])
                    </td>
                </tr>
            @endforeach
            </tbody>

        </table>
    </div>
