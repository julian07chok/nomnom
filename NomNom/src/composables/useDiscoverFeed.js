import { nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";

import { apiRequest } from "../services/api";

export function useDiscoverFeed(filters) {
  // Reactive feed data and loading feedback for the page
  const posts = ref([]);
  const loading = ref(false);
  const error = ref("");
  const hasMore = ref(true);
  // The element near the bottom of the feed that the observer watches
  const sentinel = ref(null);

  // Offset selects the next batch; generation identifies the current filter set
  let offset = 0;
  let generation = 0;
  let controller = null;
  let observer = null;
  let disposed = false;

  function resolveUrl(url) {
    if (typeof url !== "string") return "";

    return url.startsWith("api/") ? `${import.meta.env.BASE_URL}${url}` : url;
  }

  async function loadMore() {
    // Avoid overlapping requests and stop when all posts have been loaded
    if (disposed || loading.value || !hasMore.value) return;

    const requestGeneration = generation;
    const requestedOffset = offset;

    loading.value = true;
    error.value = "";
    controller = new AbortController();

    // Request four posts at a time using the current filters
    const query = new URLSearchParams({
      offset: String(offset),
      limit: "4",
      cuisine: filters.value.cuisine,
      sort: filters.value.sort,
    });

    // ── Near Me (Haversine) parameters ─────────────────────
    // When the user has allowed geolocation and Near Me is on,
    // these parameters tell the backend to compute distances
    // in SQL and filter / sort by proximity.
    if (filters.value.lat != null && filters.value.lng != null) {
      query.set("lat", String(filters.value.lat));
      query.set("lng", String(filters.value.lng));

      // Default 50km if not specified by the caller
      const radius =
        filters.value.radiusKm != null ? filters.value.radiusKm : 50;
      query.set("radius", String(radius));
    }

    try {
      const data = await apiRequest(`feed.php?${query}`, {
        signal: controller.signal,
      });

      // Ignore old responses after filters change or the page is unmounted
      if (disposed || requestGeneration !== generation) return;

      if (
        !Array.isArray(data.posts) ||
        !Number.isInteger(data.nextOffset) ||
        data.nextOffset !== requestedOffset + data.posts.length ||
        (data.hasMore && data.posts.length === 0)
      ) {
        throw new Error("The server returned an invalid feed batch.");
      }

      // Skip posts already displayed in an earlier batch
      const existingIds = new Set(posts.value.map((post) => String(post.id)));

      const batch = data.posts
        .filter((post) => !existingIds.has(String(post.id)))
        .map((post) => ({
          ...post,
          coverImage: resolveUrl(post.coverImage),
          authorAvatar: resolveUrl(post.authorAvatar),

          // distanceKm comes from PHP when Near Me is active
          // (rounded to 2 dp server-side). Leave as-is for
          // simple consumption by formatDistance()
          distanceKm:
            post.distanceKm === null || post.distanceKm === undefined
              ? null
              : Number(post.distanceKm),
        }));

      // Append the batch so Vue renders more cards without replacing existing ones
      posts.value.push(...batch);
      offset = data.nextOffset;
      hasMore.value = Boolean(data.hasMore);
    } catch (err) {
      if (
        !disposed &&
        requestGeneration === generation &&
        err.name !== "AbortError"
      ) {
        error.value = err.message || "Unable to load discoveries.";
      }
    } finally {
      if (!disposed && requestGeneration === generation) {
        loading.value = false;
      }
    }
  }

  function resetFeed() {
    // Cancel the previous request and restart from the first batch
    generation++;
    controller?.abort();

    posts.value = [];
    offset = 0;
    hasMore.value = true;
    error.value = "";
    loading.value = false;

    loadMore();
  }

  async function fillVisibleSpace() {
    // Wait for rendering, then load more if the screen is not yet filled
    await nextTick();

    if (
      disposed ||
      !sentinel.value ||
      loading.value ||
      error.value ||
      !hasMore.value
    ) {
      return;
    }

    const bounds = sentinel.value.getBoundingClientRect();

    if (bounds.top <= window.innerHeight + 200 && bounds.bottom >= 0) {
      loadMore();
    }
  }

  // Load the first batch immediately and restart when filters change
  watch(filters, resetFeed, { immediate: true, deep: true });

  // Recheck available space after loading finishes or posts are added
  watch([loading, () => posts.value.length], fillVisibleSpace);

  onMounted(() => {
    // Load the next batch when the target comes within 200px of the viewport
    if ("IntersectionObserver" in window) {
      observer = new IntersectionObserver(
        (entries) => {
          if (entries.some((entry) => entry.isIntersecting) && !error.value) {
            loadMore();
          }
        },
        { rootMargin: "200px 0px" },
      );

      if (sentinel.value) {
        observer.observe(sentinel.value);
      }
    }

    fillVisibleSpace();
  });

  onBeforeUnmount(() => {
    // Cancel pending work and disconnect the observer when leaving the page
    disposed = true;
    generation++;
    controller?.abort();
    observer?.disconnect();
  });

  return {
    posts,
    loading,
    error,
    hasMore,
    sentinel,
    loadMore,
  };
}
