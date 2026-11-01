@php
  $advancedConfigPath = is_file(base_path('config/advanced-config.php'))
    ? base_path('config/advanced-config.php')
    : base_path('storage/templates/advanced-config.php');
  $advancedConfigContent = file_get_contents($advancedConfigPath);
  $advancedConfigLines = max(count(file($advancedConfigPath) ?: []), 10);
@endphp
<p>{{__('messages.AC.description')}}</p>
@if(env('VERCEL'))
<div class="alert alert-info">
  <code>config/advanced-config.php</code> is bundled inside the container image at build time and is read-only at runtime on Vercel. Standard settings in the <strong>{{__('messages.Config')}}</strong> tab are saved durably in PostgreSQL.
</div>
@endif
<form action="{{ route('editAC') }}" method="post" id="editForm">
  @csrf
  <div class="form-group">
    <label>{{__('messages.Advanced Configuration file.')}}</label>
    <textarea style="width:100%;display:none;" class="form-control" name="AdvancedConfig" rows="280">{{ $advancedConfigContent }}</textarea>
    <div id="editor" style="width:100%; height:<?php echo $advancedConfigLines * 24 + 15;?>px; background-color:transparent !important;" class="form-control border-1 border-light" name="AdvancedConfig" rows="280">{{ $advancedConfigContent }}</div>
  </div>
  <button type="submit" class="btn btn-primary" id="saveBtn" @if(env('VERCEL')) disabled @endif>{{__('messages.Save')}}</button>
  @if(!env('VERCEL'))
  <a class="btn btn-danger confirmation" href="#" id="restoreDefaultsBtn">{{__('messages.Restore defaults')}}</a>
  @endif
</form>

<script src="{{ asset('assets/external-dependencies/ace.js') }}" type="text/javascript" charset="utf-8"></script>
<script>
  var editor = ace.edit("editor");
  @if(env('VERCEL'))
  editor.setReadOnly(true);
  @endif

  editor.getSession().on('change', function(e) {
    $('textarea[name=AdvancedConfig]').val(editor.getSession().getValue());
  });

  $('#restoreDefaultsBtn').on('click', function(e) {
    e.preventDefault();

    var isAdvancedConfig = $(this).hasClass('confirmation');
    var confirmationMessage = "Are you sure?";
    
    if (isAdvancedConfig) {
      $('#editForm').append('<input type="hidden" name="ResetAdvancedConfig" value="RESET_DEFAULTS">');
    }

    if (confirm(confirmationMessage)) {
      $('#editForm').submit();
    }
  });
</script>
