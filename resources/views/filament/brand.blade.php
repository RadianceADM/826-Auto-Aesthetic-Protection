{{-- Admin brand: the 826 mark (public/brand) beside the rest of the name --}}
<span class="app-brand">
    @if (is_file(public_path(\App\Http\Controllers\SiteController::LOGO)))
        <img class="app-brand-logo" src="{{ asset(\App\Http\Controllers\SiteController::LOGO) }}" alt="826" />
    @endif
    @if (is_file(public_path(\App\Http\Controllers\SiteController::LOGO)))
        <span class="app-brand-name"><span class="app-brand-rest">Auto Aesthetic &amp; Protection</span></span>
    @else
        <span class="app-brand-name">826 <span class="app-brand-rest">Auto Aesthetic &amp; Protection</span></span>
    @endif
</span>
