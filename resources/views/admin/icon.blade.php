<svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($icon)
@case('grid') <rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/> @break
@case('ticket') <path d="M3 5h18v5a2 2 0 0 0 0 4v5H3v-5a2 2 0 0 0 0-4zM15 5v3m0 3v2m0 3v3"/> @break
@case('calendar') <rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4m10-4v4M3 11h18m-13 5h2m4 0h2"/> @break
@case('plus') <path d="M12 5v14M5 12h14"/> @break
@case('users') <circle cx="9" cy="7" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3m2-17a3 3 0 0 1 0 6m2 4a5 5 0 0 1 2 4v3"/> @break
@case('shield') <path d="m12 3 9 4v6c0 5-9 9-9 9S3 18 3 13V7zM8 12l3 3 5-6"/> @break
@case('logout') <path d="M9 4H4v16h5m5-13 5 5-5 5m-6-5h11"/> @break
@case('check') <path d="m5 12 4 4L19 6"/><circle cx="12" cy="12" r="10"/> @break
@case('clock') <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/> @break
@default <path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zm10 0h3v3h3v3h-6z"/>
@endswitch
</svg>
