# TURN relay for WebRTC calls

Why this exists: the frontend's `useWebRTC` engine (`mindful-au-frontend/src/lib/videoCall.ts`)
falls back to public Google STUN servers when no `VITE_WEBRTC_TURN_*` env vars are set.
STUN alone lets two peers discover their public IP/port, but it cannot relay media through
a symmetric NAT or a restrictive firewall — the case for most mobile carrier data and some
office networks. Without TURN, a call between (for example) a student on mobile data and a
counselor on campus wifi can ring but never actually connect. This is called out in
`mindful-au-frontend/README.md` under "If calls work on the same network but fail across
different laptops or off-campus networks, TURN configuration is the first thing to verify."

## 1. Provision

Run this on the same VPS that serves `mindfulapi.africau.co.zw` (or any host with a public,
non-NATed IP). It needs `network_mode: host`, so it must run outside Dokploy's Traefik proxy
— deploy it as a plain `docker compose` stack over SSH, not as a Dokploy git-connected app.

```sh
cd mindful-au-backend/docker/turn
cp .env.example .env
# edit .env: set TURN_REALM, TURN_USERNAME, and a long random TURN_PASSWORD
docker compose up -d
docker compose logs -f
```

## 2. Open firewall ports

On the host firewall (and cloud provider security group, if applicable):

| Port | Protocol | Purpose |
|------|----------|---------|
| 3478 | UDP + TCP | TURN/STUN control |
| 49160–49200 | UDP | Media relay (matches `TURN_MIN_PORT`/`TURN_MAX_PORT` in `.env`) |

```sh
# ufw example
sudo ufw allow 3478/udp
sudo ufw allow 3478/tcp
sudo ufw allow 49160:49200/udp
```

## 3. Point DNS at it (optional but recommended)

Add an A record, e.g. `turn.africau.co.zw` → the server's public IP. Not required — a bare
IP works in the `turn:` URL — but a hostname survives an eventual IP change.

## 4. Wire it into the frontend build

In Dokploy, on the **frontend** application's environment variables (these are baked in at
*build* time — redeploy/rebuild after changing them):

```
VITE_WEBRTC_TURN_URLS=turn:turn.africau.co.zw:3478?transport=udp,turn:turn.africau.co.zw:3478?transport=tcp
VITE_WEBRTC_TURN_USERNAME=<TURN_USERNAME from .env>
VITE_WEBRTC_TURN_CREDENTIAL=<TURN_PASSWORD from .env>
```

(`VITE_WEBRTC_STUN_URLS` can stay unset — the built-in Google STUN fallback is fine to keep
using alongside TURN.) Also update `mindful-au-frontend/deploy/DOKPLOY.md`'s env var table if
it hasn't picked up these three already.

## 5. Verify

- App-level: open the browser dev console on a call page — `useWebRTC.ts` logs
  `[WebRTC] TURN relay servers are not configured...` if `VITE_WEBRTC_TURN_URLS` didn't make
  it into the build. No such warning means the client sees a relay candidate configured.
- Protocol-level, from any machine with the coturn image available:
  ```sh
  docker run --rm coturn/coturn:4.15.0 turnutils_uclient -u <TURN_USERNAME> -w <TURN_PASSWORD> -y turn.africau.co.zw
  ```
  A successful allocation confirms the server answers and authenticates correctly before you
  involve the browser at all.
- Real test: put one device on wifi and a second on mobile data (this is the combination that
  fails without TURN) and place a call between two test accounts.

## Notes on the credential model

This uses one **static long-term credential** shared by every client, matching what the
frontend already supports (`VITE_WEBRTC_TURN_USERNAME`/`CREDENTIAL` are plain values baked
into the public JS bundle). That means the credential is not actually secret — anyone can
read it out of the bundle. The `total-quota`/`user-quota`/`denied-peer-ip` flags in
`docker-compose.yml` bound the damage (relay bandwidth cap, and the server refuses to relay
to private/internal IP ranges), which is an adequate tradeoff for this app's scale. Rotate
`TURN_PASSWORD` occasionally (update `.env`, `docker compose up -d`, redeploy the frontend
with the new value).

If usage grows enough that this becomes a real abuse target, the standard upgrade is
coturn's `--use-auth-secret` REST API mechanism: the backend mints short-lived
(username, password) pairs per call via HMAC-SHA1 instead of shipping one static credential
to every visitor. That needs a small backend endpoint and a frontend change to fetch
credentials before starting a call, instead of reading them from build-time env vars — not
implemented here, flagged for later if needed.
