# Pointing the React app at this API

The screens stay as they are. Only the data layer changes: every Firestore read
or write becomes an HTTP call.

## 1. Environment

```env
VITE_API_URL=https://api.your-domain.com/api/v1
VITE_PUSHER_KEY=...
VITE_PUSHER_CLUSTER=eu
```

## 2. API client

```ts
// src/lib/api.ts
const BASE = import.meta.env.VITE_API_URL;

let token: string | null = localStorage.getItem("ma3roof_token");

export function setToken(next: string | null) {
  token = next;
  next ? localStorage.setItem("ma3roof_token", next) : localStorage.removeItem("ma3roof_token");
}

export async function api<T>(path: string, init: RequestInit = {}): Promise<T> {
  const res = await fetch(`${BASE}${path}`, {
    ...init,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...init.headers,
    },
  });

  const body = await res.json().catch(() => ({}));

  if (!res.ok) {
    throw Object.assign(new Error(body.message ?? "Request failed"), {
      status: res.status,
      code: body.error,
      errors: body.errors,
      context: body.context,
    });
  }

  return body as T;
}
```

## 3. Replacing Firebase calls

| Firebase | Replacement |
| --- | --- |
| `signInWithEmailAndPassword` | `POST /auth/login`, then `setToken(res.access_token)` |
| `createUserWithEmailAndPassword` | `POST /auth/register` |
| `onAuthStateChanged` | `GET /auth/me` on boot; clear the token on 401 |
| `signOut` | `POST /auth/logout` + `setToken(null)` |
| `getDocs(collection(db,'brands'))` | `GET /brands` |
| `addDoc` / `setDoc` / `updateDoc` | `POST` / `PATCH` on the matching resource |
| `deleteDoc` | `DELETE` on the resource |
| `onSnapshot` | Pusher subscription (below) plus TanStack Query invalidation |

Keep TanStack Query keys unchanged so components need no edits:

```ts
useQuery({ queryKey: ["brands"], queryFn: () => api<{data: Brand[]}>("/brands").then(r => r.data) });
```

## 4. Live updates

```ts
import Echo from "laravel-echo";
import Pusher from "pusher-js";

const echo = new Echo({
  broadcaster: "pusher",
  key: import.meta.env.VITE_PUSHER_KEY,
  cluster: import.meta.env.VITE_PUSHER_CLUSTER,
  forceTLS: true,
  authEndpoint: `${import.meta.env.VITE_API_URL.replace("/api/v1", "")}/broadcasting/auth`,
  auth: { headers: { Authorization: `Bearer ${localStorage.getItem("ma3roof_token")}` } },
});

echo.private(`users.${userId}`)
  .listen(".post.status.changed", () => queryClient.invalidateQueries({ queryKey: ["content", "posts"] }))
  .listen(".notification.created", () => queryClient.invalidateQueries({ queryKey: ["notifications"] }));
```

## 5. Things the frontend must stop doing

- Do not hold API provider keys in the browser. Content and image generation
  runs through the backend, which owns the keys.
- Do not decide roles, prices or credit balances locally. Read them from
  `/auth/me`, `/plans` and `/billing/wallet`.
- Send `Idempotency-Key: <uuid>` on generation and design requests so a retry
  after a dropped connection does not charge twice.
