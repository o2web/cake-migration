(function() {
  var $;

  $ = jQuery;

  $(function() {
    return $('#MigrationNodeIncludeMode').change(function() {
      return $(".migrated .checkbox input[type=checkbox]").each(function() {
        return $(this).attr("checked", !$(this).attr("checked"));
      });
    });
  });

}).call(this);

//# sourceMappingURL=pending.js.map
