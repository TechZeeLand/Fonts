<?php
use App\View;
View::page('Cookie Policy', 'Which cookies and browser storage TechZeeLand Fonts uses.', 'Cookie Policy', 'Last updated: October 2026',
'<h2>Short version</h2>
<p>Visitors get <strong>no cookies</strong>. We use no analytics, advertising or tracking technologies.</p>
<h2>What is stored in your browser</h2>
<table class="doc-table"><thead><tr><th>Name</th><th>Type</th><th>Purpose</th><th>Lifetime</th></tr></thead><tbody>
<tr><td><code>tzl-theme</code></td><td>localStorage (not a cookie)</td><td>Remembers your light/dark choice if you use the theme toggle. Never sent to the server.</td><td>Until you clear it</td></tr>
<tr><td><code>tzl_admin</code></td><td>Cookie (HttpOnly, SameSite=Strict, limited to <code>/admin</code>)</td><td>Keeps the site administrator signed in. Only set after a successful sign-in on the admin login page, never for visitors.</td><td>Until the browser closes or you sign out</td></tr>
</tbody></table>
<h2>Fonts do not set cookies</h2>
<p>CSS and font files are static responses without cookies, so they are safe to cache on shared caches and CDNs.</p>
<h2>Control</h2>
<p>Clear site data in your browser to remove the theme preference. Nothing else is stored.</p>');
