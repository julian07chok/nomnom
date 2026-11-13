<script setup>
import { ref, watch } from "vue";
import "../../assets/css/post-card.css";
import { useAuth } from "../../services/auth";
import UserAvatar from "../user/UserAvatar.vue";
import PostCuisineBadge from "./PostCuisineBadge.vue";

const props = defineProps({
  post: {
    type: Object,
    required: true,
  },
});

const { profileLinkFor } = useAuth();

// Tracks whether the current cover image has failed to load.
// Reset whenever the post changes so a different post's image
// gets a fresh chance to load.
const coverFailed = ref(false);

watch(
  () => props.post.coverImage,
  () => {
    coverFailed.value = false;
  },
);

function onCoverError() {
  coverFailed.value = true;
}
</script>

<template>
  <article class="post-card">
    <RouterLink :to="`/posts/${post.id}`" class="post-cover-link">
      <img
        v-if="post.coverImage && !coverFailed"
        :src="post.coverImage"
        :alt="post.title"
        class="post-cover"
        loading="lazy"
        @error="onCoverError"
      />
      <div v-else class="post-cover-fallback" aria-hidden="true">
        <i class="bi bi-image"></i>
      </div>
      <PostCuisineBadge :cuisine="post.cuisine" />
    </RouterLink>

    <div class="post-body">
      <div class="post-meta">
        <span class="location">
          <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
          {{ post.location }}
        </span>
      </div>

      <h3 class="post-title">
        <RouterLink :to="`/posts/${post.id}`">{{ post.title }}</RouterLink>
      </h3>

      <p class="post-restaurant">
        <i class="bi bi-shop" aria-hidden="true"></i>
        {{ post.restaurantName }}
      </p>

      <footer class="post-footer">
        <RouterLink :to="profileLinkFor(post.authorName)" class="post-author">
          <UserAvatar :src="post.authorAvatar" :size="28" />
          <span>{{ post.authorDisplayName || post.authorName }}</span>
        </RouterLink>

        <span class="post-likes">
          <i class="bi bi-heart-fill" aria-hidden="true"></i>
          {{ post.likes }}
        </span>
      </footer>
    </div>
  </article>
</template>