{{-- "You have not run the report yet" state, shown inside the results table.

     @include('admin-views.report.tax-report.partials._blank', [
         'blank_colspan' => 5,
         'blank_title'   => translate('No tax report generated'),
         'blank_body'    => translate('messages.To generate your tax report please select & input above field and submit for the result'),
     ])

     Distinct from `.empty--data`: these reports are blank because the query
     has not been run, not because nothing matched. That reads as an
     instruction pointing back at the card above, so it keeps its own artwork.

     Styles: `tax.css` §4. --}}

<tr>
    <td colspan="{{ $blank_colspan }}">
        <div class="txr-blank">
            <img src="{{ asset('public/assets/admin/img/tax-error.png') }}" alt="">
            <h4>{{ $blank_title }}</h4>
            <p>{{ $blank_body }}</p>
        </div>
    </td>
</tr>
