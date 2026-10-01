@extends('admin.layout')
@section('title', 'Connexion')
@section('content')
<div class="login-shell">
    <section class="login-story"><img class="logo" src="{{ asset('branding/logo_no_bg.png') }}" alt="Scannema"><div><p class="eyebrow">LE CONTRÔLE, EN TOUTE SÉRÉNITÉ</p><h1>Chaque entrée<br><span>compte.</span></h1><p>Vos événements, vos équipes et vos invitations.<br>Un seul espace pour tout orchestrer.</p></div><div class="login-proof">@include('admin.icon', ['icon'=>'shield']) Une invitation. Un paiement. Une entrée.</div></section>
    <section class="login-form"><span class="badge cyan">ESPACE ORGANISATEUR</span><h2>Heureux de vous revoir.</h2><p class="muted">Connectez-vous avec le compte attribué par votre administrateur.</p>@include('admin.messages')
        <form action="{{ route('web.login') }}" method="post" class="form-stack">@csrf
            <label>Email ou identifiant<input name="email" value="{{ old('email') }}" autocomplete="username" required autofocus maxlength="190"></label>
            <label>Mot de passe<input type="password" name="password" autocomplete="current-password" required></label>
            <button class="button primary">Se connecter <span aria-hidden="true">→</span></button>
        </form><p class="small muted">Pas de création de compte publique. Contactez votre organisateur pour obtenir un accès.</p>
    </section>
</div>
@endsection
