<script setup>
import { computed } from "vue";
import { useRoute } from "vue-router";
import { usePosts } from "./services/posts";

import AppNavbar from "./components/layout/AppNavBar.vue";
import AppFooter from "./components/layout/AppFooter.vue";

const route = useRoute();
const { isLoading, postError, loadPosts, postsLoaded } = usePosts(); // ← add postsLoaded

const needsPosts = computed(() =>
  [
    "home", "search", "post-detail", "edit-post",
    "my-profile", "user-profile", "saved",
  ].includes(route.name),
);
</script>

<template>
  <div class="app-shell">
    <AppNavbar />

    <div class="page-content">
      <!-- Only show loading state on initial load, not on re-fetches -->
      <div
        v-if="isLoading && needsPosts && !postsLoaded"
        class="container py-5"
        role="status"
      >
        Loading discoveries…
      </div>

      <div
        v-else-if="needsPosts && postError && !postsLoaded"
        class="container py-5"
      >
        <div class="alert alert-danger" role="alert">
          {{ postError }}
        </div>
        <button class="btn btn-outline-dark" type="button" @click="loadPosts">
          Try again
        </button>
      </div>

      <RouterView v-else />
    </div>

    <AppFooter />
  </div>
</template>

<style scoped>
.app-shell {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  min-height: 100dvh;
}

.page-content {
  flex: 1;
  min-width: 0;
}

@media (max-width: 767.98px) {
  .page-content {
    padding-bottom: calc(80px + env(safe-area-inset-bottom));
  }
}
</style>