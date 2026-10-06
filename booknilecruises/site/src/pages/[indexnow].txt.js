import { VERIFY } from '../lib/site.mjs';

// IndexNow ownership file (/<key>.txt), present only when the admin panel has a key.
export function getStaticPaths() {
  return VERIFY.indexnow ? [{ params: { indexnow: VERIFY.indexnow } }] : [];
}

export function GET({ params }) {
  return new Response(params.indexnow, { headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
}
