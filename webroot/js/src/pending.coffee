$ = jQuery
$( -> 
  $('#MigrationNodeIncludeMode').change(->
    console.log($(".migrated input[type=checkbox]"))
    $(".migrated .checkbox input[type=checkbox]").each(->
      $(this).attr("checked", !$(this).attr("checked"))
    )
  )
)