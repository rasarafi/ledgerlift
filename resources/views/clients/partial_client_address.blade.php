<span class="client-address-street-line">
    {!! $client->client_address_1 ? e($client->client_address_1).'<br>' : '' !!}
</span>
<span class="client-address-street-line">
    {!! $client->client_address_2 ? e($client->client_address_2).'<br>' : '' !!}
</span>
<span class="client-adress-town-line">
    {!! $client->client_city ? e($client->client_city).' ' : '' !!}
    {!! $client->client_state ? e($client->client_state).' ' : '' !!}
    {!! $client->client_zip ? e($client->client_zip) : '' !!}
</span>
<span class="client-adress-country-line">
    {{-- get_country_name() echoes the list's name as is; a code that is not in the list is escaped here, which legacy does not. --}}
    {!! $client->client_country ? '<br>'.(__('countries')[$client->client_country] ?? e($client->client_country)) : '' !!}
</span>
