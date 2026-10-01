@if(session('success'))<div class="notice success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="notice error" role="alert"><strong>Vérifie les informations suivantes :</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if(session('import'))
@php($report = session('import'))
<div class="notice {{ $report['summary']['errors'] ? 'error' : 'success' }}" role="status">
    <strong>Résultat de l’import</strong> : {{ $report['summary']['created'] }} créé(s), {{ $report['summary']['updated'] }} mis à jour, {{ $report['summary']['skipped'] }} ignoré(s), {{ $report['summary']['errors'] }} erreur(s).
    @if($report['errorDetails'])<details><summary>Voir les lignes en erreur</summary><ul>@foreach($report['errorDetails'] as $error)<li>Ligne {{ $error['row'] }} : {{ $error['error'] }}</li>@endforeach</ul></details>@endif
</div>
@endif
