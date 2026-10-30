<script>
    var delay = (function () {
        var timer = 0;
        return function (callback, ms) {
            clearTimeout(timer);
            timer = setTimeout(callback, ms);
        };
    })();

    $(function () {
        $('#filter').keyup(function () {
            delay(function () {
                $.post('{{ url('filter/ajax/'.$filterMethod) }}',
                    {
                        filter_query: $('#filter').val()
                    }, function (data) {
                        $('#filter_results').html(data);
                    });
            }, 1000);
        });
    });
</script>
