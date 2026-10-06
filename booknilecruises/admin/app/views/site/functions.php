<?php
declare(strict_types=1);
function bnc_site(): array { return \Bnc\Site\View::$site; }
function bnc_find(array $list, callable $predicate): mixed { foreach ($list as $item) if ($predicate($item)) return $item; return null; }
function bnc_asset(?string $src): ?string { if (!$src) return $src; $base = rtrim(bnc_site()['imagesBase'] ?? '/images', '/'); return str_starts_with($src, '/images/') ? $base . '/' . substr($src, 8) : $src; }
function bnc_srcset(?array $image): ?string { return empty($image['srcset']) ? null : implode(', ', array_map(fn($x) => bnc_asset($x['src']) . ' ' . $x['width'] . 'w', $image['srcset'])); }
function bnc_money(mixed $n, ?string $currency = 'USD'): ?string { if ($n === null) return null; $symbol = ['USD'=>'$', 'EUR'=>'€', 'GBP'=>'£'][$currency ?? 'USD'] ?? ($currency . ' '); return $symbol . number_format((float)$n, 0, '.', ','); }
function bnc_duration(?array $d = []): ?string { $days=$d['days']??null; $nights=$d['nights']??null; if (!$days) return null; return $days . ($days===1?' day':' days') . ($nights ? ' / ' . $nights . ($nights===1?' night':' nights'):''); }
function bnc_places(array $trip): string { return implode(' · ', array_column($trip['destinations'], 'name')); }
function bnc_category(array $trip): string { return $trip['activities'][0]['name'] ?? $trip['tripTypes'][0]['name'] ?? ''; }
function bnc_whatsapp(string $text = ''): string { return 'https://wa.me/' . \Bnc\Site\View::business()['whatsapp'] . ($text ? '?text=' . str_replace(['%21','%27','%28','%29','%2A'], ['!', "'", '(', ')', '*'], rawurlencode($text)) : ''); }
function bnc_mailto(string $subject = '', string $body = ''): string { $p=[]; if ($subject) $p['subject']=$subject; if ($body) $p['body']=$body; return 'mailto:' . \Bnc\Site\View::business()['email'] . ($p?'?'.str_replace(['~', '%2A'], ['%7E', '*'], http_build_query($p,'','&',PHP_QUERY_RFC3986)):''); }
function bnc_absolute(?string $path): string { if (preg_match('#^https?://#i',$path??'')) return $path; return 'https://booknilecruises.net/' . ltrim($path??'', '/'); }
function bnc_json(mixed $value): string { return (string)json_encode($value, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR); }
function bnc_attr(string $key, mixed $value): string { if ($value===null || $value===false) return ''; return ' ' . $key . ($value===true ? '' : '="' . e($value) . '"'); }
