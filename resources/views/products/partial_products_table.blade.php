@php($sumexActive = get_setting('sumex') == '1')
    <div class="table-responsive">
        <table class="table table-hover table-striped">

            <thead>
            <tr>
                <th>{!! __('ip.family') !!}</th>
                <th>{!! __('ip.product_sku') !!}</th>
                <th>{!! __('ip.product_name') !!}</th>
                <th>{!! __('ip.product_description') !!}</th>
                <th class="amount last">{!! __('ip.product_price') !!}</th>
                <th>{!! __('ip.product_unit') !!}</th>
                <th>{!! __('ip.tax_rate') !!}</th>
@if ($sumexActive)
                <th>{!! __('ip.product_tariff') !!}</th>
@endif
                <th>{!! __('ip.options') !!}</th>
            </tr>
            </thead>

            <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td><a href="{{ url('families/form').'/'.$row->family_id }}"><i class="fa fa-edit"></i> {{ $row->family_name }}</a></td>
                    <td>{{ $row->product_sku }}</td>
                    <td><a href="{{ url('products/form/'.$row->product_id) }}"><i class="fa fa-edit"></i> {{ $row->product_name }}</a></td>
                    <td>{!! nl2br(e($row->product_description)) !!}</td>
                    <td class="amount last">{!! format_currency($row->product_price) !!}</td>
                    <td>{{ $row->unit_name }}</td>
                    <td>{!! $row->tax_rate_id ? e($row->tax_rate_name) : __('ip.none') !!}</td>
@if ($sumexActive)
                    <td>{{ $row->product_tariff }}</td>
@endif
                    <td>
                        @include('layout.row_options', ['module' => 'products', 'id' => $row->product_id])
                    </td>
                </tr>
            @endforeach
            </tbody>

        </table>
    </div>
