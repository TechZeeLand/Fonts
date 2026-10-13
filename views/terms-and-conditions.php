<?php
use App\View;
use App\Store;
View::page('Terms & Conditions', 'Terms of use for TechZeeLand Fonts.', 'Terms &amp; Conditions', 'Last updated: October 2026',
'<h2>Use of the service</h2>
<p>TechZeeLand Fonts is free for personal and commercial use. You may link to our CSS and font files, download fonts, and cache them on your own servers. Please be a good citizen: cache files instead of requesting them on every page view, and do not send automated traffic that degrades the service for others. We may rate-limit or block abusive traffic.</p>
<h2>Fonts and their licenses</h2>
<p>Each font is provided under its own open license, shown on the font page and included in its ZIP download. Your use of a font is governed by that license, not by these terms. If you redistribute font files, keep the license text with them. See the <a href="/license-policy">Font License Policy</a>.</p>
<h2>Availability</h2>
<p>The service is provided without an uptime guarantee. For production use we strongly recommend caching fonts on your own server using the documented helpers, so your site keeps working even if the service is unavailable.</p>
<h2>No warranty</h2>
<p>The service and the software are provided “as is”, without warranty of any kind, including fitness for a particular purpose and non-infringement. Test fonts in your own project before shipping.</p>
<h2>Liability</h2>
<p>To the extent permitted by law, the authors and operators are not liable for any loss arising from use of the service, including downtime, rendering differences or license disputes concerning third-party fonts.</p>
<h2>Contributions</h2>
<p>If you suggest or submit a font, you confirm that you have the right to do so and that it is available under an open license that allows free commercial use. We may decline or remove any font at our discretion, for example if its license status is unclear.</p>
<h2>Takedown requests</h2>
<p>If you believe a font here is hosted in breach of its license or your rights, email ' . h(Store::setting('contact_email')) . ' with the font name and details. We will review promptly and remove it when appropriate.</p>
<h2>Open source</h2>
<p>The software is licensed under the <a href="https://www.gnu.org/licenses/agpl-3.0.html" target="_blank" rel="noopener">GNU AGPL-3.0</a>. If you run a modified version as a network service, you must offer its source code to your users.</p>
<h2>Changes</h2>
<p>We may update these terms; continued use after a change means you accept the updated terms.</p>
<h2>Contact</h2>
<p><a href="/contact">Contact us</a>.</p>');
