export function GET() {
  const noindex = import.meta.env.PUBLIC_NOINDEX === '1';
  const body = noindex
    ? 'User-agent: *\nDisallow: /\n'
    : 'User-agent: *\nAllow: /\n\nSitemap: https://booknilecruises.net/sitemap.xml\n';
  return new Response(body, { headers: { 'Content-Type': 'text/plain' } });
}
