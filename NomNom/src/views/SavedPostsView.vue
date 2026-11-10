<script setup>
import { computed, ref, watch } from "vue";
import { usePosts } from "../services/posts";

import PostCard from "../components/post/PostCard.vue";
import Pagination from "../components/common/Pagination.vue";

const { posts, setSaved } = usePosts();

const currentPage = ref(1);
const perPage = 6;
const pendingIds = ref([]);
const actionError = ref("");

const savedPosts = computed(() =>
  posts.value
    .filter((post) => post.savedByMe)
    .sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt)),
);

const totalPages = computed(() =>
  Math.max(1, Math.ceil(savedPosts.value.length / perPage)),
);

const paginatedPosts = computed(() => {
  const start = (currentPage.value - 1) * perPage;
  return savedPosts.value.slice(start, start + perPage);
});

// Removing the last item on a page should return to a valid page.
watch(totalPages, (maximum) => {
  if (currentPage.value > maximum) {
    currentPage.value = maximum;
  }
});

async function removeSaved(id) {
  if (pendingIds.value.includes(id)) return;

  pendingIds.value.push(id);
  actionError.value = "";

  try {
    await setSaved(id, false);
  } catch (error) {
    actionError.value = error.message || "Unable to remove this saved post.";
  } finally {
    pendingIds.value = pendingIds.value.filter((pendingId) => pendingId !== id);
  }
}
</script>

<template>
  <main class="container py-5">
    <header id="saved-posts-top" class="mb-4">
      <h1 class="fw-bold">Saved discoveries</h1>
      <p class="text-secondary">
        Your bookmarked spots for your next food trip.
      </p>

      <p>
        {{ savedPosts.length }}
        {{ savedPosts.length === 1 ? "saved post" : "saved posts" }}
      </p>
    </header>

    <p v-if="actionError" class="alert alert-danger" role="alert">
      {{ actionError }}
    </p>

    <div v-if="!savedPosts.length" class="text-center py-5">
      <i class="bi bi-bookmark fs-1" aria-hidden="true"></i>
      <h2 class="h4 mt-3">No saved discoveries yet</h2>
      <p>Save a post to keep it here for later.</p>

      <RouterLink class="btn btn-outline-dark" to="/">
        Explore food posts
      </RouterLink>
    </div>

    <div v-else class="row g-4">
      <div
        v-for="post in paginatedPosts"
        :key="post.id"
        class="col-12 col-sm-6 col-lg-4"
      >
        <PostCard :post="post" />

        <button
          class="btn btn-outline-dark w-100 mt-2"
          type="button"
          :disabled="pendingIds.includes(post.id)"
          :aria-label="`Remove ${post.title} from saved posts`"
          @click="removeSaved(post.id)"
        >
          {{ pendingIds.includes(post.id) ? "Removing…" : "Remove from saved" }}
        </button>
      </div>
    </div>

    <Pagination
      v-if="totalPages > 1"
      v-model:currentPage="currentPage"
      :total-pages="totalPages"
      :total-items="savedPosts.length"
      :per-page="perPage"
      scroll-target-id="saved-posts-top"
    />
  </main>
</template>
