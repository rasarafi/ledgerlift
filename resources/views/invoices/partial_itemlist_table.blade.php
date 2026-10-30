{{--
    partial_itemlist_table.php with LEGACY_CALCULATION=true: the item discount comes after the tax
    rate, and the totals carry the invoice taxes and the global discount
    (layout/partial/itemlist_table_item_discount_input.php and _show.php,
    partial_itemlist_table_invoice_discount.php, inlined).
--}}
@php
    $invoiceDisabled = $invoice->is_read_only != 1 ? '' : ' disabled="disabled"';
    $currencySymbol = get_setting('currency_symbol');
@endphp
<div class="table-responsive">
    <table id="item_table" class="items table table-condensed table-bordered no-margin">

        <thead style="display:none">
        <tr>
            <th></th>
            <th>{!! __('ip.item') !!}</th>
<!--
            <th>{!! __('ip.description') !!}</th>
-->
            <th class="amount">{!! __('ip.quantity') !!}</th>
            <th class="amount">{!! __('ip.price') !!}</th>
            <th class="amount">{!! __('ip.tax_rate') !!}</th>
            <th class="amount">{!! __('ip.item_discount') !!}</th>
<!--
            <th class="amount">{!! __('ip.subtotal') !!}</th>
            <th class="amount">{!! __('ip.tax') !!}</th>
-->
            <th class="amount">{!! __('ip.total') !!}</th>
            <th></th>
        </tr>
        </thead>

        <tbody id="new_row" style="display:none">
        <tr>
            <td rowspan="2" class="td-icon">
                <i class="fa fa-arrows cursor-move"></i>
                @if ($invoice->invoice_is_recurring)
                    <br/>
                    <i title="{!! __('ip.recurring') !!}"
                       class="js-item-recurrence-toggler cursor-pointer fa fa-calendar-o text-muted"></i>
                    <input type="hidden" name="item_is_recurring" value=""/>
                @endif
            </td>
            <td class="td-text">
                <input type="hidden" name="invoice_id" value="{{ $invoiceId }}">
                <input type="hidden" name="item_id" value="">
                <input type="hidden" name="item_product_id" value="">
                <input type="hidden" name="item_task_id" class="item-task-id" value="">

                <div class="input-group">
                    <span class="input-group-addon">{!! __('ip.item') !!}</span>
                    <input type="text" name="item_name" class="form-control" value="">
                </div>
            </td>
            <td class="td-amount td-quantity">
                <div class="input-group">
                    <span class="input-group-addon">{!! __('ip.quantity') !!}</span>
                    <input type="text" name="item_quantity" class="form-control amount" value="">
                </div>
            </td>
            <td class="td-amount">
                <div class="input-group">
                    <span class="input-group-addon">{!! __('ip.price') !!}</span>
                    <input type="text" name="item_price" class="form-control amount" value="">
                    <div class="input-group-addon">{!! $currencySymbol !!}</div>
                </div>
            </td>
            <td class="td-amount">
                <div class="input-group">
                    <span class="input-group-addon">{!! __('ip.tax_rate') !!}</span>
                    <select name="item_tax_rate_id" class="form-control">
                        <option value="0">{!! __('ip.none') !!}</option>
@foreach ($taxRates as $taxRate)
                        <option value="{{ $taxRate->tax_rate_id }}"
                            {!! get_setting('default_item_tax_rate') == $taxRate->tax_rate_id ? 'selected="selected"' : '' !!}>
                            {!! format_amount($taxRate->tax_rate_percent).'% - '.e($taxRate->tax_rate_name) !!}
                        </option>
@endforeach
                    </select>
                </div>
            </td>
            <td class="td-amount">
                <div class="input-group">
                    <span class="input-group-addon">{!! __('ip.discount') !!}</span>
                    <input type="text" name="item_discount_amount" class="form-control amount"
                           value=""{!! $invoiceDisabled !!}
                           data-toggle="tooltip" data-placement="bottom" title="{!! __('ip.item_discount') !!}">
                    <span class="input-group-addon">{!! $currencySymbol.' '.__('ip.per_item') !!}</span>
                </div>
            </td>
            <td class="td-icon text-right td-vert-middle">
                <button type="button" class="btn_delete_item btn btn-link btn-sm" title="{!! __('ip.delete') !!}">
                    <i class="fa fa-trash-o text-danger"></i>
                </button>
            </td>
        </tr>
        <tr>
