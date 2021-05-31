<div class="it-header-navbar-wrapper{{ $isSuperAdmin ? ' super-admin' : '' }}">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <nav class="navbar navbar-expand-lg" aria-label="{{ __('navigazione principale') }}">
                    <button class="custom-navbar-toggler" type="button" aria-controls="main-nav" aria-expanded="false" aria-label="{{ __('apri il menu di navigazione') }}" data-target="#main-nav">
                        <svg class="icon">
                            <use xlink:href="{{ asset('svg/sprite.svg#it-burger') }}"></use>
                        </svg>
                    </button>
                    <div class="navbar-collapsable" id="main-nav">
                        <div class="overlay"></div>
                        <div class="close-div sr-only">
                            <button class="btn close-menu" type="button"><span class="it-close"></span>{{ __('chiudi') }}</button>
                        </div>
                        <div class="menu-wrapper align-items-center">
                            @if ($authUser || $spidAuthUser)
                            <ul class="navbar-nav primary">
                                @foreach ($primaryMenuArray as $primaryMenuItem)
                                <li class="nav-item">
                                    <a class="nav-link{{ $primaryMenuItem['active'] ? ' active' : '' }}{{ $primaryMenuItem['disabled'] ? ' disabled' : '' }}"
                                        {{ $primaryMenuItem['disabled'] ? 'aria-disabled="true"' : '' }}
                                        href="{{ $primaryMenuItem['url'] }}">
                                        <span>{{ __($primaryMenuItem['name']) }}</span>
                                    </a>
                                </li>
                                @endforeach
                            </ul>
                            @endif
                            @if ($publicAdministrationShowSelector)
                            <div class="col-lg-4 ml-auto">
                                @include('layouts.includes.public_administration_selector')
                            </div>
                            @else
                            <ul class="navbar-nav secondary ml-auto">
                                @foreach (config('site.menu_items.secondary') as $secondaryMenuItem)
                                @if (
                                (
                                    $secondaryMenuItem &&
                                    !array_key_exists('requires', $secondaryMenuItem)
                                )
                                ||
                                (
                                    !$secondaryMenuItem["requires"]["auth"]
                                ) ||
                                (
                                    $secondaryMenuItem["requires"]["auth"] &&
                                    isset($authUser) && isset($spidAuthUser) &&
                                    ( $authUser || $spidAuthUser ) && 
                                    (
                                        (
                                            !$secondaryMenuItem["requires"]["publicAdministration"]
                                        ) ||
                                        (
                                            $secondaryMenuItem["requires"]["publicAdministration"] &&
                                            isset($hasPublicAdministration) && $hasPublicAdministration
                                        )
                                    )
                                )
                                )
                                    <li class="nav-item">
                                        <a class="nav-link{{ request()->route()->named($secondaryMenuItem['route']) ? ' active' : '' }}" href="{{ route($secondaryMenuItem['route']) }}">
                                            <span>{{ __($secondaryMenuItem['name']) }}</span>
                                        </a>
                                    </li>
                                @endif
                                @endforeach
                            </ul>
                            @endif
                        </div>
                    </div>
                </nav>
            </div>
        </div>
    </div>
</div>
