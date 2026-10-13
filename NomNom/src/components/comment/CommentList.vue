<!-- src/components/comment/CommentList.vue -->
<script setup>
import { computed } from 'vue'
import '../../assets/css/comment-list.css'

const props = defineProps({
  comments: { type: Array, default: () => [] },
  currentUser: { type: String, default: '' },
})

const emit = defineEmits(['delete-comment'])

// Computed: newest comments first
const sortedComments = computed(() =>
  [...props.comments].sort(
    (a, b) => new Date(b.createdAt) - new Date(a.createdAt),
  ),
)

function isOwn(comment) {
  return comment.userName === props.currentUser && props.currentUser !== ''
}
</script>

<template>
  <section class="comment-list" aria-labelledby="comments-heading">
    <h2 id="comments-heading" class="comment-heading">
      Comments
      <span class="comment-count">{{ comments.length }}</span>
    </h2>

    <p v-if="comments.length === 0" class="comment-empty">
      No comments yet. Be the first to share your thoughts.
    </p>

    <ul v-else class="comment-items">
      <li
        v-for="comment in sortedComments"
        :key="comment.id"
        class="comment-item"
      >
        <img
          :src="comment.userAvatar"
          :alt="comment.userName"
          class="comment-avatar"
        />

        <div class="comment-body">
          <header class="comment-header">
            <RouterLink
              :to="`/users/${comment.userName}`"
              class="comment-author"
            >
              {{ comment.userName }}
            </RouterLink>
            <span class="comment-time">
              {{ new Date(comment.createdAt).toLocaleDateString() }}
            </span>
          </header>

          <p class="comment-text">{{ comment.text }}</p>

          <ul v-if="comment.images?.length" class="comment-images">
            <li v-for="(img, i) in comment.images" :key="i">
              <img :src="img" :alt="`Comment image ${i + 1}`" />
            </li>
          </ul>

          <button
            v-if="isOwn(comment)"
            type="button"
            class="comment-delete"
            @click="emit('delete-comment', comment.id)"
          >
            <i class="bi bi-trash" aria-hidden="true"></i>
            Delete
          </button>
        </div>
      </li>
    </ul>
  </section>
</template>