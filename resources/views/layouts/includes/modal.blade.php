@isset($modal)
<div class="modal fade" tabindex="-1" role="dialog" id="modal">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content rounded">
            <div class="modal-header">
                <button class="close p-0" type="button" data-dismiss="modal" aria-label="{{ __('chiudi') }}">
                    <svg class="icon icon-lg">
                        <use xlink:href="{{ asset('svg/sprite.svg#it-close') }}"></use>
                    </svg>
                </button>
            </div>
            <div class="modal-body px-5 pb-5">
                <div class="row">
                    <div class="col-12">
                        <svg class="icon icon-xl icon-primary mb-2"><use xlink:href="{{ asset('svg/sprite.svg') }}#{{ $modal['icon'] }}"></use></svg>
                    </div>
                    <div class="col-sm-{{ isset($modal['image']) ? '8' : '12' }}">
                        <h3>{{ $modal['title'] }}</h3>
                        <p>{!! nl2br($modal['message']) !!}</p>
                    </div>
                    @isset($modal['afterMessage'])
                    {!! $modal['afterMessage'] !!}
                    @endisset
                    @isset($modal['image'])
                    <div class="col-sm-4 d-flex align-items-center justify-content-center">
                        <img src="{{ $modal['image'] }}" alt="">
                    </div>
                    @endisset
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>$('#modal').modal()</script>
@endpush
@endisset
