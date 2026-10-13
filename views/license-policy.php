<?php
use App\View;
use App\Store;
$rows = '';
foreach (Store::LICENSES as $k => [$name, $url]) {
    $rows .= '<tr><td><code>' . h($k) . '</code></td><td>' . ($url !== '' ? '<a href="' . h($url) . '" target="_blank" rel="noopener">' . h($name) . '</a>' : h($name)) . '</td></tr>';
}
View::page('Font License Policy', 'Which licenses TechZeeLand Fonts accepts and what they mean for you.', 'Font License Policy', 'Last updated: October 2026',
'<h2>Our promise</h2>
<p>Every font hosted here can be used <strong>for free, including in commercial work</strong>, without registration or attribution fees. We accept only fonts under licenses that guarantee this.</p>
<h2>Accepted licenses</h2>
<table class="doc-table"><thead><tr><th>Identifier</th><th>License</th></tr></thead><tbody>' . $rows . '</tbody></table>
<p>“Other” is used only after we have read the license and confirmed it allows free commercial use, modification and redistribution.</p>
<h2>What we do not host</h2>
<ul>
<li>Fonts that are free only for personal or non-commercial use, or demo/trial versions.</li>
<li>Fonts with unclear, missing or contradictory licensing.</li>
<li>Fonts extracted or copied from commercial products, apps or websites without permission.</li>
<li>Modified versions that reuse a Reserved Font Name without permission.</li>
</ul>
<h2>What it means for you</h2>
<ul>
<li><strong>Use</strong> the fonts in websites, apps, print, video, products and client work.</li>
<li><strong>Keep the license text</strong> with any font files you pass on to others. Every ZIP and <code>/f/&lt;font&gt;/LICENSE.txt</code> includes it.</li>
<li><strong>Do not sell the fonts by themselves</strong> (SIL OFL), and do not publish a modified font under its original reserved name.</li>
<li>Where a license asks for attribution (for example CC BY 4.0), you must give it.</li>
</ul>
<p>The exact terms are in each font\'s license; this page is a plain-language summary, not legal advice.</p>
<h2>Credit and removal</h2>
<p>Designers are credited on each font page. Designers and rights holders can ask us to update or remove a font at any time: ' . h(Store::setting('contact_email')) . '.</p>
<h2>Trademarks</h2>
<p>Font names may be trademarks of their owners. Their appearance here does not imply endorsement.</p>');
