<?php
use App\View;
use App\Store;
View::page('Privacy Policy', 'How TechZeeLand Fonts handles data: no cookies, no analytics, no stored IP addresses.', 'Privacy Policy', 'Last updated: October 2026',
'<h2>Short version</h2>
<p>No accounts for visitors, no cookies, no analytics, no ads, no fingerprinting. We keep only anonymous request counters.</p>
<h2>What happens when you load a font or a page</h2>
<p>Your browser or server requests a CSS file, a font file or a page from us. To answer, the application reads the request, finds the font and sends it back. It does not write your IP address, user agent or referrer to any file or database.</p>
<h2>What we store</h2>
<ul>
<li><strong>Anonymous counters:</strong> how many CSS, font file, ZIP and API requests happened per day and per font. They contain no personal data and cannot be traced back to a visitor.</li>
<li><strong>Font library and settings:</strong> the fonts we host and site configuration, managed by the administrator.</li>
<li><strong>Admin sign-in (administrator only):</strong> a password hash, an activity log of administrative actions, and a short-lived hash of the connecting address used only to slow down repeated failed sign-ins. These entries expire within minutes.</li>
</ul>
<h2>Server logs</h2>
<p>Whoever hosts an instance (a web host, CDN or reverse proxy) may keep ordinary access logs such as IP address, time and requested URL. The application does not add anything to them. If you cache fonts with our helper scripts, <em>your server</em> makes an occasional request to ours (about once per refresh interval), so your server\'s address may appear in those logs instead of your visitors\'.</p>
<h2>Third parties</h2>
<p>Pages load the icon stylesheet and icon images from <code>icons.rayaz.org</code>, a service operated by the same developer. It is a static file host; a request to it exposes your IP address to that host in the same way as any website. No other third-party scripts, fonts, trackers or advertising are used. The fonts you embed from this service load from this service (or from your own server once cached).</p>
<h2>Your rights</h2>
<p>Because we hold no personal data about visitors, there is nothing to access, correct or delete. Questions? <a href="/contact">Contact us</a> at ' . h(Store::setting('contact_email')) . '.</p>
<h2>Children</h2>
<p>The service is a developer tool and collects no personal data from anyone, including children.</p>
<h2>Changes</h2>
<p>If our data handling changes, we will update this page and its date.</p>');
