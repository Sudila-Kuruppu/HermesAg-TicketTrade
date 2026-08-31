<?php
$current = $_SERVER['REQUEST_URI'] ?? '/';
?>
<nav class="bottom-nav d-md-none" data-component="bottom-nav" aria-label="Primary">
<a class="bottom-nav-item <?= str_starts_with($current, '/board') ? 'active' : '' ?>" href="/board">
<span class="bottom-nav-icon" aria-hidden="true">▦</span>
<span class="bottom-nav-label">Board</span>
</a>
<a class="bottom-nav-item <?= str_starts_with($current, '/my-listings') ? 'active' : '' ?>" href="/my-listings">
<span class="bottom-nav-icon" aria-hidden="true">≡</span>
<span class="bottom-nav-label">Listings</span>
</a>
<a class="bottom-nav-item <?= str_starts_with($current, '/my-tickets') ? 'active' : '' ?>" href="/my-tickets">
<span class="bottom-nav-icon" aria-hidden="true">◧</span>
<span class="bottom-nav-label">Tickets</span>
</a>
<a class="bottom-nav-item <?= str_starts_with($current, '/sales') ? 'active' : '' ?>" href="/sales">
<span class="bottom-nav-icon" aria-hidden="true">◆</span>
<span class="bottom-nav-label">Sales</span>
</a>
<a class="bottom-nav-item <?= str_starts_with($current, '/profile') ? 'active' : '' ?>" href="/profile">
<span class="bottom-nav-icon" aria-hidden="true">◉</span>
<span class="bottom-nav-label">Profile</span>
</a>
</nav>
