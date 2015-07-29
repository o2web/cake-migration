$ = jQuery
updateSelected = ->
  # if window.console
  #   console.log($(".input.checkbox input:not(:checked)").parent())
  $('.input.checkbox input').parent().removeClass 'selected'
  $('.input.checkbox input:checked').parent().addClass 'selected'

$ ->
  updateSelected()
  $('.input.checkbox input').change updateSelected
  $('#MigrationAdminIndexForm').submit (e) ->
    # console.log $('.modelList .checkbox.selected[deleted_count!=0]')
    if $('.modelList .checkbox.selected[deleted_count!=0]').length
      if !confirm($('#MigrationAdminIndexForm').attr('deletion_confim'))
        e.preventDefault()
