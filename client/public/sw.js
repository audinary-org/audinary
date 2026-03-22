// Audinary Service Worker
const CACHE_NAME = "audinary";
const urlsToCache = [
  "/",
  "/src/main.js",
  //'/src/styles/style.css'
];

// Install Service Worker
self.addEventListener("install", (event) => {
  event.waitUntil(
    caches
      .open(CACHE_NAME)
      .then((cache) => {
        return cache.addAll(urlsToCache);
      })
      .catch((error) => {
        console.error("Cache installation failed:", error);
        // Don't fail if cache can't be populated
        return Promise.resolve();
      }),
  );
});

// Fetch event
self.addEventListener("fetch", (event) => {
  // Only handle GET requests
  if (event.request.method !== "GET") {
    return;
  }

  // Skip non-HTTP(S) requests
  if (!event.request.url.startsWith("http")) {
    return;
  }

  // Skip API requests — especially media streams which are too large to cache
  const url = new URL(event.request.url);
  if (url.pathname.startsWith("/api/")) {
    return;
  }

  event.respondWith(
    caches
      .match(event.request)
      .then((response) => {
        // Return cached version or fetch from network
        return response || fetch(event.request);
      })
      .catch(() => {
        // If both cache and network fail, return a basic response for navigation requests
        if (event.request.mode === "navigate") {
          return caches.match("/");
        }
        return new Response("", { status: 503 });
      }),
  );
});

// Activate Service Worker
self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            console.warn("Deleting old cache:", cacheName);
            return caches.delete(cacheName);
          }
        }),
      );
    }),
  );
});
