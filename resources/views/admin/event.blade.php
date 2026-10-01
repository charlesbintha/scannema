@extends('admin.layout')
@section('title', $event ? 'Modifier un événement' : 'Nouvel événement')
@section('content')
<div class="page-heading"><div><p class="eyebrow">PRÉPAREZ VOTRE PROCHAINE RENCONTRE</p><h1>{{ $event ? 'Votre événement' : 'Créer un événement' }}<span class="title-dot">.</span></h1><p class="muted">Les tickets et les agents pourront être ajoutés ensuite.</p></div><a class="button" href="{{ route('dashboard') }}">Retour</a></div>
<section class="panel form-panel"><form action="{{ $event ? route('web.events.update',$event->id) : route('web.events.create') }}" method="post" class="form-stack">@csrf @if($event) @method('PATCH') @endif
<div class="form-grid"><label>Nom de l’événement<input name="name" required maxlength="255" value="{{ old('name',$event?->name) }}" placeholder="Concert St Kisito 2026"></label><label>Code unique<input name="code" required maxlength="100" value="{{ old('code',$event?->code) }}" placeholder="CONCERT-ST-KISITO-2026"></label>
<label>Statut<select name="status">@foreach(['DRAFT'=>'Brouillon','LIVE'=>'En cours','ENDED'=>'Terminé','CANCELLED'=>'Annulé'] as $value=>$label)<option value="{{ $value }}" @selected(old('status',$event?->status ?? 'DRAFT')===$value)>{{ $label }}</option>@endforeach</select></label><label>Invités attendus<input name="expected_guests" type="number" min="0" max="10000000" required value="{{ old('expected_guests',$event?->expected_guests ?? 0) }}"></label>
<label>Début (facultatif)<input type="datetime-local" name="starts_at" value="{{ old('starts_at',$event?->starts_at?->copy()->setTimezone($event->timezone)->format('Y-m-d\TH:i')) }}"></label><label>Fin (facultative)<input type="datetime-local" name="ends_at" value="{{ old('ends_at',$event?->ends_at?->copy()->setTimezone($event->timezone)->format('Y-m-d\TH:i')) }}"></label>
<label>Fuseau horaire<input name="timezone" required value="{{ old('timezone',$event?->timezone ?? 'Africa/Dakar') }}" list="timezones"><datalist id="timezones"><option value="Africa/Dakar"><option value="Africa/Abidjan"><option value="Europe/Paris"><option value="UTC"></datalist></label></div>
<p class="muted small">Les dates saisies sont exprimées dans le fuseau de l’événement. Aucun prix ou paiement ne sera créé automatiquement.</p>
<div class="actions"><button class="button primary">Enregistrer l’événement</button><a class="button" href="{{ route('dashboard') }}">Annuler</a></div>
</form></section>
@endsection
