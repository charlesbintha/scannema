<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · Scannema</title>
    <link rel="stylesheet" href="{{ asset('admin.css') }}?v=1">
    <script src="{{ asset('admin.js') }}?v=1" defer></script>
</head>
<body>
<a class="skip" href="#content">Aller au contenu</a>
@isset($webUser)
<div class="workspace">
    <aside class="sidebar">
        <a href="{{ route('dashboard') }}"><img class="logo" src="{{ asset('branding/logo_no_bg.png') }}" alt="Scannema"></a>
        <div class="workspace-label"><span class="avatar">S</span><div><strong>Espace organisateur</strong><small>Gestion des événements</small></div></div>
        <p class="eyebrow">VOTRE ESPACE</p>
        <nav aria-label="Navigation principale">
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">@include('admin.icon', ['icon'=>'grid']) Vue d’ensemble</a>
            <a class="nav-link" href="{{ route('dashboard', request()->only('event')) }}#tickets">@include('admin.icon', ['icon'=>'ticket']) Tickets & paiements</a>
            <a class="nav-link" href="{{ route('dashboard') }}#events">@include('admin.icon', ['icon'=>'calendar']) Mes événements</a>
            @if($webUser->role !== 'CHECKER')
            <a class="nav-link {{ request()->routeIs('web.events.*') ? 'active' : '' }}" href="{{ route('web.events.new') }}">@include('admin.icon', ['icon'=>'plus']) Créer un événement</a>
            @endif
            @if($webUser->role === 'SUPER_ADMIN')
            <a class="nav-link {{ request()->routeIs('web.agents*') ? 'active' : '' }}" href="{{ route('web.agents') }}">@include('admin.icon', ['icon'=>'users']) Agents</a>
            @endif
        </nav>
        <div class="sidebar-note">@include('admin.icon', ['icon'=>'shield'])<strong>Chaque entrée compte.</strong><p>Un ticket payé.<br>Un scan. Une entrée.</p><span class="badge cyan">Contrôle sécurisé</span></div>
        <form action="{{ route('web.logout') }}" method="post">@csrf<button class="nav-link logout">@include('admin.icon', ['icon'=>'logout']) Se déconnecter</button></form>
    </aside>
    <div class="main">
        <header class="topbar"><span>Espace organisateur <span class="muted">/</span> <strong>@yield('title', 'Dashboard')</strong></span><div class="user-chip"><span class="online"></span>{{ $webUser->name }}<span class="badge">{{ ['SUPER_ADMIN'=>'Administrateur','MANAGER'=>'Gestionnaire','CHECKER'=>'Agent'][$webUser->role] ?? $webUser->role }}</span></div></header>
        <main id="content" class="content">
            @include('admin.messages')
            @yield('content')
        </main>
        <footer>SCANNEMA <span>La sérénité, à chaque entrée.</span></footer>
    </div>
</div>
@else
<main id="content" class="login-canvas">@yield('content')</main>
@endisset
</body>
</html>
