<!-- src/views/PostDetailView.vue -->
<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import posts from '../data/posts.json'
import commentsData from '../data/comments.json'
import users from '../data/users.json'

import PostGallery from '../components/post/PostGallery.vue'
import PostActions from '../components/post/PostActions.vue'
import CommentList from '../components/comment/CommentList.vue'
import CommentForm from '../components/comment/CommentForm.vue'

import '../assets/css/post-detail.css'

const route = useRoute()
const router = useRouter()

// 🔑 FAKE AUTH (for now) — swap with real auth service later
const currentUser = computed(() => {
  // Pretend logged-in user is "julian" (id=1).
  // Replace this with a real auth store in the backend phase.
  return users.find((u) => u.username === 'julian') || null
})
const isLoggedIn = computed(() => currentUser.value !== null)

// ─── 1. Post ────────────────────────────────────────────────
const post = ref(null)
const notFound = ref(false)

function loadPost(id) {
  const found = posts.find((p) => p.id === Number(id))
  if (!found) {
    notFound.value = true
    post.value = null
    return
  }
  notFound.value = false
  post.value = { ...found }   // local copy so like/save don't mutate the JSON
}

// Composition API (p.9): watch a specific source
watch(
  () => route.params.id,
  (newId) => loadPost(newId),
  { immediate: true },
)

// Computed: is current user the author?
const isOwner = computed(
  () => isLoggedIn.value && post.value?.authorName === currentUser.value?.username,
)

// ─── 2. Comments (local state) ──────────────────────────────
const comments = ref([])

function loadComments(postId) {
  comments.value = commentsData.filter((c) => c.postId === Number(postId))
}

// Reload comments when post changes
watch(post, (p) => {
  if (p) loadComments(p.id)
  else comments.value = []
})

// ─── 3. Interactions ────────────────────────────────────────
const liked = ref(false)
const saved = ref(false)

function toggleLike() {
  if (!isLoggedIn.value) {
    router.push({ name: 'login' })
    return
  }
  liked.value = !liked.value
  post.value.likes += liked.value ? 1 : -1
}

function toggleSave() {
  if (!isLoggedIn.value) {
    router.push({ name: 'login' })
    return
  }
  saved.value = !saved.value
}

function goEdit() {
  router.push({ name: 'edit-post', params: { id: post.value.id } })
}

function deletePost() {
  if (!confirm('Delete this post? This cannot be undone.')) return
  // In the mock version, we just navigate away.
  // Backend phase: call DELETE /posts/:id
  router.push({ name: 'home' })
}

function addComment(text) {
  const newComment = {
    id: Date.now(),
    postId: post.value.id,
    userId: currentUser.value.id,
    userName: currentUser.value.username,
    userAvatar: currentUser.value.avatar,
    text,
    images: [],
    createdAt: new Date().toISOString(),
  }
  comments.value = [newComment, ...comments.value]
}

function deleteComment(commentId) {
  comments.value = comments.value.filter((c) => c.id !== commentId)
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
          <RouterLink
            :to="`/users/${post.authorName}`"
            class="post-author"
          >
            <img
              :src="post.authorAvatar"
              :alt="post.authorName"
              class="post-author-avatar"
            />
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
          :liked="liked"
          :saved="saved"
          :is-owner="isOwner"
          :is-logged-in="isLoggedIn"
          @toggle-like="toggleLike"
          @toggle-save="toggleSave"
          @edit="goEdit"
          @delete="deletePost"
        />

        <section class="post-info">
          <h2 class="post-restaurant">
            <i class="bi bi-shop" aria-hidden="true"></i>
            {{ post.restaurantName }}
          </h2>
          <ul class="post-meta">
            <li><i class="bi bi-geo-alt" aria-hidden="true"></i> {{ post.address }}</li>
            <li><i class="bi bi-currency-dollar" aria-hidden="true"></i> {{ post.priceRange }}</li>
            <li><i class="bi bi-tag" aria-hidden="true"></i> {{ post.location }}</li>
          </ul>
        </section>

        <section class="post-description">
          <h2>About this place</h2>
          <p>{{ post.description }}</p>
        </section>

        <!-- Comments -->
        <CommentList
          :comments="comments"
          :current-user="currentUser?.username || ''"
          @delete-comment="deleteComment"
        />

        <CommentForm
          :is-logged-in="isLoggedIn"
          @submit-comment="addComment"
        />
      </article>
    </div>
  </main>
</template>