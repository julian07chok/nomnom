<!-- src/components/comment/CommentForm.vue -->
<script setup>
import { ref } from 'vue'
import '../../assets/css/comment-form.css'

const props = defineProps({
  isLoggedIn: { type: Boolean, default: false },
})

const emit = defineEmits(['submit-comment'])

const text = ref('')
const error = ref('')

const MAX_LEN = 500

function validate() {
  const trimmed = text.value.trim()
  if (!trimmed) {
    error.value = 'Please write something before posting.'
    return false
  }
  if (trimmed.length > MAX_LEN) {
    error.value = `Comment must be ${MAX_LEN} characters or fewer.`
    return false
  }
  error.value = ''
  return true
}

function handleSubmit() {
  if (!validate()) return
  emit('submit-comment', text.value.trim())
  text.value = ''
}
</script>

<template>
  <div class="comment-form-wrap">
    <p v-if="!isLoggedIn" class="comment-form-login">
      <RouterLink to="/login">Log in</RouterLink>
      to leave a comment.
    </p>

    <form v-else class="comment-form" @submit.prevent="handleSubmit">
      <label for="comment" class="comment-label">Add a comment</label>
      <textarea
        id="comment"
        v-model="text"
        class="comment-input"
        rows="3"
        :maxlength="MAX_LEN"
        placeholder="Share your thoughts…"
        aria-describedby="comment-help"
      ></textarea>

      <p id="comment-help" class="comment-help">
        {{ text.length }} / {{ MAX_LEN }} characters
      </p>

      <p v-if="error" class="comment-error" role="alert">{{ error }}</p>

      <button type="submit" class="comment-submit">Post comment</button>
    </form>
  </div>
</template>