<?php
declare(strict_types=1);

use Bnc\Html;

test('sanitiser keeps ordinary trip content unchanged', function () {
    $in = '<p class="x">Day <strong>1</strong>: Luxor<br>Karnak &amp; Luxor temples – “ok” é</p><ul class="wp-block-list"><li>Lunch</li></ul>'
        . '<h3>Notes</h3><table><tbody><tr><td colspan="2">A</td></tr></tbody></table>'
        . '<p><a href="https://en.wikipedia.org/wiki/Karnak" target="_blank" rel="noreferrer noopener">Karnak</a> <a href="/trip/x/">x</a></p>'
        . '<img src="/images/2025/12/a.jpg" alt="A" width="800" height="600">';
    assert_same($in, Html::clean($in));
});

test('sanitiser removes scripts, event handlers and dangerous URLs', function () {
    $out = Html::clean('<p onclick="x()" style="color:red">Hi<script>alert(1)</script></p>'
        . '<a href="javascript:alert(1)">a</a><a href="java&#9;script:alert(1)">b</a><a href=" JAVASCRIPT:alert(1)">c</a>'
        . '<img src="data:image/svg+xml,<svg onload=alert(1)>" onerror="alert(1)"><img src="x" onerror=alert(1)>'
        . '<svg><script>alert(1)</script></svg><style>p{}</style><iframe src="https://evil.example/"></iframe>'
        . '<form action="/x"><input name="a"></form><!-- comment --><a href="//evil.example/">d</a>');
    foreach (['script', 'onclick', 'onerror', 'javascript', 'style', 'svg', 'evil.example', 'data:', '<form', '<input', 'comment', 'alert'] as $bad) {
        assert_not_contains($bad, strtolower($out), $bad);
    }
    assert_contains('<p>Hi</p>', $out);
    assert_contains('<a>a</a>', $out);
});

test('sanitiser unwraps unknown tags but keeps their text', function () {
    assert_same('<div>Open<p>Body</p></div>', Html::clean('<div data-wp-interactive="x" role="region"><button data-wp-on--click="x">Open</button><p>Body</p></div>'));
});

test('sanitiser allows YouTube and Google Maps embeds only', function () {
    assert_contains('youtube.com/embed/abc', Html::clean('<iframe src="https://www.youtube.com/embed/abc" title="v" allowfullscreen></iframe>'));
    assert_contains('google.com/maps/embed', Html::clean('<iframe src="https://www.google.com/maps/embed?pb=1"></iframe>'));
    assert_same('', Html::clean('<iframe src="https://www.google.com/search?q=x"></iframe>'));
    assert_same('', Html::clean('<iframe src="http://www.youtube.com/embed/abc"></iframe>'));
});

test('target=_blank links always get rel=noopener', function () {
    assert_same('<a href="https://x.example/" target="_blank" rel="noopener">x</a>', Html::clean('<a href="https://x.example/" target="_blank">x</a>'));
    assert_same('<a href="/a/">x</a>', Html::clean('<a href="/a/" target="_top" rel="opener evil">x</a>'));
});
