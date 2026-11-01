<div class="modal fade" id="builderRequirementsModal" tabindex="-1" role="dialog"
     aria-labelledby="builderRequirementsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content" id="builderRequirementsData">
            @if(session('builder_requirements_issues'))
                @include('admin-views.system.addon.partials.builder-requirements-modal-data', [
                    'issues'     => session('builder_requirements_issues'),
                    'addon_name' => session('builder_requirements_addon', 'Builder'),
                ])
            @endif
        </div>
    </div>
</div>
