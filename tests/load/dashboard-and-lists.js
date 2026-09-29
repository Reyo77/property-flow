// Load test for the dashboard and the two main community lists (units, residents). See README.md
// in this directory for setup (a seeded company at scale, and a two-factor one-time code).
import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE = __ENV.BASE_URL || 'https://property-flow.test';
const EMAIL = __ENV.EMAIL || 'loadtest@propertyflow.test';
const PASSWORD = __ENV.PASSWORD || 'password';
const OTP = __ENV.OTP;
const COMMUNITY_ID = __ENV.COMMUNITY_ID || '1';
const VUS = Number(__ENV.VUS || 10);
const P95_TARGET_MS = Number(__ENV.P95_TARGET_MS || 300);

if (!OTP) {
  throw new Error('Set OTP to a current one-time code for the load-test admin (see README.md)');
}

export const options = {
  scenarios: {
    load: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '15s', target: VUS },
        { duration: '45s', target: VUS },
        { duration: '10s', target: 0 },
      ],
    },
  },
  thresholds: {
    [`http_req_duration{page:dashboard}`]: [`p(95)<${P95_TARGET_MS}`],
    [`http_req_duration{page:units}`]: [`p(95)<${P95_TARGET_MS}`],
    [`http_req_duration{page:residents}`]: [`p(95)<${P95_TARGET_MS}`],
  },
};

function extractCsrfToken(html) {
  const match = html.match(/name="_token" value="([^"]+)"/);
  return match ? match[1] : null;
}

export function setup() {
  const jar = http.cookieJar();

  let res = http.get(`${BASE}/login`);
  let token = extractCsrfToken(res.body);
  if (!token) {
    throw new Error('Could not find a CSRF token on the login page');
  }

  res = http.post(
    `${BASE}/login`,
    { email: EMAIL, password: PASSWORD, _token: token },
    { redirects: 0 },
  );
  if (res.status !== 302 || (res.headers.Location || '').indexOf('two-factor-challenge') === -1) {
    throw new Error(`Expected a redirect to two-factor-challenge, got ${res.status} -> ${res.headers.Location}`);
  }

  res = http.get(`${BASE}/two-factor-challenge`);
  token = extractCsrfToken(res.body);
  if (!token) {
    throw new Error('Could not find a CSRF token on the two-factor-challenge page');
  }

  res = http.post(
    `${BASE}/two-factor-challenge`,
    { code: OTP, _token: token },
    { redirects: 0 },
  );
  if (res.status !== 302 || (res.headers.Location || '').indexOf('dashboard') === -1) {
    throw new Error(`Two-factor challenge failed (code may have expired): ${res.status} -> ${res.headers.Location}`);
  }

  const cookies = jar.cookiesForURL(`${BASE}/`);
  const sessionCookie = cookies['propertyflow-session'] && cookies['propertyflow-session'][0];
  if (!sessionCookie) {
    throw new Error('No session cookie after completing login');
  }

  return { sessionCookie };
}

export default function (data) {
  const params = { cookies: { 'propertyflow-session': data.sessionCookie } };

  let res = http.get(`${BASE}/dashboard`, { ...params, tags: { page: 'dashboard' } });
  check(res, { 'dashboard is 200': (r) => r.status === 200 });

  res = http.get(`${BASE}/communities/${COMMUNITY_ID}/units`, { ...params, tags: { page: 'units' } });
  check(res, { 'units is 200': (r) => r.status === 200 });

  res = http.get(`${BASE}/communities/${COMMUNITY_ID}/residents`, { ...params, tags: { page: 'residents' } });
  check(res, { 'residents is 200': (r) => r.status === 200 });

  sleep(1);
}
