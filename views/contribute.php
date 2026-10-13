<?php
use App\View;
use App\Store;
$mail = h(Store::setting('contact_email'));
$gh = h(Store::setting('github_url'));
View::page('Contribute a font', 'How to suggest or contribute an open-source font to TechZeeLand Fonts.', 'Contribute a font', 'Know a great open-source font that is missing? Help us add it.',
'<h2>What we accept</h2>
<p>A font can be added when <strong>all</strong> of these are true:</p>
<ul>
<li>It is released under an open license that allows free commercial use, modification and redistribution (see the <a href="/license-policy">Font License Policy</a>).</li>
<li>You can point to the original source: a repository, the designer\'s site or an official release.</li>
<li>The license text and copyright notice are available, so they can travel with the files.</li>
<li>Its name does not clash with a Reserved Font Name or someone else\'s trademark.</li>
</ul>
<h2>How to suggest one</h2>
<ol>
<li>Open an issue at <a href="' . $gh . '/issues" target="_blank" rel="noopener">GitHub</a> or email <a href="mailto:' . $mail . '">' . $mail . '</a>.</li>
<li>Include the font name, designer, source link and license.</li>
<li>We check the license and files, then publish it. You will get a link to its page.</li>
</ol>
<h2>If you designed the font</h2>
<p>Tell us, and we will credit you on the font page and link to your site. If you ever want a font removed or updated, write to us and we will act on it promptly.</p>
<h2>Run your own collection</h2>
<p>You do not need our permission to host your own fonts: the service is open source. Deploy it with Docker, sign in at <code>/admin</code> and upload your fonts. See the <a href="/docs#run-your-own-tzl-fonts">documentation</a>.</p>');
