jQuery(function ($) {
    var boxes = { 
        severity: $('select.clgs-severity'),
        seen: $('input.clgs-seen')
    };
    boxes.severity.change(function (e) {
        boxes.severity.val(this.value)
            .attr('class', 'clgs-severity severity-' + this.value);
    });
    boxes.seen.change(function (e) {
        boxes.seen.prop('checked', this.checked);
    });

    $('.wp-list-table span[data-date]').each(function () {
        var date = new Date($(this).data('date') * 1000); // JS wants milliseconds!
        $(this).text(date.toLocaleString());
    });
});
