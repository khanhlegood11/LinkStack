@php
  $altEnvContent = \App\Support\RuntimeSettings::hasEnvFile()
    ? file_get_contents(base_path('.env'))
    : \App\Support\RuntimeSettings::renderEditableEnv();
  $altEnvLines = max(substr_count((string) $altEnvContent, "\n") + 1, 10);
@endphp
<section class="shadow text-gray-400">
  <h2 class="mb-4 card-header"><i class="bi bi-pencil-square"> .ENV</i></h2>
  <div class="card-body p-0 p-md-3">
  
          <form action="{{ route('editENV') }}" method="post">
            @csrf
            <div class="form-group">
              <label>{{__('messages.Strings with a # in front of them are comments and wont affect anything')}}</label>
              <textarea style="width:100%!important;display:none;" class="form-control" name="altConfig" rows="{{ $altEnvLines }}">{{ $altEnvContent }}</textarea>
              <div id="editor2" style="width:100%; height:<?php echo $altEnvLines * 24 + 50;?>px;" class="form-control" name="altConfig" rows="280">{{ $altEnvContent }}</div>
            </div>
            <button type="submit" class="mt-3 ml-3 btn btn-info">{{__('messages.Save')}}</button>
          </form>
  
  <script src="{{ asset('assets/external-dependencies/ace.js') }}" type="text/javascript" charset="utf-8"></script>
  <script>
  var editor = ace.edit("editor2");
  editor.setTheme("ace/theme/xcode");
  editor.getSession().setMode("ace/mode/ruby");
  </script>
  <script>
  editor.getSession().on('change', function(e) {
  $('textarea[name=altConfig]').val(editor.getSession().getValue());
  });
  </script>
  
  
  </div>
  </section>
