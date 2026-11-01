{{-- Sidebar collapse / expand glyph.
     Drawn inline rather than pulled from lucide because it animates between
     its two states — the panel block slides back behind the rail and the
     chevron turns to point the way the next click will go. Swapping two
     lucide icons meant replacing the node and re-running createIcons() on
     every click, which could only ever pop.
     Styled by the .v2-mode-glyph block in admin-v2.css. Shared by the admin
     and vendor topbars, so it renders once per page and the clipPath id is
     safe to hard-code. --}}
<svg class="v2-mode-glyph" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    <clipPath id="v2ModeGlyphClip">
        <rect x="3" y="3" width="18" height="18" rx="2.4"/>
    </clipPath>
    <g clip-path="url(#v2ModeGlyphClip)">
        <rect class="v2-mode-glyph__fill" x="3" y="3" width="6" height="18"/>
    </g>
    <rect class="v2-mode-glyph__frame" x="3" y="3" width="18" height="18" rx="2.4"/>
    <path class="v2-mode-glyph__divider" d="M9 3.85V20.15"/>
    <path class="v2-mode-glyph__chev" d="M16.35 9.9 14.25 12l2.1 2.1"/>
</svg>
