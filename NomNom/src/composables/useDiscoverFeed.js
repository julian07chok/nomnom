import { nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";

import { apiRequest } from "../services/api";

export function useDiscoverFeed(filters) {
  const posts = ref([]);
  const loading = ref(false);
  const error = ref("");
  const hasMore = ref(true);
  const sentinel = ref(null);

  let offset = 0;
  let generation = 0;
  let controller = null;
  let observer = null;
  let disposed = false;

  function resolveUrl(url) {
    if (typeof url !== "string") return "";

    return url.startsWith("api/")
      ? `${import.meta.env.BASE_URL}${url}`
      : url;
  }

  async function loadMore() {
    if (disposed || loading.value || !hasMore.value) return;

    const requestGeneration = generation;
    const requestedOffset = offset;

    loading.value = true;
    error.value = "";
    controller = new AbortController();

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

      // Default 50km if not specified by the caller.
      const radius =
        filters.value.radiusKm != null ? filters.value.radiusKm : 50;
      query.set("radius", String(radius));
    }

    try {
      const data = await apiRequest(`feed.php?${query}`, {
        signal: controller.signal,
      });

      if (disposed || requestGeneration !== generation) return;

      if (
        !Array.isArray(data.posts) ||
        !Number.isInteger(data.nextOffset) ||
        data.nextOffset !== requestedOffset + data.posts.length ||
        (data.hasMore && data.posts.length === 0)
      ) {
        throw new Error("The server returned an invalid feed batch.");
      }

      const existingIds = new Set(posts.value.map((post) => String(post.id)));

      const batch = data.posts
        .filter((post) => !existingIds.has(String(post.id)))
        .map((post) => ({
          ...post,
          coverImage: resolveUrl(post.coverImage),
          authorAvatar: resolveUrl(post.authorAvatar),

          // distanceKm comes from PHP when Near Me is active
          // (rounded to 2 dp server-side). Leave as-is for
          // simple consumption by formatDistance().
          distanceKm:
            post.distanceKm === null || post.distanceKm === undefined
              ? null
              : Number(post.distanceKm),
        }));

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

  watch(filters, resetFeed, { immediate: true, deep: true });

  watch([loading, () => posts.value.length], fillVisibleSpace);

  onMounted(() => {
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