@if ($invoice->sumex_id == '')
            <td class="td-textarea">
                <div class="input-group">
                    <span class="input-group-addon">{!! __('ip.description') !!}</span>
                    <textarea name="item_description" class="form-control"></textarea>
                </div>
            </td>
@else
            <td class="td-date">
                <div class="input-group">
                    <span class="input-group-addon">{!! __('ip.date') !!}</span>
                    <input type="text" name="item_date" class="form-control datepicker"
                           value="{{ format_date(date('y-m-d')) }}"{!! $invoiceDisabled !!}>
                </div>
            </td>
@endif
            <td class="td-amount">
                <div class="input-group">
                    <span class="input-group-addon">{!! __('ip.product_unit') !!}</span>
                    <select name="item_product_unit_id" class="form-control">
                        <option value="0">{!! __('ip.none') !!}</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->unit_id }}">
                                {{ $unit->unit_name }}/{{ $unit->unit_name_plrl }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </td>
            <td class="td-amount td-vert-middle">
                <span>{!! __('ip.subtotal') !!}</span><br/>
                <span name="subtotal" class="amount"></span>
            </td>
            <td class="td-amount td-vert-middle">
                <span>{!! __('ip.tax') !!}</span><br/>
                <span name="item_tax_total" class="amount"></span>
            </td>
            <td class="td-amount td-vert-middle">
                <span>{!! __('ip.discount') !!}</span><br/>
                <span data-toggle="tooltip" data-placement="bottom" title="{!! __('ip.item_discount') !!}"
                      class="amount"></span>
            </td>
            <td class="td-amount td-vert-middle">
                <span>{!! __('ip.total') !!}</span><br/>
                <span name="item_total" class="amount"></span>
            </td>
        </tr>
        </tbody>

@foreach ($items as $item)
        <tbody class="item">
        <tr>
            <td rowspan="2" class="td-icon">
                <i class="fa fa-arrows cursor-move"></i>
@if ($invoice->invoice_is_recurring)
@php
    // An item without a recurrence state counts as recurring.
    $recurring = $item->item_is_recurring == 1 || $item->item_is_recurring === null;
@endphp
                <br/>
                <i title="{!! __('ip.recurring') !!}"
                   class="js-item-recurrence-toggler cursor-pointer fa {{ $recurring ? 'fa-calendar-check-o text-success' : 'fa-calendar-o text-muted' }}"></i>
                <input type="hidden" name="item_is_recurring" value="{{ $recurring ? '1' : '0' }}"/>
@endif
            </td>
            <td class="td-text">
                <input type="hidden" name="invoice_id" value="{{ $invoiceId }}">
                <input type="hidden" name="item_id" value="{{ $item->item_id }}"{!! $invoiceDisabled !!}>
                <input type="hidden" name="item_task_id" class="item-task-id"
                       value="{{ $item->item_task_id ?: '' }}">
                <input type="hidden" name="item_product_id" value="{{ $item->item_product_id }}">

                <div class="input-group">
                    <span class="input-group-addon">{!! __('ip.item') !!}</span>
                    <input type="text" name="item_name" class="form-control"
                           value="{{ $item->item_name }}"{!! $invoiceDisabled !!}>
                </div>
            </td>
            <td class="td-amount td-quantity">
                <div class="input-group">
                    <span class="input-group-addon">{!! __('ip.quantity') !!}</span>
                    <input type="text" name="item_quantity" class="form-control amount"
                           value="{!! format_quantity($item->item_quantity) !!}"{!! $invoiceDisabled !!}>
                </div>
            </td>
            <td class="td-amount">
                <div class="input-group">
                    <span class="input-group-addon">{!! __('ip.price') !!}</span>
                    <input type="text" name="item_price" class="form-control amount"
                           value="{!! format_amount($item->item_price) !!}"{!! $invoiceDisabled !!}>
                    <div class="input-group-addon">{!! $currencySymbol !!}</div>
                </div>
            </td>
            <td class="td-amount">
                <div class="input-group">
                    <span class="input-group-addon">{!! __('ip.tax_rate') !!}</span>
                    <select name="item_tax_rate_id" class="form-control"{!! $invoiceDisabled !!}>
                        <option value="0">{!! __('ip.none') !!}</option>
@foreach ($taxRates as $taxRate)
                        <option value="{{ $taxRate->tax_rate_id }}"
                            {!! $item->item_tax_rate_id == $taxRate->tax_rate_id ? 'selected="selected"' : '' !!}>
                            {!! format_amount($taxRate->tax_rate_percent).'% - '.e($taxRate->tax_rate_name) !!}
                        </option>
@endforeach
                    </select>
                </div>
            </td>
            <td class="td-amount">
                <div class="input-group">
                    <span class="input-group-addon">{!! __('ip.discount') !!}</span>
                    <input type="text" name="item_discount_amount" class="form-control amount"
                           value="{!! format_amount($item->item_discount_amount) !!}"{!! $invoiceDisabled !!}
                           data-toggle="tooltip" data-placement="bottom" title="{!! __('ip.item_discount') !!}">
                    <span class="input-group-addon">{!! $currencySymbol.' '.__('ip.per_item') !!}</span>
                </div>
            </td>
                <td class="td-icon text-right td-vert-middle">
@if ($invoice->is_read_only != 1)
                    <button type="button" class="btn_delete_item btn btn-link btn-sm" title="{!! __('ip.delete') !!}"
                            data-item-id="{{ $item->item_id }}">
                        <i class="fa fa-trash-o text-danger"></i>
                    </button>
@endif
                </td>
            </tr>

            <tr>
@if ($invoice->sumex_id == '')
                    <td class="td-textarea">
                        <div class="input-group">
                            <span class="input-group-addon">{!! __('ip.description') !!}</span>
                            <textarea name="item_description" class="form-control"{!! $invoiceDisabled !!}
                            >{{ $item->item_description }}</textarea>
                        </div>
                    </td>
@else
                    <td class="td-date">
                        <div class="input-group">
                            <span class="input-group-addon">{!! __('ip.date') !!}</span>
                            <input type="text" name="item_date" class="form-control datepicker"
                                   value="{{ format_date($item->item_date) }}"{!! $invoiceDisabled !!}>
                        </div>
                    </td>
@endif

                <td class="td-amount">
                    <div class="input-group">
                        <span class="input-group-addon">{!! __('ip.product_unit') !!}</span>
                        <select name="item_product_unit_id" class="form-control">
                            <option value="0">{!! __('ip.none') !!}</option>
@foreach ($units as $unit)
                            <option value="{{ $unit->unit_id }}"
                                {!! $item->item_product_unit_id == $unit->unit_id ? 'selected="selected"' : '' !!}>
                                {{ $unit->unit_name }}/{{ $unit->unit_name_plrl }}
                            </option>
@endforeach
                        </select>
                    </div>
                </td>
                <td class="td-amount td-vert-middle">
                    <span>{!! __('ip.subtotal') !!}</span><br/>
                    <span name="subtotal" class="amount">
                        {!! format_currency($item->item_subtotal) !!}
                    </span>
                </td>
                <td class="td-amount td-vert-middle">
                    <span>{!! __('ip.tax') !!}</span><br/>
                    <span name="item_tax_total" class="amount">
                        {!! format_currency($item->item_tax_total) !!}
                    </span>
                </td>
                <td class="td-amount td-vert-middle">
                    <span>{!! __('ip.discount') !!}</span><br/>
                    <span data-toggle="tooltip" data-placement="bottom" title="{!! __('ip.item_discount') !!}"
                          class="amount">{!! format_currency($item->item_discount) !!}</span>
                </td>
                <td class="td-amount td-vert-middle">
                    <span>{!! __('ip.total') !!}</span><br/>
                    <span name="item_total" class="amount">
                        {!! format_currency($item->item_total) !!}
                    </span>
                </td>
            </tr>
            </tbody>
@endforeach

    </table>
</div>

<br>

<div class="row">
    <div class="col-xs-12 col-md-4">
        <div class="btn-group">
            @if ($invoice->is_read_only != 1)
                <a href="javascript:void(0);" class="btn_add_row btn btn-sm btn-default">
                    <i class="fa fa-plus"></i> {!! __('ip.add_new_row') !!}
                </a>
                <a href="javascript:void(0);" class="btn_add_product btn btn-sm btn-default">
                    <i class="fa fa-database"></i>
                    {!! __('ip.add_product') !!}
                </a>
                <a href="javascript:void(0);" class="btn_add_task btn btn-sm btn-default{{ get_setting('projects_enabled') == 1 ? '' : ' hidden' }}">
                    <i class="fa fa-database"></i> {!! __('ip.add_task') !!}
                </a>
            @endif
        </div>
    </div>

    <div class="col-xs-12 visible-xs visible-sm"><br></div>

    <div class="col-xs-12 col-md-6 col-md-offset-2 col-lg-4 col-lg-offset-4">
        <table class="table table-bordered text-right">
            <tr>
                <td style="width: 40%;">{!! __('ip.subtotal') !!}</td>
                <td style="width: 60%;"
                    class="amount">{!! format_currency($invoice->invoice_item_subtotal) !!}</td>
            </tr>
            <tr>
                <td>{!! __('ip.item_tax') !!}</td>
                <td class="amount">{!! format_currency($invoice->invoice_item_tax_total) !!}</td>
            </tr>
            <tr>
                <td>{!! __('ip.invoice_tax') !!}</td>
                <td>
@forelse ($invoiceTaxRates as $invoiceTaxRate)
                    <form method="post"
                        action="{{ url('invoices/delete_invoice_tax/'.$invoice->invoice_id.'/'.$invoiceTaxRate->invoice_tax_rate_id) }}">
                        <input type="hidden" name="_ip_csrf" value="{{ csrf_token() }}">
                        <button type="submit" class="btn btn-xs btn-link" onclick="var Y=confirm('{!! __('ip.delete_tax_warning') !!}');if(Y)show_loader();return Y;">
                            <i class="fa fa-trash-o"></i>
                        </button>
                        <span class="text-muted">
                            {!! e($invoiceTaxRate->invoice_tax_rate_name).' '.format_amount($invoiceTaxRate->invoice_tax_rate_percent).'%' !!}
                        </span>
                        <span class="amount">
                            {!! format_currency($invoiceTaxRate->invoice_tax_rate_amount) !!}
                        </span>
                    </form>
@empty
                    {!! format_currency('0') !!}
@endforelse
                </td>
            </tr>
            <tr>
                <td class="td-vert-middle">{!! __('ip.global_discount') !!}</td>
                <td class="clearfix">
                    <div class="discount-field">
                        <div class="input-group input-group-sm">
                            <input id="invoice_discount_amount" name="invoice_discount_amount" aria-label="{!! __('ip.global_discount') !!}"
                                   value="{!! format_amount($invoice->invoice_discount_amount != 0 ? $invoice->invoice_discount_amount : '') !!}"
                                   class="discount-option form-control amount"{!! $invoiceDisabled !!}>
                            <span class="input-group-addon">{!! $currencySymbol !!}</span>
                        </div>
                    </div>
                    <div class="discount-field">
                        <div class="input-group input-group-sm">
                            <input id="invoice_discount_percent" name="invoice_discount_percent" aria-label="{!! __('ip.global_discount') !!} %"
                                   value="{!! format_amount($invoice->invoice_discount_percent != 0 ? $invoice->invoice_discount_percent : '') !!}"
                                   class="discount-option form-control amount"{!! $invoiceDisabled !!}>
                            <span class="input-group-addon">%</span>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td>{!! __('ip.total') !!}</td>
                <td class="amount"><b>{!! format_currency($invoice->invoice_total) !!}</b></td>
            </tr>
            <tr>
                <td>{!! __('ip.paid') !!}</td>
                <td class="amount"><b>{!! format_currency($invoice->invoice_paid) !!}</b></td>
            </tr>
            <tr>
                <td><b>{!! __('ip.balance') !!}</b></td>
                <td class="amount"><b>{!! format_currency($invoice->invoice_balance) !!}</b></td>
            </tr>
        </table>
    </div>
</div>
