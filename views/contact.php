<?php
use App\View;
use App\Store;
$li = static fn($i, $u, $t) => '<li><a href="' . $u . '" target="_blank" rel="noopener">' . ico($i) . ' ' . $t . '</a></li>';
$mail = h(Store::setting('contact_email'));
View::page('Contact', 'Get in touch with TechZeeLand, the team behind TechZeeLand Fonts.', 'Contact', 'We\'d love to hear from you.',
 '<p>For bugs, feature requests or font suggestions, opening an issue on GitHub is the fastest way to reach us. For license questions, removal requests or anything else, email or message us on social media.</p><ul class="contact-list">
<li><a href="mailto:' . $mail . '">' . ico('mail') . ' ' . $mail . '</a></li>'
 . $li('github', h(Store::setting('github_url')) . '/issues', 'Open an issue on GitHub')
 . $li('youtube', 'https://www.youtube.com/@TechZeeLand', 'YouTube — @TechZeeLand')
 . $li('facebook', 'https://www.facebook.com/TechZeeLand', 'Facebook — TechZeeLand')
 . $li('instagram', 'https://www.instagram.com/TechZeeLand', 'Instagram — @TechZeeLand')
 . $li('tiktok', 'https://www.tiktok.com/@TechZeeLand', 'TikTok — @TechZeeLand')
 . '</ul>');
