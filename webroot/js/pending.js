// Generated by CoffeeScript 1.8.0
(function() {
  var $;

  $ = jQuery;

  $(function() {
    return $('#MigrationNodeIncludeMode').change(function() {
      console.log($(".migrated input[type=checkbox]"));
      return $(".migrated .checkbox input[type=checkbox]").each(function() {
        return $(this).attr("checked", !$(this).attr("checked"));
      });
    });
  });

}).call(this);

//# sourceMappingURL=pending.js.map
