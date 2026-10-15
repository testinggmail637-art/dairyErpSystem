{{-- Farm name; a switcher only when the user is linked to more than one active farm. --}}
@if ($currentFarm)
    @if ($userFarms->count() > 1)
        <div class="dropdown">
            <button type="button" class="btn btn-link app-topbar-farm dropdown-toggle" data-bs-toggle="dropdown"
                    aria-expanded="false" data-testid="farm-switcher">
                <i class="bi bi-house-door"></i>
                <span>{{ $currentFarm->name }}</span>
            </button>
            <ul class="dropdown-menu">
                @foreach ($userFarms as $farm)
                    <li>
                        <form method="POST" action="{{ route('farm.switch') }}">
                            @csrf
                            <input type="hidden" name="farm_id" value="{{ $farm->id }}">
                            <button type="submit" class="dropdown-item {{ $farm->is($currentFarm) ? 'active' : '' }}">
                                {{ $farm->name }} <small class="text-body-secondary">({{ $farm->code }})</small>
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        <span class="app-topbar-farm" data-testid="farm-name">
            <i class="bi bi-house-door"></i>
            <span>{{ $currentFarm->name }}</span>
        </span>
    @endif
@endif
