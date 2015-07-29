(function() {
  var $, updateSelected;

  $ = jQuery;

  updateSelected = function() {
    $('.input.checkbox input').parent().removeClass('selected');
    return $('.input.checkbox input:checked').parent().addClass('selected');
  };

  $(function() {
    updateSelected();
    $('.input.checkbox input').change(updateSelected);
    return $('#MigrationAdminIndexForm').submit(function(e) {
      if ($('.modelList .checkbox.selected[deleted_count!=0]').length) {
        if (!confirm($('#MigrationAdminIndexForm').attr('deletion_confim'))) {
          return e.preventDefault();
        }
      }
    });
  });

}).call(this);

//# sourceMappingURL=list_models.js.map
