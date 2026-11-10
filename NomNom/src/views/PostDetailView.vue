<!-- src/views/PostDetailView.vue -->
<script setup>
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { usePosts } from "../services/posts";
import { useAuth } from "../services/auth";
import {
  getComments,
  createComment,
  updateComment,
  deleteComment,
} from "../services/comments";

import PostGallery from "../components/post/PostGallery.vue";
import UserAvatar from "../components/user/UserAvatar.vue";
import PostActions from "../components/post/PostActions.vue";
import CommentForm from "../components/comment/CommentForm.vue";
import CommentList from "../components/comment/CommentList.vue";

import "../assets/css/post-detail.css";

const route = useRoute();
const router = useRouter();
const isDeleting = ref(false);
const deleteError = ref("");
const interactionBusy = ref(false);
const interactionError = ref("");

const {
  posts,
  isLoading,
  postError,
  deletePost: deletePostFromService,
  setLike,
  setSaved,
} = usePosts();

const { currentUser, isLoggedIn, profileLinkFor } = useAuth();

const post = computed(
  () =>
    posts.value.find((item) => String(item.id) === String(route.params.id)) ??
    null,
);

const comments = ref([]);
const commentsLoading = ref(false);
const commentsError = ref("");

let commentRequestVersion = 0;

async function loadComments() {
  const postId = post.value?.id;
  const version = ++commentRequestVersion;

  comments.value = [];
  commentsError.value = "";

  if (!postId) {
    commentsLoading.value = false;
    return;
  }

  commentsLoading.value = true;

  try {
    const result = await getComments(postId);

    if (version === commentRequestVersion) {
      comments.value = result;
    }
  } catch (error) {
    if (version === commentRequestVersion) {
      commentsError.value = error.message || "Unable to load comments.";
    }
  } finally {
    if (version === commentRequestVersion) {
      commentsLoading.value = false;
    }
  }
}

watch(() => post.value?.id, loadComments, { immediate: true });

async function submitComment(draft) {
  const postId = post.value.id;
  const result = await createComment(postId, draft);

  if (post.value?.id === postId) {
    comments.value = result;
  }
}

async function saveComment(id, text) {
  const postId = post.value.id;
  const result = await updateComment(id, text);

  if (post.value?.id === postId) {
    comments.value = result;
  }
}

async function removeComment(id) {
  const postId = post.value.id;
  const result = await deleteComment(id);

  if (post.value?.id === postId) {
    comments.value = result;
  }
}

const notFound = computed(
  () => !isLoading.value && !postError.value && !post.value,
);

const isOwner = computed(
  () =>
    Boolean(currentUser.value && post.value) &&
    Number(post.value.authorId) === Number(currentUser.value.id),
);

async function handleDelete() {
  if (
    !post.value ||
    isDeleting.value ||
    interactionBusy.value ||
    !isOwner.value
  )
    return;

  if (!window.confirm("Delete this post? This cannot be undone.")) {
    return;
  }

  isDeleting.value = true;
  deleteError.value = "";

  try {
    await deletePostFromService(post.value.id);
  } catch (error) {
    deleteError.value = error.message || "Unable to delete this post.";
    isDeleting.value = false;
    return;
  }

  await router.push({ name: "home" });
  isDeleting.value = false;
}

async function handleInteraction(type) {
  if (!post.value || interactionBusy.value || isDeleting.value) return;

  if (!isLoggedIn.value) {
    await router.push({
      name: "login",
      query: { redirect: route.fullPath },
    });
    return;
  }

  interactionBusy.value = true;
  interactionError.value = "";

  try {
    if (type === "like") {
      await setLike(post.value.id, !post.value.likedByMe);
    } else {
      await setSaved(post.value.id, !post.value.savedByMe);
    }
  } catch (error) {
    interactionError.value = error.message || "Unable to update this post.";
  } finally {
    interactionBusy.value = false;
  }
}
</script>

<template>
  <main class="post-detail">
    <div class="container post-detail-inner">
      <!-- 404 -->
      <div v-if="notFound" class="not-found">
        <h1>Post not found</h1>
        <p>The post you're looking for doesn't exist or was removed.</p>
        <RouterLink class="not-found-link" to="/">Back to Discover</RouterLink>
      </div>

      <!-- Post -->
      <article v-else-if="post" class="post-article">
        <header class="post-header">
          <RouterLink :to="profileLinkFor(post.authorName)" class="post-author">
            <UserAvatar :src="post.authorAvatar" :size="40" />
            <div>
              <p class="post-author-name">{{ post.authorName }}</p>
              <p class="post-date">
                {{ new Date(post.createdAt).toLocaleDateString() }}
              </p>
            </div>
          </RouterLink>

          <span class="post-cuisine">{{ post.cuisine }}</span>
        </header>

        <h1 class="post-title">{{ post.title }}</h1>

        <PostGallery :images="post.gallery" :alt="post.title" />

        <PostActions
          :likes="post.likes"
          :liked="post.likedByMe"
          :saved="post.savedByMe"
          :is-logged-in="isLoggedIn"
          :busy="interactionBusy || isDeleting"
          @toggle-like="handleInteraction('like')"
          @toggle-save="handleInteraction('save')"
        />

        <p v-if="interactionError" class="alert alert-danger" role="alert">
          {{ interactionError }}
        </p>

        <div v-if="isOwner" class="d-flex flex-wrap gap-2 mb-4">
          <RouterLink
            v-if="!isDeleting && !interactionBusy"
            class="btn btn-outline-dark"
            :to="{ name: 'edit-post', params: { id: post.id } }"
          >
            <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>
            Edit post
          </RouterLink>

          <button
            class="btn btn-outline-danger"
            type="button"
            :disabled="isDeleting || interactionBusy"
            @click="handleDelete"
          >
            {{ isDeleting ? "Deleting…" : "Delete post" }}
          </button>
        </div>

        <p v-if="deleteError" class="alert alert-danger" role="alert">
          {{ deleteError }}
        </p>

        <section class="post-info">
          <h2 class="post-restaurant">
            <i class="bi bi-shop" aria-hidden="true"></i>
            {{ post.restaurantName }}
          </h2>
          <ul class="post-meta">
            <li>
              <i class="bi bi-geo-alt" aria-hidden="true"></i>
              {{ post.address }}
            </li>
            <li>
              <i class="bi bi-currency-dollar" aria-hidden="true"></i>
              {{ post.priceRange }}
            </li>
            <li>
              <i class="bi bi-tag" aria-hidden="true"></i>
              {{ post.location }}
            </li>
          </ul>
        </section>

        <section class="post-description">
          <h2>About this place</h2>
          <p>{{ post.description }}</p>
        </section>

        <section class="mt-5">
          <p v-if="commentsLoading" role="status">Loading comments…</p>

          <div v-else-if="commentsError">
            <p class="alert alert-danger" role="alert">
              {{ commentsError }}
            </p>

            <button
              class="btn btn-outline-dark"
              type="button"
              @click="loadComments"
            >
              Try again
            </button>
          </div>

          <template v-else>
            <CommentList
              :key="String(post.id)"
              :comments="comments"
              :current-user-id="currentUser?.id ?? null"
              :save-comment="saveComment"
              :remove-comment="removeComment"
            />

            <CommentForm
              :key="`form-${post.id}`"
              :is-logged-in="isLoggedIn"
              :submit-comment="submitComment"
            />
          </template>
        </section>
      </article>
    </div>
  </main>
</template>
