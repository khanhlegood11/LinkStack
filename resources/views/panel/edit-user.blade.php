@extends('layouts.sidebar')

@section('content')

<div class="conatiner-fluid content-inner mt-n5 py-0">
  <div class="row">   


      <div class="col-lg-12">
          <div class="card   rounded">
             <div class="card-body">
                <div class="row">
                    <div class="col-sm-12">  
  
                      <section class="text-gray-400">
                        <h2 class="mb-4 card-header"><i class="bi bi-person"> {{__('messages.Edit User')}}</i></h2>
                          <div class="card-body p-0 p-md-3">
                  
                        @foreach($user as $user)
                        <form action="{{ route('editUser', $user->id) }}" enctype="multipart/form-data" method="post">
                          @csrf
                              <div class="form-group col-lg-8">
                              <label>{{__('messages.Name')}}</label>
                              <input type="text" class="form-control" name="name" value="{{ $user->name }}">
                            </div>
                            <div class="form-group col-lg-8">
                              <label>{{__('messages.Email')}}</label>
                              <input type="email" class="form-control" name="email" value="{{ $user->email }}">
                            </div>
                            <div class="form-group col-lg-8">
                              <label>{{__('messages.Password')}}</label>
                              <input type="password" class="form-control" name="password" placeholder="Leave empty for no change">
                            </div>
                            
                            <div class="form-group col-lg-8">
                              <label>{{__('messages.Logo')}}</label>
                              <div class="mb-3">
                                <input type="file" class="form-control form-control-lg" name="image">
                            </div>
                            </div>
                            
                            <div class="form-group col-lg-8">
                              @if(hasAvatar($user->id))
                              <img src="{{ avatarUrl($user->id) }}" class="bd-placeholder-img img-thumbnail" width="100" height="100" draggable="false">
                              @else
                              <img src="{{ asset('assets/linkstack/images/logo.svg') }}" class="bd-placeholder-img img-thumbnail" width="100" height="100" draggable="false">
                              @endif
                              @if(hasAvatar($user->id))<br><a title="Remove icon" class="hvr-grow p-1 text-danger" style="padding-left:5px;" href="{{ route('delUserAvatar', ['id' => $user->id]) }}"><i class="bi bi-trash-fill"></i> {{__('messages.Delete')}}</a>@endif
                          </div><br>
                            
                            <div class="form-group col-lg-8">
                              <label>{{__('messages.Custom background')}}</label>
                              <div class="mb-3">
                                <input type="file" class="form-control form-control-lg" name="background">
                            </div>
                            </div>
                            <div class="form-group col-lg-8">
                                @if(!hasBackground($user->id))<p><i>{{__('messages.No image selected')}}</i></p>@endif
                                <img style="width:95%;max-width:400px;argin-left:1rem!important;border-radius:5px;" src="{{ backgroundUrl($user->id) }}">
                                @if(hasBackground($user->id))<br><a title="Remove icon" class="hvr-grow p-1 text-danger" style="padding-left:5px;" href="{{ route('delUserBackground', ['id' => $user->id]) }}"><i class="bi bi-trash-fill"></i> {{__('messages.Delete')}}</a>@endif
                                <br>
                            </div><br>

                            <label>{{__('messages.Select theme')}}</label>
                              <div class="form-group col-lg-8">
                                  <select id="theme-select" style="margin-bottom: 40px;" class="form-control" name="theme" data-base-url="{{ url('') }}/@<?= Auth::user()->littlelink_name ?>">
                                      @php $themeOptions = availableThemes(); $selectedTheme = $user->theme ?: 'default'; @endphp
                                      @foreach($themeOptions as $themeOption)
                                          <option value="{{ $themeOption['slug'] }}" data-image="{{ $themeOption['preview'] }}" @if($selectedTheme === $themeOption['slug']) selected @endif>{{ $themeOption['name'] }}</option>
                                      @endforeach
                                      <option value="default" data-image="{{ url('/assets/linkstack/images/themes/default.png') }}" @if($selectedTheme === 'default') selected @endif>Default</option>
                                  </select>
                              </div>
                            
                            <div class="form-group col-lg-8">
                              <label>{{__('messages.Page URL')}}</label>
                              <div class="input-group">
                            <div class="input-group-prepend">
                            <div class="input-group-text">{{ url('') }}/@</div>
                            </div>
                            <input type="text" class="form-control" name="littlelink_name" value="{{ $user->littlelink_name }}">
                          </div>
                        </div>
                            
                            <div class="form-group col-lg-8">
                              <label> {{__('messages.Page description')}}</label>
                              <textarea class="form-control" name="littlelink_description" rows="3">{{ $user->littlelink_description }}</textarea>
                            </div>
                            <div class="form-group col-lg-8">
                              <label for="exampleFormControlSelect1">{{__('messages.Role')}}</label>
                              <select class="form-control" name="role">
                                <option <?= ($user->role === strtolower('user')) ? 'selected' : '' ?>>user</option>
                                <option <?= ($user->role === strtolower('vip')) ? 'selected' : '' ?>>vip</option>
                                <option <?= ($user->role === strtolower('admin')) ? 'selected' : '' ?>>admin</option>
                              </select>
                            </div>
                            @endforeach
                            <button type="submit" class="mt-3 ml-3 btn btn-primary">{{__('messages.Save')}}</button>
                          </form>
                  
                            </div>
                  </section>
  
                    </div>
                </div>
             </div>
          </div>
       </div>


    </div>
  </div>

@endsection
