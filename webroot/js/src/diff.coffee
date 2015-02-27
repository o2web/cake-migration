$ = jQuery
doDiff = ->
  $('.diff .diffProcessed').removeClass('diffProcessed')
  $('.diff .fieldRow .changes').remove()
  $('.diff .different .local .val').each( ->
    $remoteVal = $(this).closest('.fieldRow').find('.remote.active .val')
    diff = JsDiff.diffWords(protectTags($(this).html()), protectTags($remoteVal.html()));
    localChanges = ''
    remoteChanges = ''
    diff.forEach( (part) -> 
      if part.added
        remoteChanges = addChangedSection(remoteChanges,part.value)
      else if part.removed
        localChanges = addChangedSection(localChanges,part.value)
      else
        localChanges += part.value
        remoteChanges += part.value
    )
    $(this).after("""<div class="changes">#{formatChangedTag(localChanges)}</div>""")
    $remoteVal.after("""<div class="changes">#{formatChangedTag(remoteChanges)}</div>""")
    $(this).closest('.fieldRow').addClass('diffProcessed')
  )
  
protectTags = (txt) ->
  txt.replace(/</g,' <').replace(/>/g,'> ')
  
addChangedSection = (txt,changed) ->
  if txt.match(/<([^>]*)$/)
    txt.replace(/<([^[>][^>]*)$/,'<[changedTag]$1') + changed
  else
    txt + """<span class="change">#{changed}</span>"""
    
formatChangedTag = (txt) ->
    console.log(txt);
    txt = txt.replace(/<\[changedTag\](\w*)/g,'<$1 data-changed-tag="1"')
    $("<div>#{txt}</div>").each( ->
      $('[data-changed-tag]',this).addClass('changedTag').removeAttr('data-changed-tag')
    ).html()

$( -> 
  $('#remote').change( ->
  )
  doDiff()
)
