<?php
use App\View;
use App\Store;
$s = h(View::site());
View::page('About', 'About TechZeeLand Fonts: open-source fonts, free for commercial use, built to be cached on your own server.', 'About ' . $s,
 'A small, focused font service that respects your visitors and your page speed.', '
<h2>What it is</h2>
<p>' . $s . ' (TZL Fonts) hosts open-source fonts that anyone can use in their projects, including commercial ones, for free. You add one line of CSS to use a font, just like with Google Fonts, and you can then keep a copy of the files on your own server so your site no longer depends on anyone else to load.</p>
<h2>Why it exists</h2>
<p>Third-party font hosts are convenient but add a DNS lookup, a TLS handshake and a render-blocking stylesheet to the first visit, and they see every visitor. TZL Fonts is built around the opposite idea: <strong>serve fonts with aggressive caching, and make it trivial to cache them on your own server</strong> with PHP, Node and Nginx helpers that refresh themselves every few days.</p>
<h2>Only truly free fonts</h2>
<p>Every font must carry an open license that allows free commercial use and redistribution, such as the SIL Open Font License. Read the <a href="/license-policy">Font License Policy</a> and, if you have a font to share, <a href="/contribute">contribute it</a>. All credit belongs to the original designers, whose names and licenses are shown on each font page.</p>
<h2>Open source &amp; self-hosted</h2>
<p>The software is released under the <a href="https://www.gnu.org/licenses/agpl-3.0.html" target="_blank" rel="noopener">AGPL-3.0</a>. Run your own copy with Docker; source, issues and contributions live on <a href="' . h(Store::setting('github_url')) . '" target="_blank" rel="noopener">GitHub</a>. The <a href="/docs">documentation</a> explains everything from the first line of CSS to running your own instance.</p>
<h2>Made in Bangladesh</h2>
<p>Built and maintained by <a href="https://github.com/TechZeeLand" target="_blank" rel="noopener">TechZeeLand</a>, based in Bangladesh. The interface is set in <a href="/font/alata">Alata</a>, the first font in the collection.</p>');
