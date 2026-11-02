@php
// theme() and themeAsset() live in app/Functions/functions.php so that both
// disk themes and database-backed themes resolve through the same helpers.
$customBackgroundExists = false;
@endphp

@foreach($information as $info) @php $GLOBALS['themeName'] = $info->theme; @endphp @endforeach

@if(theme('allow_custom_background') != "false" && hasBackground($userinfo->id))
<style>
  body {
    background-image: url('{{ backgroundUrl($userinfo->id) }}') !important;
    background-size: cover !important;
    background-attachment: fixed !important;
    background-repeat: no-repeat !important;
    background-position: center !important;
  }
</style>
@endif

@push('linkstack-head-end')
@if(theme('enable_custom_code') == "true" and theme('enable_custom_head') == "true" and env('ALLOW_CUSTOM_CODE_IN_THEMES') == 'true')
  @if(dbThemeExists($GLOBALS['themeName']))
    {!! themeCustomCode($GLOBALS['themeName'], 'head') !!}
  @else
    @includeIf($GLOBALS['themeName'] . '.extra.custom-head')
  @endif
@endif
@if($info->theme != '' and $info->theme != 'default')

  <!-- LinkStack Theme: "{{$info->theme}}" -->

  <!-- Theme details: -->
  <meta name="designer" href="{{ url('') . "/theme/@" . $littlelink_name}}" content="{{ url('') . "/theme/@" . $littlelink_name}}">

  <link rel="stylesheet" href="{{ themeFileUrl($info->theme, 'share.button.css') }}">
  @if(theme('use_default_buttons') == "true")
  <link rel="stylesheet" href="{{ asset('assets/linkstack/css/brands.css') }}">
  @else
  <link rel="stylesheet" href="{{ themeFileUrl($info->theme, 'brands.css') }}">
  @endif
  <link rel="stylesheet" href="{{ themeFileUrl($info->theme, 'skeleton-auto.css') }}">
@if(themeFileExists($info->theme, 'animations.css'))
  <link rel="stylesheet" href="{{ themeFileUrl($info->theme, 'animations.css') }}">
@else
  <link rel="stylesheet" href="{{ asset('assets/linkstack/css/animations.css') }}">
@endif

@else
  <link rel="stylesheet" href="{{ asset('assets/linkstack/css/share.button.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/linkstack/css/animations.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/linkstack/css/brands.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/linkstack/css/skeleton-auto.css') }}">
@endif
<style>.container{word-break: break-word;}</style>
@endpush

@push('linkstack-body-start')
@if(theme('enable_custom_code') == "true" and theme('enable_custom_body') == "true" and env('ALLOW_CUSTOM_CODE_IN_THEMES') == 'true')
  @if(dbThemeExists($GLOBALS['themeName']))
    {!! themeCustomCode($GLOBALS['themeName'], 'body') !!}
  @else
    @includeIf($GLOBALS['themeName'] . '.extra.custom-body')
  @endif
@endif

@if($info->theme != '' and $info->theme != 'default')
    <!-- Enables parallax background animations -->
    <div class="background-container">
    <section class="parallax-background">
      <div id="object1" class="object1"></div>
      <div id="object2" class="object2"></div>
      <div id="object3" class="object3"></div>
      <div id="object4" class="object4"></div>
      <div id="object5" class="object5"></div>
      <div id="object6" class="object6"></div>
      <div id="object7" class="object7"></div>
      <div id="object8" class="object8"></div>
      <div id="object9" class="object9"></div>
      <div id="object10" class="object10"></div>
      <div id="object11" class="object11"></div>
      <div id="object12" class="object12"></div>
    </section>
    </div>
    <!-- End of parallax background animations -->
@endif
@endpush

@push('linkstack-body-end')
@if(theme('enable_custom_code') == "true" and theme('enable_custom_body_end') == "true" and env('ALLOW_CUSTOM_CODE_IN_THEMES') == 'true')
  @if(dbThemeExists($GLOBALS['themeName']))
    {!! themeCustomCode($GLOBALS['themeName'], 'body-end') !!}
  @else
    @includeIf($GLOBALS['themeName'] . '.extra.custom-body-end')
  @endif
@endif
@endpush
@include('linkstack.modules.dynamic-contrast')
