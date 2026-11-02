        <!-- Your Image Here -->
        @if(hasAvatar($userinfo->id))
        <img alt="avatar" id="avatar" class="rounded-avatar fadein" src="{{ avatarUrl($userinfo->id) }}" height="128px" width="128px" style="object-fit: cover;">
        @elseif(siteImageExists('avatar'))
        <img alt="avatar" id="avatar" class="fadein" src="{{ siteImageUrl('avatar')}}" height="128px" width="128px" style="object-fit: cover;">
        @else
        <img alt="avatar" id="avatar" class="fadein" src="{{ asset('assets/linkstack/images/logo.svg') }}" height="128px" style="width:auto;min-width:128px;object-fit: cover;">
        @endif